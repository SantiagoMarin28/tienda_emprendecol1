<?php
/**
 * Configuración de la conexión a la base de datos.
 *
 * Con XAMPP recién instalado el usuario es "root" y la contraseña está vacía.
 * Si cambiaron la contraseña de MySQL o el puerto, ajústenlos aquí.
 */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'tienda_emprendecol');
define('DB_USER', 'root');
define('DB_PASS', '');

// Imágenes de productos (RNF-23)
define('MAX_IMAGENES_PRODUCTO', 5);
define('MAX_TAMANO_IMAGEN', 5 * 1024 * 1024);   // 5 MB
define('ANCHO_MAX_IMAGEN', 1200);               // se reducen las más grandes
define('CARPETA_UPLOADS', __DIR__ . '/../uploads/productos/');
define('RUTA_UPLOADS', 'uploads/productos/');   // ruta relativa a la raíz del proyecto
