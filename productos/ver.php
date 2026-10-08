<?php
/** READ (detalle): ficha del producto con desglose de precio e historial. */
require_once __DIR__ . '/../includes/productos.php';

$emp = emprendedor_actual();
$comision = porcentaje_comision();
$id = (int)($_GET['id'] ?? 0);
$p = producto_del_emprendedor($id, (int)$emp['id_emprendedor']);
if (!$p) {
    flash('error', 'Ese producto no existe o no pertenece a tu emprendimiento.');
    redirigir('index.php');
}
$imagenes = imagenes_producto($id);
$final = precio_final((float)$p['precio_base'], $comision);

$st = conexion()->prepare(
    "SELECT COALESCE(SUM(d.cantidad), 0) unidades, COUNT(DISTINCT d.id_pedido) pedidos
       FROM detalle_pedido d JOIN pedido pe ON pe.id_pedido = d.id_pedido
      WHERE d.id_producto = ? AND pe.estado NOT IN ('pendiente_pago','cancelado','reembolsado')"
);
$st->execute([$id]);
$ventas = $st->fetch();

$st = conexion()->prepare(
    "SELECT accion, estado_anterior, estado_nuevo, detalle, fecha
       FROM bitacora WHERE entidad = 'producto' AND id_entidad = ? ORDER BY fecha DESC, id_evento DESC LIMIT 10"
);
$st->execute([$id]);
$historial = $st->fetchAll();

$titulo = $p['nombre'];
require __DIR__ . '/../includes/header.php';
?>
<section class="encabezado-pagina">
  <div>
    <p class="sobretitulo"><a href="index.php">← Mis productos</a></p>
    <h1><?= e($p['nombre']) ?></h1>
  </div>
  <a class="btn btn-primario" href="editar.php?id=<?= $id ?>">Editar</a>
</section>

<div class="ficha">
  <div class="ficha-galeria">
    <img class="ficha-principal" id="foto-principal" src="<?= e(url_imagen($imagenes[0]['url'] ?? null)) ?>" alt="<?= e($p['nombre']) ?>">
    <?php if (count($imagenes) > 1): ?>
      <div class="ficha-miniaturas">
        <?php foreach ($imagenes as $img): ?>
          <button type="button" onclick="document.getElementById('foto-principal').src=this.dataset.src" data-src="<?= e(url_imagen($img['url'])) ?>">
            <img src="<?= e(url_imagen($img['url'])) ?>" alt="">
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="ficha-datos">
    <p><?= etiqueta_estado($p['estado']) ?> <span class="chip"><?= e($p['categoria']) ?></span></p>
    <p class="precio-grande"><?= cop($final) ?></p>
    <div class="desglose">
      <div><span>Precio base (recibes)</span><span><?= cop($p['precio_base']) ?></span></div>
      <div><span>Comisión (<?= e(rtrim(rtrim(number_format($comision, 2, ',', ''), '0'), ',')) ?> %)</span><span><?= cop($final - (float)$p['precio_base']) ?></span></div>
      <div class="desglose-total"><span>Precio para el cliente</span><span><?= cop($final) ?></span></div>
    </div>
    <dl class="datos">
      <div><dt>Stock</dt><dd class="<?= (int)$p['stock'] === 0 ? 'texto-alerta' : '' ?>"><?= (int)$p['stock'] ?> unidades</dd></div>
      <div><dt>Vendidas</dt><dd><?= (int)$ventas['unidades'] ?> en <?= (int)$ventas['pedidos'] ?> pedido(s)</dd></div>
      <div><dt>Publicado</dt><dd><?= e(date('d/m/Y', strtotime($p['fecha_creacion']))) ?></dd></div>
    </dl>
    <?php if ($p['descripcion']): ?>
      <h2 class="subtitulo">Descripción</h2>
      <p class="descripcion"><?= nl2br(e($p['descripcion'])) ?></p>
    <?php endif; ?>
  </div>
</div>

<?php if ($historial): ?>
  <h2 class="subtitulo">Historial de cambios</h2>
  <ul class="historial">
    <?php foreach ($historial as $h): ?>
      <li>
        <time><?= e(date('d/m/Y H:i', strtotime($h['fecha']))) ?></time>
        <strong><?= e(ucfirst($h['accion'])) ?></strong>
        <?php if ($h['estado_anterior'] !== $h['estado_nuevo'] && $h['estado_nuevo']): ?>
          · <?= e($h['estado_anterior'] ?? 'nuevo') ?> → <?= e($h['estado_nuevo']) ?>
        <?php endif; ?>
        <?php if ($h['detalle']): ?><span><?= e($h['detalle']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
