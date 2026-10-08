<?php
/** UPDATE: editar un producto propio. */
require_once __DIR__ . '/../includes/productos.php';

$emp = emprendedor_actual();
$comision = porcentaje_comision();
$id = (int)($_GET['id'] ?? 0);
$producto = producto_del_emprendedor($id, (int)$emp['id_emprendedor']);
if (!$producto) {
    flash('error', 'Ese producto no existe o no pertenece a tu emprendimiento.');
    redirigir('index.php');
}

$imagenes = imagenes_producto($id);
$datos = [
    'nombre'       => $producto['nombre'],
    'descripcion'  => (string)$producto['descripcion'],
    'id_categoria' => (string)$producto['id_categoria'],
    'precio_base'  => fmod((float)$producto['precio_base'], 1.0) == 0
                        ? (string)(int)$producto['precio_base']
                        : str_replace('.', ',', $producto['precio_base']),
    'stock'        => (string)$producto['stock'],
    'estado'       => $producto['estado'],
];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $datos = datos_formulario($_POST);
    $errores = validar_producto($datos);

    // Solo se aceptan ids de imágenes de este producto
    $idsPropios = array_map('intval', array_column($imagenes, 'id_imagen'));
    $quitar = array_values(array_intersect($idsPropios, array_map('intval', (array)($_POST['quitar_imagenes'] ?? []))));
    $archivos = archivos_subidos();
    if ($errImg = validar_imagenes($archivos, count($imagenes) - count($quitar))) {
        $errores['imagenes'] = $errImg;
    }

    if (!$errores) {
        $pdo = conexion();
        $creadas = [];
        try {
            $pdo->beginTransaction();
            $nuevoPrecio = precio_numerico($datos['precio_base']);
            $st = $pdo->prepare(
                'UPDATE producto SET id_categoria = ?, nombre = ?, descripcion = ?, precio_base = ?, stock = ?, estado = ?
                  WHERE id_producto = ? AND id_emprendedor = ?'
            );
            $st->execute([
                (int)$datos['id_categoria'], $datos['nombre'],
                $datos['descripcion'] !== '' ? $datos['descripcion'] : null,
                $nuevoPrecio, (int)$datos['stock'], $datos['estado'], $id, $emp['id_emprendedor'],
            ]);

            $porBorrar = [];
            if ($quitar) {
                $marcas = implode(',', array_fill(0, count($quitar), '?'));
                $st = $pdo->prepare("SELECT url FROM imagen_producto WHERE id_producto = ? AND id_imagen IN ($marcas)");
                $st->execute(array_merge([$id], $quitar));
                $porBorrar = $st->fetchAll(PDO::FETCH_COLUMN);
                $st = $pdo->prepare("DELETE FROM imagen_producto WHERE id_producto = ? AND id_imagen IN ($marcas)");
                $st->execute(array_merge([$id], $quitar));
            }
            $creadas = guardar_imagenes($id, $archivos);

            // Detalle legible de lo que cambió
            $cambios = [];
            if ($producto['nombre'] !== $datos['nombre']) $cambios[] = 'nombre';
            if ((float)$producto['precio_base'] !== $nuevoPrecio) $cambios[] = 'precio ' . cop($producto['precio_base']) . ' → ' . cop($nuevoPrecio);
            if ((int)$producto['stock'] !== (int)$datos['stock']) $cambios[] = 'stock ' . $producto['stock'] . ' → ' . (int)$datos['stock'];
            if ((string)$producto['id_categoria'] !== $datos['id_categoria']) $cambios[] = 'categoría';
            if ((string)$producto['descripcion'] !== $datos['descripcion']) $cambios[] = 'descripción';
            if ($quitar) $cambios[] = count($quitar) . ' foto(s) quitada(s)';
            if ($archivos) $cambios[] = count($archivos) . ' foto(s) nueva(s)';
            registrar_bitacora((int)$emp['id_usuario'], 'producto', $id, 'editar',
                $producto['estado'], $datos['estado'], $cambios ? implode('; ', $cambios) : 'Sin cambios en los datos');
            $pdo->commit();

            foreach ($porBorrar as $url) {
                borrar_archivo_imagen($url);
            }
            flash('exito', 'Guardaste los cambios de «' . $datos['nombre'] . '».');
            redirigir('index.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($creadas as $ruta) {
                @unlink($ruta);
            }
            $errores['general'] = 'No se pudieron guardar los cambios. Inténtalo de nuevo.';
            error_log('[editar producto] ' . $ex->getMessage());
        }
    }
}

$titulo = 'Editar producto';
$accion = 'editar.php?id=' . $id;
$textoBoton = 'Guardar cambios';
require __DIR__ . '/../includes/header.php';
?>
<section class="encabezado-pagina">
  <div>
    <p class="sobretitulo"><a href="index.php">← Mis productos</a></p>
    <h1>Editar producto</h1>
  </div>
  <a class="btn btn-suave" href="ver.php?id=<?= $id ?>">Ver detalle</a>
</section>
<?php if (isset($errores['general'])): ?><div class="alerta alerta-error"><?= e($errores['general']) ?></div><?php endif; ?>
<?php require __DIR__ . '/_formulario.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
