# Tienda_EmprendeCol

Marketplace que da visibilidad a emprendedores nuevos y protege al comprador con pago en custodia.

Este paquete contiene:

| Carpeta | Qué es |
|---|---|
| `database/` | Script SQL con las 20 tablas del modelo entidad-relación y datos de prueba |
| `productos/` | CRUD funcional de productos en PHP, conectado a MySQL |
| `prototipo/` | Prototipo clicable en HTML (cliente, emprendedor y administrador) |
| `config/db.php` | Datos de conexión a la base de datos |
| `includes/` | Funciones compartidas (conexión, seguridad, bitácora, lógica de productos) |
| `assets/` | Estilos e imágenes |
| `uploads/productos/` | Aquí se guardan las fotos que suben los emprendedores |

---

## Instalación con XAMPP (Windows, macOS o Linux)

1. **Instala XAMPP** desde https://www.apachefriends.org (versión con PHP 8.0 o superior).
2. **Copia la carpeta** `tienda_emprendecol` dentro de `htdocs`:
   - Windows: `C:\xampp\htdocs\tienda_emprendecol`
   - macOS: `/Applications/XAMPP/htdocs/tienda_emprendecol`
3. Abre el **panel de control de XAMPP** y presiona **Start** en **Apache** y **MySQL**.
4. **Crea la base de datos**:
   - Entra a http://localhost/phpmyadmin
   - Pestaña **Importar** → **Seleccionar archivo** → `database/tienda_emprendecol.sql` → **Importar** (o **Continuar**).
   - Debe aparecer la base `tienda_emprendecol` con 20 tablas.
5. **Abre el proyecto**: http://localhost/tienda_emprendecol

> Si tu MySQL tiene contraseña o usa otro puerto, cámbialos en `config/db.php`.

### Para volver a los datos iniciales
Importa de nuevo `database/tienda_emprendecol.sql`. El script borra y vuelve a crear la base.

---

## Qué hace el CRUD de productos

Ruta: http://localhost/tienda_emprendecol/productos/

| Operación | Archivo | Detalle |
|---|---|---|
| **Listar** (Read) | `index.php` | Búsqueda, filtros por categoría y estado, orden, paginación y resumen (activos, inactivos, sin stock) |
| **Ver detalle** (Read) | `ver.php` | Fotos, desglose del precio, unidades vendidas e historial tomado de la bitácora |
| **Crear** (Create) | `crear.php` | Formulario en 3 pasos con cálculo del precio final en vivo y hasta 5 fotos |
| **Editar** (Update) | `editar.php` | Mismo formulario; permite quitar y agregar fotos |
| **Activar / desactivar** (Update) | `estado.php` | Oculta o muestra el producto en el catálogo |
| **Eliminar** (Delete) | `eliminar.php` | Si el producto **nunca se vendió**, se borra con sus fotos. Si **tiene pedidos**, se hace borrado lógico (`estado = 'eliminado'`) para no romper el historial |

### Requisitos que cubre

- **RF-07**: crear, editar, desactivar y eliminar productos (con borrado lógico cuando tienen ventas).
- **RF-08**: precio final = precio base + comisión. La comisión se lee de la tabla `configuracion`, no está fija en el código (RNF-21).
- **RF-33**: cada creación, edición, cambio de estado y eliminación queda en la tabla `bitacora`, que es de solo inserción (un *trigger* impide modificarla o borrarla, RNF-19).
- **RNF-04**: consultas preparadas con PDO (contra inyección SQL), `htmlspecialchars` en todas las salidas (contra XSS) y token CSRF en todos los formularios.
- **RNF-11**: publicar un producto toma 3 pasos.
- **RNF-12**: la interfaz se adapta a celular (la tabla se convierte en tarjetas).
- **RNF-13**: mensajes de error que dicen cómo corregir.
- **RNF-17**: producto, fotos y bitácora se guardan en una sola transacción.
- **RNF-23**: fotos JPG, PNG o WebP de máximo 5 MB, validadas por su tipo real, guardadas fuera de la base de datos y reducidas a 1200 px de ancho si son más grandes. La carpeta `uploads/` no permite ejecutar código.

### Sesión simulada

El inicio de sesión todavía no está construido, así que en la barra superior hay un selector **Emprendedor** para elegir con qué emprendimiento trabajar. El CRUD solo muestra y deja modificar los productos del emprendedor elegido. Cuando hagan el login, basta con guardar `$_SESSION['id_emprendedor']` al iniciar sesión y quitar el selector de `includes/header.php`.

### Datos de prueba

| Usuario | Correo | Rol |
|---|---|---|
| Administrador | admin@emprendecol.co | Administrador |
| Laura Gómez | laura@correo.co | Emprendedora (Café de la Montaña) |
| Andrés Restrepo | andres@correo.co | Emprendedor (Manos de Barro) |
| Camila Ruiz | camila@correo.co | Cliente |

Contraseña de todos: `Demo1234` (guardada con bcrypt).

El producto **Café especial 500 g** tiene un pedido de ejemplo: si lo eliminas, verás el borrado lógico.

---

## Prototipo clicable

Ruta: http://localhost/tienda_emprendecol/prototipo/ (también se abre con doble clic en `prototipo/index.html`, sin XAMPP).

Usa la barra flotante **Ver como** para cambiar entre cliente, emprendedor y administrador. Los datos son simulados y se reinician al recargar.

Recorridos sugeridos:

1. **Cliente**: catálogo → producto → carrito (se divide en un pedido por emprendedor) → pago → mis pedidos → pedido #1024 → *Sí, recibí mi pedido* → reseña.
2. **Cliente con problema**: pedido #1024 → *Tengo un problema* → reporte (el pago queda congelado).
3. **Emprendedor**: panel → *Despachar ahora* → transportadora, guía y foto → comprobante PDF.
4. **Emprendedor**: *Publicar producto* en 3 pasos.
5. **Administrador**: panel → reporte R-8 → versiones de ambas partes → resolver (reembolso, desestimar o sancionar).
6. **Administrador**: configuración de comisión, plazos y categorías; suspender o reactivar usuarios.

---

## Problemas comunes

| Mensaje | Solución |
|---|---|
| *No se pudo conectar a la base de datos* | Inicia MySQL en XAMPP e importa el `.sql`. Revisa usuario y contraseña en `config/db.php` |
| *Unknown database 'tienda_emprendecol'* | Falta importar `database/tienda_emprendecol.sql` |
| MySQL no inicia en XAMPP (puerto 3306 ocupado) | Cierra otro MySQL que tengas instalado o cambia el puerto en XAMPP y en `config/db.php` |
| Las fotos no suben | En `C:\xampp\php\php.ini` revisa `upload_max_filesize = 8M` y `post_max_size = 40M`, y reinicia Apache |
| *La solicitud no es válida o la página expiró* | Recarga la página e inténtalo de nuevo (el token de seguridad venció) |
