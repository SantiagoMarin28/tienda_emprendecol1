<?php
require_once __DIR__ . '/../includes/funciones.php';
exigir_post();

$id = (int)($_POST['id_emprendedor'] ?? 0);
foreach (emprendedores_disponibles() as $emp) {
    if ((int)$emp['id_emprendedor'] === $id) {
        $_SESSION['id_emprendedor'] = $id;
        flash('info', 'Ahora estás viendo los productos de ' . $emp['nombre_emprendimiento'] . '.');
        break;
    }
}
redirigir('index.php');
