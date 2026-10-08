<?php
/** CREATE: publicar un producto nuevo. */
require_once __DIR__ . '/../includes/productos.php';

$emp = emprendedor_actual();
$comision = porcentaje_comision();
$datos = ['nombre' => '', 'descripcion' => '', 'id_categoria' => '', 'precio_base' => '', 'stock' => '1', 'estado' => 'activo'];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $datos = datos_formulario($_POST);
    $errores = validar_producto($datos);
    $archivos = archivos_subidos();
    if ($errImg = validar_imagenes($archivos, 0)) {
        $errores['imagenes'] = $errImg;
    }

    if (!$errores) {
        $pdo = conexion();
        $creadas = [];
        try {
            // RNF-17: producto, imágenes y bitácora se guardan juntos o no se guarda nada.
            $pdo->beginTransaction();
            $st = $pdo->prepare(
                'INSERT INTO producto (id_emprendedor, id_categoria, nombre, descripcion, precio_base, stock, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([
                $emp['id_emprendedor'], (int)$datos['id_categoria'], $datos['nombre'],
                $datos['descripcion'] !== '' ? $datos['descripcion'] : null,
                precio_numerico($datos['precio_base']), (int)$datos['stock'], $datos['estado'],
            ]);
            $id = (int)$pdo->lastInsertId();
            $creadas = guardar_imagenes($id, $archivos);
            registrar_bitacora((int)$emp['id_usuario'], 'producto', $id, 'crear', null, $datos['estado'],
                'Precio base ' . cop(precio_numerico($datos['precio_base'])) . ', stock ' . (int)$datos['stock']);
            $pdo->commit();

            flash('exito', 'Publicaste «' . $datos['nombre'] . '». Los clientes lo verán a ' . cop(precio_final(precio_numerico($datos['precio_base']), $comision)) . '.');
            redirigir('index.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($creadas as $ruta) {
                @unlink($ruta);
            }
            $errores['general'] = 'No se pudo guardar el producto. Inténtalo de nuevo.';
            error_log('[crear producto] ' . $ex->getMessage());
        }
    }
}

$titulo = 'Nuevo producto';
$imagenes = [];
$accion = 'crear.php';
$textoBoton = 'Publicar producto';
require __DIR__ . '/../includes/header.php';
?>
<section class="encabezado-pagina">
  <div>
    <p class="sobretitulo"><a href="index.php">← Mis productos</a></p>
    <h1>Nuevo producto</h1>
  </div>
</section>
<?php if (isset($errores['general'])): ?><div class="alerta alerta-error"><?= e($errores['general']) ?></div><?php endif; ?>
<?php require __DIR__ . '/_formulario.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
