<?php
/**
 * Formulario compartido por crear.php y editar.php.
 * Variables esperadas: $datos, $errores, $imagenes (array), $comision, $accion, $textoBoton.
 * Organizado en 3 pasos (RNF-11: publicar en máximo 3 pasos).
 */
function error_campo(array $errores, string $campo): string
{
    return isset($errores[$campo])
        ? '<p class="error-campo" id="err-' . e($campo) . '">' . e($errores[$campo]) . '</p>'
        : '';
}
function aria_error(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? ' aria-invalid="true" aria-describedby="err-' . e($campo) . '"' : '';
}
?>
<?php if ($errores): ?>
  <div class="alerta alerta-error" role="alert">
    Revisa los campos marcados en rojo antes de guardar.
  </div>
<?php endif; ?>

<form class="formulario" method="post" action="<?= e($accion) ?>" enctype="multipart/form-data" novalidate>
  <?= campo_csrf() ?>

  <fieldset class="paso">
    <legend><span class="paso-num">1</span> ¿Qué vendes?</legend>
    <label for="nombre">Nombre del producto</label>
    <input id="nombre" name="nombre" maxlength="120" required value="<?= e($datos['nombre']) ?>"
           placeholder="Ej.: Café especial 500 g"<?= aria_error($errores, 'nombre') ?>>
    <?= error_campo($errores, 'nombre') ?>

    <label for="id_categoria">Categoría</label>
    <select id="id_categoria" name="id_categoria" required<?= aria_error($errores, 'id_categoria') ?>>
      <option value="">Elige una categoría</option>
      <?php foreach (categorias_activas() as $c): ?>
        <option value="<?= (int)$c['id_categoria'] ?>" <?= (string)$datos['id_categoria'] === (string)$c['id_categoria'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <?= error_campo($errores, 'id_categoria') ?>

    <label for="descripcion">Descripción <span class="opcional">(opcional)</span></label>
    <textarea id="descripcion" name="descripcion" rows="4" maxlength="2000"
              placeholder="Cuenta qué lo hace especial: materiales, tamaño, origen…"<?= aria_error($errores, 'descripcion') ?>><?= e($datos['descripcion']) ?></textarea>
    <?= error_campo($errores, 'descripcion') ?>
  </fieldset>

  <fieldset class="paso">
    <legend><span class="paso-num">2</span> Precio y disponibilidad</legend>
    <div class="fila-2">
      <div>
        <label for="precio_base">Precio base (lo que recibes)</label>
        <div class="campo-prefijo"><span>$</span>
          <input id="precio_base" name="precio_base" inputmode="numeric" required value="<?= e($datos['precio_base']) ?>"
                 placeholder="25000"<?= aria_error($errores, 'precio_base') ?>>
        </div>
        <?= error_campo($errores, 'precio_base') ?>
      </div>
      <div>
        <label for="stock">Unidades disponibles</label>
        <input id="stock" name="stock" type="number" min="0" step="1" required value="<?= e($datos['stock']) ?>"<?= aria_error($errores, 'stock') ?>>
        <?= error_campo($errores, 'stock') ?>
      </div>
    </div>
    <div class="desglose" data-comision="<?= e($comision) ?>">
      <div><span>Precio base</span><span id="d-base">—</span></div>
      <div><span>Comisión de la plataforma (<?= e(rtrim(rtrim(number_format($comision, 2, ',', ''), '0'), ',')) ?> %)</span><span id="d-comision">—</span></div>
      <div class="desglose-total"><span>Precio que verá el cliente</span><span id="d-final">—</span></div>
    </div>

    <span class="etiqueta">Estado</span>
    <div class="opciones-radio">
      <label><input type="radio" name="estado" value="activo" <?= $datos['estado'] === 'activo' ? 'checked' : '' ?>> Activo: visible en el catálogo</label>
      <label><input type="radio" name="estado" value="inactivo" <?= $datos['estado'] === 'inactivo' ? 'checked' : '' ?>> Inactivo: oculto por ahora</label>
    </div>
    <?= error_campo($errores, 'estado') ?>
  </fieldset>

  <fieldset class="paso">
    <legend><span class="paso-num">3</span> Fotos</legend>
    <?php if ($imagenes): ?>
      <p class="nota">Marca las fotos que quieras quitar.</p>
      <div class="galeria-edicion">
        <?php foreach ($imagenes as $img): ?>
          <label class="miniatura">
            <img src="<?= e(url_imagen($img['url'])) ?>" alt="">
            <span><input type="checkbox" name="quitar_imagenes[]" value="<?= (int)$img['id_imagen'] ?>"> Quitar</span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <label class="zona-archivos" for="imagenes">
      <strong>Elige tus fotos</strong>
      <span>JPG, PNG o WebP · máximo 5 MB cada una · hasta <?= MAX_IMAGENES_PRODUCTO ?> fotos en total</span>
      <input id="imagenes" name="imagenes[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
    </label>
    <div id="vista-previa" class="galeria-edicion"></div>
    <?= error_campo($errores, 'imagenes') ?>
  </fieldset>

  <div class="acciones-form">
    <a class="btn btn-suave" href="index.php">Cancelar</a>
    <button class="btn btn-primario"><?= e($textoBoton) ?></button>
  </div>
</form>

<script>
(function () {
  const caja = document.querySelector('.desglose');
  const pct = parseFloat(caja.dataset.comision) || 0;
  const input = document.getElementById('precio_base');
  const fmt = n => '$ ' + Math.round(n).toLocaleString('es-CO');
  function actualizar() {
    const base = parseFloat(input.value.replace(/[.\s$]/g, '').replace(',', '.'));
    if (!base || base <= 0) {
      ['d-base', 'd-comision', 'd-final'].forEach(id => document.getElementById(id).textContent = '—');
      return;
    }
    const final = Math.round(base * (1 + pct / 100));
    document.getElementById('d-base').textContent = fmt(base);
    document.getElementById('d-comision').textContent = fmt(final - base);
    document.getElementById('d-final').textContent = fmt(final);
  }
  input.addEventListener('input', actualizar);
  actualizar();

  const archivos = document.getElementById('imagenes');
  const vista = document.getElementById('vista-previa');
  archivos.addEventListener('change', function () {
    vista.innerHTML = '';
    Array.from(archivos.files).forEach(f => {
      const fig = document.createElement('div');
      fig.className = 'miniatura';
      const img = document.createElement('img');
      img.src = URL.createObjectURL(f);
      img.alt = '';
      const txt = document.createElement('span');
      txt.textContent = f.size > 5 * 1024 * 1024 ? 'Pesa más de 5 MB' : 'Nueva';
      fig.append(img, txt);
      vista.append(fig);
    });
  });
})();
</script>
