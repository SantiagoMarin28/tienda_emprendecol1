<?php
/** UPDATE (estado): activar o desactivar un producto. */
require_once __DIR__ . '/../includes/productos.php';
exigir_post();

$emp = emprendedor_actual();
$id = (int)($_POST['id'] ?? 0);
$p = producto_del_emprendedor($id, (int)$emp['id_emprendedor']);
if (!$p) {
    flash('error', 'Ese producto no existe o no pertenece a tu emprendimiento.');
    redirigir('index.php');
}

$nuevo = $p['estado'] === 'activo' ? 'inactivo' : 'activo';
$pdo = conexion();
$pdo->beginTransaction();
$pdo->prepare('UPDATE producto SET estado = ? WHERE id_producto = ? AND id_emprendedor = ?')
    ->execute([$nuevo, $id, $emp['id_emprendedor']]);
registrar_bitacora((int)$emp['id_usuario'], 'producto', $id, $nuevo === 'activo' ? 'activar' : 'desactivar', $p['estado'], $nuevo);
$pdo->commit();

flash('exito', $nuevo === 'activo'
    ? '«' . $p['nombre'] . '» ya es visible en el catálogo.'
    : '«' . $p['nombre'] . '» quedó oculto. Puedes activarlo cuando quieras.');
redirigir('index.php');
