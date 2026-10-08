-- =====================================================================
--  Tienda_EmprendeCol - Base de datos
--  Motor: MySQL 8 / MariaDB 10.4+ (incluido en XAMPP)
--  Basado en el modelo entidad-relación corregido (20 tablas).
--
--  Cómo importarlo:
--    phpMyAdmin > Importar > seleccionar este archivo > Continuar
--    o por consola:  mysql -u root < tienda_emprendecol.sql
-- =====================================================================

DROP DATABASE IF EXISTS tienda_emprendecol;
CREATE DATABASE tienda_emprendecol
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE tienda_emprendecol;

SET NAMES utf8mb4;
SET time_zone = '-05:00';

-- ---------------------------------------------------------------------
-- 1. Cuentas y perfiles
-- ---------------------------------------------------------------------

CREATE TABLE usuario (
  id_usuario               INT AUTO_INCREMENT PRIMARY KEY,
  nombre                   VARCHAR(120)  NOT NULL,
  correo                   VARCHAR(150)  NOT NULL,
  contrasena_hash          VARCHAR(255)  NOT NULL,
  telefono                 VARCHAR(20)   NULL,
  correo_verificado        BOOLEAN       NOT NULL DEFAULT FALSE,
  es_admin                 BOOLEAN       NOT NULL DEFAULT FALSE,
  acepta_tratamiento_datos BOOLEAN       NOT NULL DEFAULT FALSE,
  fecha_consentimiento     DATETIME      NULL,
  estado                   ENUM('activo','suspendido','bloqueado') NOT NULL DEFAULT 'activo',
  fecha_registro           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_usuario_correo UNIQUE (correo)
) ENGINE=InnoDB;

CREATE TABLE token_usuario (
  id_token          INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario        INT          NOT NULL,
  tipo              ENUM('verificacion_correo','recuperacion_contrasena') NOT NULL,
  token_hash        VARCHAR(255) NOT NULL,
  fecha_expiracion  DATETIME     NOT NULL,
  usado             BOOLEAN      NOT NULL DEFAULT FALSE,
  CONSTRAINT fk_token_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE perfil_emprendedor (
  id_emprendedor        INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario            INT          NOT NULL,
  nombre_emprendimiento VARCHAR(120) NOT NULL,
  descripcion           TEXT         NULL,
  logo_url              VARCHAR(255) NULL,
  ciudad                VARCHAR(80)  NOT NULL,
  telefono_contacto     VARCHAR(20)  NULL,
  correo_contacto       VARCHAR(150) NULL,
  verificado            BOOLEAN      NOT NULL DEFAULT FALSE,
  estado                ENUM('activo','advertido','suspendido','bloqueado') NOT NULL DEFAULT 'activo',
  fecha_creacion        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_perfil_usuario UNIQUE (id_usuario),
  CONSTRAINT fk_perfil_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE direccion (
  id_direccion  INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario    INT          NOT NULL,
  destinatario  VARCHAR(120) NOT NULL,
  telefono      VARCHAR(20)  NOT NULL,
  direccion     VARCHAR(200) NOT NULL,
  ciudad        VARCHAR(80)  NOT NULL,
  departamento  VARCHAR(80)  NOT NULL,
  es_principal  BOOLEAN      NOT NULL DEFAULT FALSE,
  activa        BOOLEAN      NOT NULL DEFAULT TRUE,
  CONSTRAINT fk_direccion_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. Catálogo
-- ---------------------------------------------------------------------

CREATE TABLE categoria (
  id_categoria INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(80) NOT NULL,
  activa       BOOLEAN     NOT NULL DEFAULT TRUE,
  CONSTRAINT uk_categoria_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

CREATE TABLE producto (
  id_producto     INT AUTO_INCREMENT PRIMARY KEY,
  id_emprendedor  INT            NOT NULL,
  id_categoria    INT            NOT NULL,
  nombre          VARCHAR(120)   NOT NULL,
  descripcion     TEXT           NULL,
  precio_base     DECIMAL(12,2)  NOT NULL,
  stock           INT            NOT NULL DEFAULT 0,
  estado          ENUM('activo','inactivo','eliminado') NOT NULL DEFAULT 'activo',
  fecha_creacion  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT ck_producto_precio CHECK (precio_base > 0),
  CONSTRAINT ck_producto_stock  CHECK (stock >= 0),
  CONSTRAINT fk_producto_emprendedor FOREIGN KEY (id_emprendedor)
    REFERENCES perfil_emprendedor(id_emprendedor) ON DELETE RESTRICT,
  CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria)
    REFERENCES categoria(id_categoria) ON DELETE RESTRICT,
  INDEX idx_producto_estado (estado),
  INDEX idx_producto_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE imagen_producto (
  id_imagen   INT AUTO_INCREMENT PRIMARY KEY,
  id_producto INT          NOT NULL,
  url         VARCHAR(255) NOT NULL,
  orden       INT          NOT NULL DEFAULT 1,
  CONSTRAINT fk_imagen_producto FOREIGN KEY (id_producto)
    REFERENCES producto(id_producto) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. Carrito
-- ---------------------------------------------------------------------

CREATE TABLE carrito (
  id_carrito          INT AUTO_INCREMENT PRIMARY KEY,
  id_cliente          INT      NOT NULL,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uk_carrito_cliente UNIQUE (id_cliente),
  CONSTRAINT fk_carrito_cliente FOREIGN KEY (id_cliente)
    REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE item_carrito (
  id_item        INT AUTO_INCREMENT PRIMARY KEY,
  id_carrito     INT      NOT NULL,
  id_producto    INT      NOT NULL,
  cantidad       INT      NOT NULL DEFAULT 1,
  fecha_agregado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT ck_item_cantidad CHECK (cantidad > 0),
  CONSTRAINT uk_item_carrito_producto UNIQUE (id_carrito, id_producto),
  CONSTRAINT fk_item_carrito FOREIGN KEY (id_carrito)
    REFERENCES carrito(id_carrito) ON DELETE CASCADE,
  CONSTRAINT fk_item_producto FOREIGN KEY (id_producto)
    REFERENCES producto(id_producto) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. Pedidos, pagos y despachos
-- ---------------------------------------------------------------------

CREATE TABLE pedido (
  id_pedido             INT AUTO_INCREMENT PRIMARY KEY,
  id_cliente            INT           NOT NULL,
  id_emprendedor        INT           NOT NULL,
  id_direccion          INT           NULL,
  envio_destinatario    VARCHAR(120)  NOT NULL,
  envio_telefono        VARCHAR(20)   NOT NULL,
  envio_direccion       VARCHAR(200)  NOT NULL,
  envio_ciudad          VARCHAR(80)   NOT NULL,
  envio_departamento    VARCHAR(80)   NOT NULL,
  estado                ENUM('pendiente_pago','pagado','despachado','entregado',
                             'en_disputa','recibido','cancelado','reembolsado')
                        NOT NULL DEFAULT 'pendiente_pago',
  subtotal              DECIMAL(12,2) NOT NULL,
  comision_total        DECIMAL(12,2) NOT NULL,
  costo_envio           DECIMAL(12,2) NOT NULL DEFAULT 0,
  total                 DECIMAL(12,2) NOT NULL,
  fecha_creacion        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_limite_despacho DATETIME      NULL,
  fecha_confirmacion    DATETIME      NULL,
  tipo_confirmacion     ENUM('cliente','automatica') NULL,
  CONSTRAINT fk_pedido_cliente FOREIGN KEY (id_cliente)
    REFERENCES usuario(id_usuario) ON DELETE RESTRICT,
  CONSTRAINT fk_pedido_emprendedor FOREIGN KEY (id_emprendedor)
    REFERENCES perfil_emprendedor(id_emprendedor) ON DELETE RESTRICT,
  CONSTRAINT fk_pedido_direccion FOREIGN KEY (id_direccion)
    REFERENCES direccion(id_direccion) ON DELETE SET NULL,
  INDEX idx_pedido_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE detalle_pedido (
  id_detalle          INT AUTO_INCREMENT PRIMARY KEY,
  id_pedido           INT           NOT NULL,
  id_producto         INT           NOT NULL,
  nombre_producto     VARCHAR(120)  NOT NULL,
  cantidad            INT           NOT NULL,
  precio_base_unit    DECIMAL(12,2) NOT NULL,
  porcentaje_comision DECIMAL(5,2)  NOT NULL,
  precio_final_unit   DECIMAL(12,2) NOT NULL,
  CONSTRAINT ck_detalle_cantidad CHECK (cantidad > 0),
  CONSTRAINT fk_detalle_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedido(id_pedido) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto FOREIGN KEY (id_producto)
    REFERENCES producto(id_producto) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE pago (
  id_pago             INT AUTO_INCREMENT PRIMARY KEY,
  id_pedido           INT           NOT NULL,
  monto_total         DECIMAL(12,2) NOT NULL,
  comision            DECIMAL(12,2) NOT NULL,
  monto_emprendedor   DECIMAL(12,2) NOT NULL,
  metodo              VARCHAR(40)   NOT NULL,
  estado              ENUM('pendiente','retenido','congelado','liberado','reembolsado')
                      NOT NULL DEFAULT 'pendiente',
  referencia_pasarela VARCHAR(100)  NULL,
  fecha_pago          DATETIME      NULL,
  fecha_liberacion    DATETIME      NULL,
  fecha_reembolso     DATETIME      NULL,
  CONSTRAINT uk_pago_pedido UNIQUE (id_pedido),
  CONSTRAINT fk_pago_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedido(id_pedido) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE despacho (
  id_despacho         INT AUTO_INCREMENT PRIMARY KEY,
  id_pedido           INT          NOT NULL,
  transportadora      VARCHAR(80)  NOT NULL,
  numero_guia         VARCHAR(60)  NOT NULL,
  evidencia_url       VARCHAR(255) NOT NULL,
  comprobante_pdf_url VARCHAR(255) NULL,
  fecha_despacho      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_entrega       DATETIME     NULL,
  CONSTRAINT uk_despacho_pedido UNIQUE (id_pedido),
  CONSTRAINT fk_despacho_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedido(id_pedido) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE resena (
  id_resena    INT AUTO_INCREMENT PRIMARY KEY,
  id_pedido    INT      NOT NULL,
  calificacion TINYINT  NOT NULL,
  comentario   TEXT     NULL,
  fecha        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT ck_resena_calificacion CHECK (calificacion BETWEEN 1 AND 5),
  CONSTRAINT uk_resena_pedido UNIQUE (id_pedido),
  CONSTRAINT fk_resena_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedido(id_pedido) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. Reportes y sanciones
-- ---------------------------------------------------------------------

CREATE TABLE reporte (
  id_reporte            INT AUTO_INCREMENT PRIMARY KEY,
  id_pedido             INT          NOT NULL,
  id_admin              INT          NULL,
  motivo                ENUM('no_recibido','producto_distinto','producto_danado','fraude','otro') NOT NULL,
  descripcion           TEXT         NOT NULL,
  respuesta_emprendedor TEXT         NULL,
  estado                ENUM('abierto','en_revision','resuelto') NOT NULL DEFAULT 'abierto',
  tipo_resolucion       ENUM('reembolso','desestimado','sancion') NULL,
  observaciones         TEXT         NULL,
  fecha_creacion        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_resolucion      DATETIME     NULL,
  CONSTRAINT fk_reporte_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedido(id_pedido) ON DELETE RESTRICT,
  CONSTRAINT fk_reporte_admin FOREIGN KEY (id_admin)
    REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE evidencia_reporte (
  id_evidencia INT AUTO_INCREMENT PRIMARY KEY,
  id_reporte   INT          NOT NULL,
  url          VARCHAR(255) NOT NULL,
  fecha        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_evidencia_reporte FOREIGN KEY (id_reporte)
    REFERENCES reporte(id_reporte) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sancion (
  id_sancion     INT AUTO_INCREMENT PRIMARY KEY,
  id_emprendedor INT          NOT NULL,
  id_admin       INT          NOT NULL,
  id_reporte     INT          NULL,
  tipo           ENUM('advertencia','suspension','bloqueo') NOT NULL,
  motivo         TEXT         NOT NULL,
  fecha_inicio   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_fin      DATETIME     NULL,
  CONSTRAINT fk_sancion_emprendedor FOREIGN KEY (id_emprendedor)
    REFERENCES perfil_emprendedor(id_emprendedor) ON DELETE RESTRICT,
  CONSTRAINT fk_sancion_admin FOREIGN KEY (id_admin)
    REFERENCES usuario(id_usuario) ON DELETE RESTRICT,
  CONSTRAINT fk_sancion_reporte FOREIGN KEY (id_reporte)
    REFERENCES reporte(id_reporte) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. Notificaciones, configuración y bitácora
-- ---------------------------------------------------------------------

CREATE TABLE notificacion (
  id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario      INT          NOT NULL,
  id_pedido       INT          NULL,
  tipo            VARCHAR(40)  NOT NULL,
  mensaje         VARCHAR(255) NOT NULL,
  leida           BOOLEAN      NOT NULL DEFAULT FALSE,
  fecha           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notificacion_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario(id_usuario) ON DELETE CASCADE,
  CONSTRAINT fk_notificacion_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedido(id_pedido) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE configuracion (
  clave               VARCHAR(60)  PRIMARY KEY,
  valor               VARCHAR(255) NOT NULL,
  descripcion         VARCHAR(255) NULL,
  id_admin            INT          NULL,
  fecha_actualizacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_configuracion_admin FOREIGN KEY (id_admin)
    REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE bitacora (
  id_evento       BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_usuario      INT          NULL,
  entidad         VARCHAR(40)  NOT NULL,
  id_entidad      INT          NULL,
  accion          VARCHAR(40)  NOT NULL,
  estado_anterior VARCHAR(40)  NULL,
  estado_nuevo    VARCHAR(40)  NULL,
  detalle         TEXT         NULL,
  ip              VARCHAR(45)  NULL,
  fecha           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bitacora_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario(id_usuario) ON DELETE SET NULL,
  INDEX idx_bitacora_entidad (entidad, id_entidad)
) ENGINE=InnoDB;

-- RNF-19: la bitácora es de solo inserción.
DELIMITER //
CREATE TRIGGER trg_bitacora_no_update BEFORE UPDATE ON bitacora
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora no se puede modificar';
END//
CREATE TRIGGER trg_bitacora_no_delete BEFORE DELETE ON bitacora
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora no se puede eliminar';
END//
DELIMITER ;

-- =====================================================================
--  DATOS DE PRUEBA
--  Contraseña de todos los usuarios de prueba: Demo1234
-- =====================================================================

INSERT INTO usuario (id_usuario, nombre, correo, contrasena_hash, telefono, correo_verificado,
                     es_admin, acepta_tratamiento_datos, fecha_consentimiento) VALUES
 (1, 'Administrador',   'admin@emprendecol.co',  '$2y$10$dm3ushFtgXmjWbwr3E6qju864YmXp2IXdQH6tDzvyofjuxhu4xrL2', '3000000000', TRUE, TRUE,  TRUE, NOW()),
 (2, 'Laura Gómez',     'laura@correo.co',       '$2y$10$dm3ushFtgXmjWbwr3E6qju864YmXp2IXdQH6tDzvyofjuxhu4xrL2', '3001112233', TRUE, FALSE, TRUE, NOW()),
 (3, 'Andrés Restrepo', 'andres@correo.co',      '$2y$10$dm3ushFtgXmjWbwr3E6qju864YmXp2IXdQH6tDzvyofjuxhu4xrL2', '3014445566', TRUE, FALSE, TRUE, NOW()),
 (4, 'Camila Ruiz',     'camila@correo.co',      '$2y$10$dm3ushFtgXmjWbwr3E6qju864YmXp2IXdQH6tDzvyofjuxhu4xrL2', '3027778899', TRUE, FALSE, TRUE, NOW());

INSERT INTO perfil_emprendedor (id_emprendedor, id_usuario, nombre_emprendimiento, descripcion,
                                ciudad, telefono_contacto, correo_contacto, verificado) VALUES
 (1, 2, 'Café de la Montaña', 'Café de origen cultivado por familias del suroeste antioqueño.', 'Medellín', '3001112233', 'ventas@cafemontana.co', TRUE),
 (2, 3, 'Manos de Barro',     'Cerámica artesanal hecha a mano en el Carmen de Viboral.',       'El Carmen de Viboral', '3014445566', 'hola@manosdebarro.co', FALSE);

INSERT INTO direccion (id_direccion, id_usuario, destinatario, telefono, direccion, ciudad, departamento, es_principal) VALUES
 (1, 4, 'Camila Ruiz', '3027778899', 'Calle 50 # 45-20, Apto 301', 'Bello', 'Antioquia', TRUE);

INSERT INTO categoria (id_categoria, nombre) VALUES
 (1, 'Alimentos y bebidas'),
 (2, 'Artesanías'),
 (3, 'Ropa y accesorios'),
 (4, 'Belleza y cuidado personal'),
 (5, 'Hogar y decoración');

INSERT INTO configuracion (clave, valor, descripcion, id_admin) VALUES
 ('porcentaje_comision',          '8',  'Porcentaje que se suma al precio base de cada producto', 1),
 ('dias_limite_despacho',         '5',  'Días que tiene el emprendedor para despachar un pedido pagado', 1),
 ('dias_confirmacion_automatica', '7',  'Días tras la entrega para confirmar la recepción automáticamente', 1),
 ('dias_para_reportar',           '15', 'Días tras la entrega en los que el cliente puede reportar', 1);

INSERT INTO producto (id_producto, id_emprendedor, id_categoria, nombre, descripcion, precio_base, stock, estado) VALUES
 (1, 1, 1, 'Café especial 500 g',      'Café tostado medio, notas de panela y chocolate. Molido o en grano.', 32000, 40, 'activo'),
 (2, 1, 1, 'Café especial 250 g',      'Presentación pequeña del mismo café de origen.',                      18000, 25, 'activo'),
 (3, 1, 5, 'Kit de filtrado V60',      'Gotero, filtros y jarra de vidrio para preparar café de filtro.',      95000,  6, 'activo'),
 (4, 2, 2, 'Taza de cerámica pintada', 'Taza de 300 ml pintada a mano con motivos florales.',                  28000, 15, 'activo'),
 (5, 2, 5, 'Jarrón mediano',           'Jarrón de 25 cm esmaltado en tonos tierra.',                           64000,  4, 'activo'),
 (6, 2, 2, 'Juego de platos x4',       'Cuatro platos llanos pintados a mano.',                               120000,  0, 'inactivo');

-- Un pedido de ejemplo (permite probar que un producto con ventas se elimina de forma lógica)
INSERT INTO pedido (id_pedido, id_cliente, id_emprendedor, id_direccion, envio_destinatario, envio_telefono,
                    envio_direccion, envio_ciudad, envio_departamento, estado, subtotal, comision_total,
                    costo_envio, total, fecha_limite_despacho) VALUES
 (1, 4, 1, 1, 'Camila Ruiz', '3027778899', 'Calle 50 # 45-20, Apto 301', 'Bello', 'Antioquia',
  'pagado', 64000, 5120, 12000, 81120, DATE_ADD(NOW(), INTERVAL 5 DAY));

INSERT INTO detalle_pedido (id_pedido, id_producto, nombre_producto, cantidad, precio_base_unit,
                            porcentaje_comision, precio_final_unit) VALUES
 (1, 1, 'Café especial 500 g', 2, 32000, 8, 34560);

INSERT INTO pago (id_pedido, monto_total, comision, monto_emprendedor, metodo, estado,
                  referencia_pasarela, fecha_pago) VALUES
 (1, 81120, 5120, 76000, 'PSE', 'retenido', 'DEMO-0001', NOW());

INSERT INTO bitacora (id_usuario, entidad, id_entidad, accion, estado_anterior, estado_nuevo, detalle) VALUES
 (4, 'pedido', 1, 'pago_aprobado', 'pendiente_pago', 'pagado', 'Pago retenido en custodia (datos de prueba)');
