<?php
/**
 * DELETE: eliminar un producto.
 * - Si nunca se ha vendido, se borra de la base de datos junto con sus fotos.
 * - Si aparece en algún pedido, se hace borrado lógico (estado = 'eliminado')
 *   para que el historial de pedidos siga intacto (RF-07).
 */
require_once __DIR__ . '/../includes/productos.php';
exigir_post();

$emp = emprendedor_actual();
$id = (int)($_POST['id'] ?? 0);
$p = producto_del_emprendedor($id, (int)$emp['id_emprendedor']);
if (!$p) {
    flash('error', 'Ese producto no existe o no pertenece a tu emprendimiento.');
    redirigir('index.php');
}

$pdo = conexion();
$st = $pdo->prepare('SELECT COUNT(*) FROM detalle_pedido WHERE id_producto = ?');
$st->execute([$id]);
$tieneVentas = (int)$st->fetchColumn() > 0;

try {
    $pdo->beginTransaction();
    if ($tieneVentas) {
        $pdo->prepare("UPDATE producto SET estado = 'eliminado' WHERE id_producto = ?")->execute([$id]);
        $pdo->prepare('DELETE FROM item_carrito WHERE id_producto = ?')->execute([$id]);
        registrar_bitacora((int)$emp['id_usuario'], 'producto', $id, 'eliminar_logico', $p['estado'], 'eliminado',
            'Tiene pedidos asociados; se conserva para el historial');
        $pdo->commit();
        flash('exito', 'Eliminaste «' . $p['nombre'] . '». Como tiene ventas, se conserva en el historial de pedidos pero ya no aparece en tu catálogo.');
    } else {
        $urls = array_column(imagenes_producto($id), 'url');
        // imagen_producto e item_carrito se borran en cascada
        $pdo->prepare('DELETE FROM producto WHERE id_producto = ?')->execute([$id]);
        registrar_bitacora((int)$emp['id_usuario'], 'producto', $id, 'eliminar', $p['estado'], null,
            'Producto «' . $p['nombre'] . '» borrado definitivamente');
        $pdo->commit();
        foreach ($urls as $url) {
            borrar_archivo_imagen($url);
        }
        flash('exito', 'Eliminaste «' . $p['nombre'] . '».');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[eliminar producto] ' . $ex->getMessage());
    flash('error', 'No se pudo eliminar el producto. Inténtalo de nuevo.');
}
redirigir('index.php');
