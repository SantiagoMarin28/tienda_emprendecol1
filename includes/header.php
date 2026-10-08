<?php
/** Encabezado común. Espera $titulo definido antes de incluirlo. */
$empActual = emprendedor_actual();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo ?? 'Mis productos') ?> · EmprendeCol</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="marca" href="../index.php"><span class="marca-sello">E</span>EmprendeCol</a>
    <nav class="nav">
      <a href="index.php">Mis productos</a>
      <a href="crear.php" class="btn btn-primario btn-sm">+ Nuevo producto</a>
    </nav>
    <form class="sesion-demo" method="post" action="cambiar_emprendedor.php">
      <?= campo_csrf() ?>
      <label for="emp">Emprendedor</label>
      <select id="emp" name="id_emprendedor" onchange="this.form.submit()">
        <?php foreach (emprendedores_disponibles() as $opcion): ?>
          <option value="<?= (int)$opcion['id_emprendedor'] ?>" <?= $opcion['id_emprendedor'] == $empActual['id_emprendedor'] ? 'selected' : '' ?>>
            <?= e($opcion['nombre_emprendimiento']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-sm">Cambiar</button></noscript>
    </form>
  </div>
</header>
<main class="contenedor">
<?php foreach (tomar_flash() as $f): ?>
  <div class="alerta alerta-<?= e($f['tipo']) ?>" role="status"><?= e($f['mensaje']) ?></div>
<?php endforeach; ?>
