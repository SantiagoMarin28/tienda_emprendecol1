<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tienda_EmprendeCol</title>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/estilos.css">
  <style>
    .inicio { max-width: 760px; margin: 64px auto; padding: 0 16px; }
    .inicio h1 { font-size: 2.4rem; }
    .inicio > p { color: var(--tinta-suave); font-size: 1.05rem; }
    .tarjetas { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 28px; }
    .tarjeta { background: #fff; border: 1px solid var(--borde); border-radius: 14px; padding: 22px; text-decoration: none; color: var(--tinta); box-shadow: var(--sombra); }
    .tarjeta:hover { border-color: var(--primario); }
    .tarjeta h2 { margin: 0 0 6px; font-size: 1.3rem; }
    .tarjeta p { margin: 0; color: var(--tinta-suave); font-size: .92rem; }
    @media (max-width: 640px) { .tarjetas { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="inicio">
    <p class="sobretitulo">Proyecto académico</p>
    <h1>Tienda_EmprendeCol</h1>
    <p>Marketplace que da visibilidad a emprendedores nuevos y protege al comprador con pago en custodia.</p>
    <div class="tarjetas">
      <a class="tarjeta" href="prototipo/index.html">
        <h2>Prototipo clicable →</h2>
        <p>Recorrido navegable por las pantallas de cliente, emprendedor y administrador.</p>
      </a>
      <a class="tarjeta" href="productos/index.php">
        <h2>CRUD de productos →</h2>
        <p>Módulo funcional conectado a MySQL: crear, ver, editar, activar y eliminar productos.</p>
      </a>
    </div>
  </div>
</body>
</html>
