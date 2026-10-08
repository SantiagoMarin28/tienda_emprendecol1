<?php
/**
 * Funciones compartidas: conexión, seguridad, formato y bitácora.
 */
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ------------------------------------------------------------------ */
/* Conexión (PDO con consultas preparadas: protege contra inyección SQL) */
/* ------------------------------------------------------------------ */
function conexion(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $ex) {
            http_response_code(500);
            echo '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #e5a3a3;border-radius:12px;background:#fff5f5">'
               . '<h2 style="margin-top:0">No se pudo conectar a la base de datos</h2>'
               . '<p>Revisa que MySQL esté iniciado en el panel de XAMPP, que hayas importado '
               . '<code>database/tienda_emprendecol.sql</code> y que los datos de <code>config/db.php</code> sean correctos.</p>'
               . '<p style="color:#888;font-size:13px">Detalle: ' . htmlspecialchars($ex->getMessage()) . '</p></div>';
            exit;
        }
    }
    return $pdo;
}

/* ------------------------------------------------------------------ */
/* Seguridad                                                           */
/* ------------------------------------------------------------------ */

/** Escapa texto para mostrarlo en HTML (protege contra XSS). */
function e($texto): string
{
    return htmlspecialchars((string)($texto ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Token CSRF de la sesión. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Detiene la petición si el token CSRF no coincide. */
function verificar_csrf(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(403);
        exit('La solicitud no es válida o la página expiró. Vuelve atrás, recarga e inténtalo de nuevo.');
    }
}

function exigir_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Método no permitido.');
    }
    verificar_csrf();
}

/* ------------------------------------------------------------------ */
/* Sesión simulada del emprendedor                                     */
/* El módulo de inicio de sesión aún no existe, así que se elige el    */
/* emprendedor desde la barra superior para probar el CRUD.            */
/* ------------------------------------------------------------------ */
function emprendedores_disponibles(): array
{
    return conexion()->query(
        "SELECT pe.id_emprendedor, pe.id_usuario, pe.nombre_emprendimiento
           FROM perfil_emprendedor pe
           JOIN usuario u ON u.id_usuario = pe.id_usuario
          WHERE u.estado = 'activo'
          ORDER BY pe.nombre_emprendimiento"
    )->fetchAll();
}

function emprendedor_actual(): array
{
    $lista = emprendedores_disponibles();
    if (!$lista) {
        exit('No hay emprendedores activos en la base de datos. Importa los datos de prueba.');
    }
    foreach ($lista as $emp) {
        if ((int)$emp['id_emprendedor'] === (int)($_SESSION['id_emprendedor'] ?? 0)) {
            return $emp;
        }
    }
    $_SESSION['id_emprendedor'] = (int)$lista[0]['id_emprendedor'];
    return $lista[0];
}

/* ------------------------------------------------------------------ */
/* Configuración y precios                                             */
/* ------------------------------------------------------------------ */
function obtener_config(string $clave, $defecto = null)
{
    $st = conexion()->prepare('SELECT valor FROM configuracion WHERE clave = ?');
    $st->execute([$clave]);
    $valor = $st->fetchColumn();
    return $valor === false ? $defecto : $valor;
}

function porcentaje_comision(): float
{
    return (float)obtener_config('porcentaje_comision', 0);
}

/** RF-08: precio final = precio base + porcentaje de comisión (en pesos, sin centavos). */
function precio_final(float $precioBase, ?float $porcentaje = null): float
{
    $porcentaje = $porcentaje ?? porcentaje_comision();
    return round($precioBase * (1 + $porcentaje / 100));
}

function cop($valor): string
{
    return '$ ' . number_format((float)$valor, 0, ',', '.');
}

/* ------------------------------------------------------------------ */
/* Bitácora (RF-33)                                                    */
/* ------------------------------------------------------------------ */
function registrar_bitacora(?int $idUsuario, string $entidad, ?int $idEntidad, string $accion,
                            ?string $estadoAnterior = null, ?string $estadoNuevo = null, ?string $detalle = null): void
{
    $st = conexion()->prepare(
        'INSERT INTO bitacora (id_usuario, entidad, id_entidad, accion, estado_anterior, estado_nuevo, detalle, ip)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute([$idUsuario, $entidad, $idEntidad, $accion, $estadoAnterior, $estadoNuevo, $detalle,
                  $_SERVER['REMOTE_ADDR'] ?? null]);
}

/* ------------------------------------------------------------------ */
/* Mensajes y navegación                                               */
/* ------------------------------------------------------------------ */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function tomar_flash(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function etiqueta_estado(string $estado): string
{
    $textos = ['activo' => 'Activo', 'inactivo' => 'Inactivo', 'eliminado' => 'Eliminado'];
    return '<span class="badge badge-' . e($estado) . '">' . e($textos[$estado] ?? $estado) . '</span>';
}
