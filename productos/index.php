<?php
/** READ: listado de productos del emprendedor con búsqueda, filtros y paginación. */
require_once __DIR__ . '/../includes/productos.php';

$emp = emprendedor_actual();
$pdo = conexion();
$comision = porcentaje_comision();

// Filtros
$q         = trim((string)($_GET['q'] ?? ''));
$categoria = (int)($_GET['categoria'] ?? 0);
$estado    = in_array($_GET['estado'] ?? '', ESTADOS_EDITABLES, true) ? $_GET['estado'] : '';
$ordenes   = [
    'recientes'   => 'p.fecha_creacion DESC, p.id_producto DESC',
    'nombre'      => 'p.nombre ASC',
    'precio_asc'  => 'p.precio_base ASC',
    'precio_desc' => 'p.precio_base DESC',
    'stock'       => 'p.stock ASC',
];
$orden = array_key_exists($_GET['orden'] ?? '', $ordenes) ? $_GET['orden'] : 'recientes';

$where  = ["p.id_emprendedor = :emp", "p.estado <> 'eliminado'"];
$params = [':emp' => $emp['id_emprendedor']];
if ($q !== '') {
    $where[] = '(p.nombre LIKE :q1 OR p.descripcion LIKE :q2)';
    $params[':q1'] = $params[':q2'] = '%' . $q . '%';
}
if ($categoria > 0) {
    $where[] = 'p.id_categoria = :cat';
    $params[':cat'] = $categoria;
}
if ($estado !== '') {
    $where[] = 'p.estado = :estado';
    $params[':estado'] = $estado;
}
$sqlWhere = implode(' AND ', $where);

// Paginación
$porPagina = 8;
$st = $pdo->prepare("SELECT COUNT(*) FROM producto p WHERE $sqlWhere");
$st->execute($params);
$total   = (int)$st->fetchColumn();
$paginas = max(1, (int)ceil($total / $porPagina));
$pagina  = min(max(1, (int)($_GET['pagina'] ?? 1)), $paginas);
$offset  = ($pagina - 1) * $porPagina;

$st = $pdo->prepare(
    "SELECT p.*, c.nombre AS categoria,
            (SELECT url FROM imagen_producto i WHERE i.id_producto = p.id_producto ORDER BY orden, id_imagen LIMIT 1) AS imagen
       FROM producto p
       JOIN categoria c ON c.id_categoria = p.id_categoria
      WHERE $sqlWhere
      ORDER BY {$ordenes[$orden]}
      LIMIT $porPagina OFFSET $offset"
);
$st->execute($params);
$productos = $st->fetchAll();

// Resumen
$st = $pdo->prepare(
    "SELECT SUM(estado = 'activo') activos, SUM(estado = 'inactivo') inactivos, SUM(stock = 0) agotados
       FROM producto WHERE id_emprendedor = ? AND estado <> 'eliminado'"
);
$st->execute([$emp['id_emprendedor']]);
$resumen = $st->fetch();

function url_con(array $cambios): string
{
    return '?' . http_build_query(array_merge($_GET, $cambios));
}

$titulo = 'Mis productos';
require __DIR__ . '/../includes/header.php';
?>
<section class="encabezado-pagina">
  <div>
    <p class="sobretitulo"><?= e($emp['nombre_emprendimiento']) ?></p>
    <h1>Mis productos</h1>
  </div>
  <div class="resumen">
    <div><strong><?= (int)$resumen['activos'] ?></strong><span>activos</span></div>
    <div><strong><?= (int)$resumen['inactivos'] ?></strong><span>inactivos</span></div>
    <div class="<?= $resumen['agotados'] ? 'resumen-alerta' : '' ?>"><strong><?= (int)$resumen['agotados'] ?></strong><span>sin stock</span></div>
  </div>
</section>

<form class="filtros" method="get">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o descripción" aria-label="Buscar">
  <select name="categoria" aria-label="Categoría">
    <option value="0">Todas las categorías</option>
    <?php foreach (categorias_activas() as $c): ?>
      <option value="<?= (int)$c['id_categoria'] ?>" <?= $categoria === (int)$c['id_categoria'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="estado" aria-label="Estado">
    <option value="">Todos los estados</option>
    <option value="activo" <?= $estado === 'activo' ? 'selected' : '' ?>>Activos</option>
    <option value="inactivo" <?= $estado === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
  </select>
  <select name="orden" aria-label="Ordenar">
    <option value="recientes" <?= $orden === 'recientes' ? 'selected' : '' ?>>Más recientes</option>
    <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre (A-Z)</option>
    <option value="precio_asc" <?= $orden === 'precio_asc' ? 'selected' : '' ?>>Precio: menor a mayor</option>
    <option value="precio_desc" <?= $orden === 'precio_desc' ? 'selected' : '' ?>>Precio: mayor a menor</option>
    <option value="stock" <?= $orden === 'stock' ? 'selected' : '' ?>>Menos stock primero</option>
  </select>
  <button class="btn">Filtrar</button>
  <?php if ($q !== '' || $categoria || $estado !== '' || $orden !== 'recientes'): ?>
    <a class="enlace-suave" href="index.php">Limpiar</a>
  <?php endif; ?>
</form>

<?php if (!$productos): ?>
  <div class="vacio">
    <?php if ($total === 0 && $q === '' && !$categoria && $estado === ''): ?>
      <h2>Aún no tienes productos</h2>
      <p>Publica el primero en tres pasos: datos, precio y fotos.</p>
      <a href="crear.php" class="btn btn-primario">+ Publicar mi primer producto</a>
    <?php else: ?>
      <h2>No encontramos productos con esos filtros</h2>
      <p><a href="index.php">Ver todos mis productos</a></p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <p class="nota">El precio final incluye la comisión de la plataforma (<?= e(rtrim(rtrim(number_format($comision, 2, ',', ''), '0'), ',')) ?> %). Es el precio que ve el cliente.</p>
  <div class="tabla-envoltura">
    <table class="tabla">
      <thead>
        <tr>
          <th>Producto</th><th>Categoría</th><th class="num">Precio base</th><th class="num">Precio final</th>
          <th class="num">Stock</th><th>Estado</th><th><span class="sr">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($productos as $p): ?>
        <tr>
          <td data-label="Producto">
            <a class="celda-producto" href="ver.php?id=<?= (int)$p['id_producto'] ?>">
              <img src="<?= e(url_imagen($p['imagen'])) ?>" alt="" loading="lazy">
              <span><?= e($p['nombre']) ?></span>
            </a>
          </td>
          <td data-label="Categoría"><?= e($p['categoria']) ?></td>
          <td data-label="Precio base" class="num"><?= cop($p['precio_base']) ?></td>
          <td data-label="Precio final" class="num"><strong><?= cop(precio_final((float)$p['precio_base'], $comision)) ?></strong></td>
          <td data-label="Stock" class="num <?= (int)$p['stock'] === 0 ? 'texto-alerta' : '' ?>"><?= (int)$p['stock'] ?></td>
          <td data-label="Estado"><?= etiqueta_estado($p['estado']) ?></td>
          <td class="acciones">
            <a class="btn btn-sm" href="editar.php?id=<?= (int)$p['id_producto'] ?>">Editar</a>
            <form method="post" action="estado.php">
              <?= campo_csrf() ?>
              <input type="hidden" name="id" value="<?= (int)$p['id_producto'] ?>">
              <button class="btn btn-sm btn-suave"><?= $p['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
            </form>
            <form method="post" action="eliminar.php" onsubmit="return confirm('¿Eliminar «<?= e(addslashes($p['nombre'])) ?>»? Esta acción no se puede deshacer.');">
              <?= campo_csrf() ?>
              <input type="hidden" name="id" value="<?= (int)$p['id_producto'] ?>">
              <button class="btn btn-sm btn-peligro">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($paginas > 1): ?>
    <nav class="paginacion" aria-label="Páginas">
      <?php for ($i = 1; $i <= $paginas; $i++): ?>
        <a href="<?= e(url_con(['pagina' => $i])) ?>" class="<?= $i === $pagina ? 'actual' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
