<?php
/**
 * Lógica del módulo de productos (RF-07, RF-08).
 */
require_once __DIR__ . '/funciones.php';

const ESTADOS_EDITABLES = ['activo', 'inactivo'];
const TIPOS_IMAGEN = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function categorias_activas(): array
{
    return conexion()->query('SELECT id_categoria, nombre FROM categoria WHERE activa = 1 ORDER BY nombre')->fetchAll();
}

/** Devuelve el producto si pertenece al emprendedor; si no, null (evita editar productos ajenos). */
function producto_del_emprendedor(int $idProducto, int $idEmprendedor): ?array
{
    $st = conexion()->prepare(
        "SELECT p.*, c.nombre AS categoria
           FROM producto p
           JOIN categoria c ON c.id_categoria = p.id_categoria
          WHERE p.id_producto = ? AND p.id_emprendedor = ? AND p.estado <> 'eliminado'"
    );
    $st->execute([$idProducto, $idEmprendedor]);
    return $st->fetch() ?: null;
}

function imagenes_producto(int $idProducto): array
{
    $st = conexion()->prepare('SELECT * FROM imagen_producto WHERE id_producto = ? ORDER BY orden, id_imagen');
    $st->execute([$idProducto]);
    return $st->fetchAll();
}

/** Toma los datos del formulario y los limpia. */
function datos_formulario(array $post): array
{
    return [
        'nombre'       => trim((string)($post['nombre'] ?? '')),
        'descripcion'  => trim((string)($post['descripcion'] ?? '')),
        'id_categoria' => (string)($post['id_categoria'] ?? ''),
        'precio_base'  => str_replace(['.', ' ', '$'], '', trim((string)($post['precio_base'] ?? ''))),
        'stock'        => trim((string)($post['stock'] ?? '')),
        'estado'       => (string)($post['estado'] ?? 'activo'),
    ];
}

/** Valida los datos (RNF-04, RNF-13: mensajes claros que dicen cómo corregir). */
function validar_producto(array $d): array
{
    $err = [];
    $largo = mb_strlen($d['nombre']);
    if ($largo < 3 || $largo > 120) {
        $err['nombre'] = 'Escribe un nombre de entre 3 y 120 caracteres.';
    }
    if (mb_strlen($d['descripcion']) > 2000) {
        $err['descripcion'] = 'La descripción puede tener máximo 2.000 caracteres.';
    }
    $ids = array_column(categorias_activas(), 'id_categoria');
    if (!in_array((int)$d['id_categoria'], array_map('intval', $ids), true)) {
        $err['id_categoria'] = 'Elige una categoría de la lista.';
    }
    if (!preg_match('/^\d+(,\d{1,2})?$/', $d['precio_base']) || (float)str_replace(',', '.', $d['precio_base']) <= 0) {
        $err['precio_base'] = 'Escribe el precio en pesos, solo números y mayor que cero (por ejemplo 25000).';
    } elseif ((float)str_replace(',', '.', $d['precio_base']) > 999999999) {
        $err['precio_base'] = 'El precio es demasiado alto. Revisa que no tenga ceros de más.';
    }
    if (!preg_match('/^\d+$/', $d['stock']) || (int)$d['stock'] > 100000) {
        $err['stock'] = 'Escribe la cantidad disponible como número entero, desde 0.';
    }
    if (!in_array($d['estado'], ESTADOS_EDITABLES, true)) {
        $err['estado'] = 'Elige si el producto queda activo o inactivo.';
    }
    return $err;
}

function precio_numerico(string $precio): float
{
    return (float)str_replace(',', '.', $precio);
}

/** Reordena $_FILES['imagenes'] (subida múltiple) a una lista de archivos. */
function archivos_subidos(string $campo = 'imagenes'): array
{
    if (empty($_FILES[$campo]) || !is_array($_FILES[$campo]['name'])) {
        return [];
    }
    $lista = [];
    foreach ($_FILES[$campo]['name'] as $i => $nombre) {
        if ($_FILES[$campo]['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $lista[] = [
            'name'     => $nombre,
            'tmp_name' => $_FILES[$campo]['tmp_name'][$i],
            'error'    => $_FILES[$campo]['error'][$i],
            'size'     => $_FILES[$campo]['size'][$i],
        ];
    }
    return $lista;
}

/** Revisa tamaño, tipo real y cantidad de las imágenes antes de guardar nada. */
function validar_imagenes(array $archivos, int $yaExistentes): ?string
{
    if ($yaExistentes + count($archivos) > MAX_IMAGENES_PRODUCTO) {
        return 'Un producto puede tener máximo ' . MAX_IMAGENES_PRODUCTO . ' fotos. Quita algunas e inténtalo de nuevo.';
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($archivos as $a) {
        if ($a['error'] === UPLOAD_ERR_INI_SIZE || $a['error'] === UPLOAD_ERR_FORM_SIZE || $a['size'] > MAX_TAMANO_IMAGEN) {
            return 'La foto "' . $a['name'] . '" pesa más de 5 MB. Usa una imagen más liviana.';
        }
        if ($a['error'] !== UPLOAD_ERR_OK) {
            return 'No se pudo subir la foto "' . $a['name'] . '". Inténtalo de nuevo.';
        }
        if (!isset(TIPOS_IMAGEN[$finfo->file($a['tmp_name'])])) {
            return 'La foto "' . $a['name'] . '" debe ser JPG, PNG o WebP.';
        }
    }
    return null;
}

/**
 * Guarda las imágenes en disco (fuera de la base de datos, RNF-23) y las registra.
 * Devuelve las rutas creadas para poder borrarlas si la transacción falla.
 */
function guardar_imagenes(int $idProducto, array $archivos): array
{
    $pdo = conexion();
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $st = $pdo->prepare('SELECT COALESCE(MAX(orden), 0) FROM imagen_producto WHERE id_producto = ?');
    $st->execute([$idProducto]);
    $orden = (int)$st->fetchColumn();
    $ins = $pdo->prepare('INSERT INTO imagen_producto (id_producto, url, orden) VALUES (?, ?, ?)');

    if (!is_dir(CARPETA_UPLOADS)) {
        mkdir(CARPETA_UPLOADS, 0775, true);
    }
    $creadas = [];
    foreach ($archivos as $a) {
        $ext = TIPOS_IMAGEN[$finfo->file($a['tmp_name'])];
        $nombre = 'p' . $idProducto . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destino = CARPETA_UPLOADS . $nombre;
        if (!optimizar_imagen($a['tmp_name'], $destino, $ext)) {
            if (!move_uploaded_file($a['tmp_name'], $destino) && !copy($a['tmp_name'], $destino)) {
                throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
            }
        }
        $creadas[] = $destino;
        $ins->execute([$idProducto, RUTA_UPLOADS . $nombre, ++$orden]);
    }
    return $creadas;
}

/** Reduce las imágenes muy grandes con GD. Si GD no está disponible, devuelve false y se guarda la original. */
function optimizar_imagen(string $origen, string $destino, string $ext): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }
    [$ancho, $alto] = @getimagesize($origen) ?: [0, 0];
    if ($ancho <= ANCHO_MAX_IMAGEN || $ancho === 0) {
        return false;
    }
    $img = match ($ext) {
        'jpg'  => @imagecreatefromjpeg($origen),
        'png'  => @imagecreatefrompng($origen),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($origen) : false,
    };
    if (!$img) {
        return false;
    }
    $nuevoAlto = (int)round($alto * ANCHO_MAX_IMAGEN / $ancho);
    $lienzo = imagecreatetruecolor(ANCHO_MAX_IMAGEN, $nuevoAlto);
    imagealphablending($lienzo, false);
    imagesavealpha($lienzo, true);
    imagecopyresampled($lienzo, $img, 0, 0, 0, 0, ANCHO_MAX_IMAGEN, $nuevoAlto, $ancho, $alto);
    $ok = match ($ext) {
        'jpg'  => imagejpeg($lienzo, $destino, 82),
        'png'  => imagepng($lienzo, $destino, 7),
        'webp' => imagewebp($lienzo, $destino, 80),
    };
    imagedestroy($img);
    imagedestroy($lienzo);
    return $ok;
}

/** Borra el archivo físico de una imagen (solo dentro de la carpeta de uploads). */
function borrar_archivo_imagen(string $url): void
{
    $ruta = CARPETA_UPLOADS . basename($url);
    if (is_file($ruta)) {
        @unlink($ruta);
    }
}

/** URL para mostrar una imagen desde una página dentro de /productos. */
function url_imagen(?string $url): string
{
    return $url ? '../' . $url : '../assets/img/sin-imagen.svg';
}
