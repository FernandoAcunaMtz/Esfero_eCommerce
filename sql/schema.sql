-- ============================================================
-- ESFERO MARKETPLACE - Schema Completo
-- MySQL 8.0 | 16 tablas + stored procedures + datos de prueba
-- ============================================================

CREATE DATABASE IF NOT EXISTS esfero CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE esfero;

-- ============================================================
-- TABLAS
-- ============================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    nombre          VARCHAR(100) NOT NULL,
    apellidos       VARCHAR(100) DEFAULT '',
    telefono        VARCHAR(20)  DEFAULT '',
    rol             ENUM('usuario','admin') NOT NULL DEFAULT 'usuario',
    estado          ENUM('activo','inactivo','suspendido') NOT NULL DEFAULT 'activo',
    total_ventas    INT          NOT NULL DEFAULT 0,
    fecha_registro  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email  (email),
    INDEX idx_rol    (rol),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS perfiles (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id           INT NOT NULL UNIQUE,
    foto_perfil          VARCHAR(500) DEFAULT NULL,
    descripcion          TEXT         DEFAULT NULL,
    calificacion_promedio DECIMAL(3,2) DEFAULT 0.00,
    ubicacion_estado     VARCHAR(100) DEFAULT '',
    ubicacion_ciudad     VARCHAR(100) DEFAULT '',
    codigo_postal        VARCHAR(10)  DEFAULT '',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categorias (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    slug   VARCHAR(100) NOT NULL UNIQUE,
    icono  VARCHAR(50)  DEFAULT 'fa-tag',
    activo TINYINT(1)   NOT NULL DEFAULT 1,
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS productos (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    titulo            VARCHAR(255)    NOT NULL,
    descripcion       TEXT            DEFAULT NULL,
    precio            DECIMAL(10,2)   NOT NULL,
    precio_original   DECIMAL(10,2)   DEFAULT NULL,
    moneda            VARCHAR(10)     NOT NULL DEFAULT 'MXN',
    stock             INT             NOT NULL DEFAULT 1,
    estado_producto   ENUM('nuevo','excelente','bueno','regular','para_repuesto') NOT NULL DEFAULT 'bueno',
    categoria_id      INT             DEFAULT NULL,
    vendedor_id       INT             NOT NULL,
    activo            TINYINT(1)      NOT NULL DEFAULT 1,
    vendido           TINYINT(1)      NOT NULL DEFAULT 0,
    destacado         TINYINT(1)      NOT NULL DEFAULT 0,
    vistas            INT             NOT NULL DEFAULT 0,
    ventas_count      INT             NOT NULL DEFAULT 0,
    favoritos_count   INT             NOT NULL DEFAULT 0,
    ubicacion_estado  VARCHAR(100)    DEFAULT '',
    ubicacion_ciudad  VARCHAR(100)    DEFAULT '',
    fecha_publicacion   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME      DEFAULT NULL,
    fecha_vendido       DATETIME      DEFAULT NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (vendedor_id)  REFERENCES usuarios(id)   ON DELETE CASCADE,
    INDEX idx_activo    (activo),
    INDEX idx_vendido   (vendido),
    INDEX idx_destacado (destacado),
    INDEX idx_categoria (categoria_id),
    INDEX idx_vendedor  (vendedor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS imagenes_productos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT          NOT NULL,
    url_imagen  VARCHAR(500) NOT NULL,
    es_principal TINYINT(1)  NOT NULL DEFAULT 0,
    orden       INT          NOT NULL DEFAULT 0,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    INDEX idx_producto    (producto_id),
    INDEX idx_es_principal (producto_id, es_principal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS carrito (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT           NOT NULL,
    producto_id         INT           NOT NULL,
    cantidad            INT           NOT NULL DEFAULT 1,
    precio_momento      DECIMAL(10,2) NOT NULL,
    fecha_agregado      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuario_producto (usuario_id, producto_id),
    FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS favoritos (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT      NOT NULL,
    producto_id    INT      NOT NULL,
    fecha_agregado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuario_producto (usuario_id, producto_id),
    FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mensajes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    conversacion_id VARCHAR(64)  NOT NULL,
    remitente_id    INT          NOT NULL,
    destinatario_id INT          NOT NULL,
    producto_id     INT          DEFAULT NULL,
    mensaje         TEXT         NOT NULL,
    leido           TINYINT(1)   NOT NULL DEFAULT 0,
    fecha_envio     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (remitente_id)    REFERENCES usuarios(id)  ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES usuarios(id)  ON DELETE CASCADE,
    FOREIGN KEY (producto_id)     REFERENCES productos(id) ON DELETE SET NULL,
    INDEX idx_conversacion    (conversacion_id),
    INDEX idx_remitente       (remitente_id),
    INDEX idx_destinatario    (destinatario_id),
    INDEX idx_leido           (leido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ordenes (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    numero_orden            VARCHAR(50)   NOT NULL UNIQUE,
    comprador_id            INT           NOT NULL,
    vendedor_id             INT           NOT NULL,
    subtotal                DECIMAL(10,2) NOT NULL,
    envio                   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total                   DECIMAL(10,2) NOT NULL,
    direccion_envio         TEXT          DEFAULT NULL,
    ciudad_envio            VARCHAR(100)  DEFAULT NULL,
    estado_envio            VARCHAR(100)  DEFAULT NULL,
    codigo_postal_envio     VARCHAR(10)   DEFAULT NULL,
    telefono_envio          VARCHAR(20)   DEFAULT NULL,
    estado                  ENUM('pendiente','pago_confirmado','confirmada','enviada','entregada','cancelada') NOT NULL DEFAULT 'pendiente',
    estado_pago             ENUM('pendiente','completado','fallido','reembolsado') NOT NULL DEFAULT 'pendiente',
    paypal_order_id         VARCHAR(100)  DEFAULT NULL,
    paypal_payer_id         VARCHAR(100)  DEFAULT NULL,
    id_transaccion_paypal   VARCHAR(100)  DEFAULT NULL,
    fecha_creacion          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_pago              DATETIME      DEFAULT NULL,
    fecha_confirmacion      DATETIME      DEFAULT NULL,
    FOREIGN KEY (comprador_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (vendedor_id)  REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_comprador (comprador_id),
    INDEX idx_vendedor  (vendedor_id),
    INDEX idx_estado    (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orden_items (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    orden_id             INT           NOT NULL,
    producto_id          INT           DEFAULT NULL,
    cantidad             INT           NOT NULL DEFAULT 1,
    precio_unitario      DECIMAL(10,2) NOT NULL,
    subtotal             DECIMAL(10,2) NOT NULL,
    producto_titulo      VARCHAR(255)  NOT NULL,
    producto_descripcion TEXT          DEFAULT NULL,
    producto_imagen      VARCHAR(500)  DEFAULT NULL,
    FOREIGN KEY (orden_id)   REFERENCES ordenes(id)   ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL,
    INDEX idx_orden (orden_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS calificaciones (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    orden_id       INT           NOT NULL,
    producto_id    INT           DEFAULT NULL,
    calificador_id INT           NOT NULL,
    calificado_id  INT           NOT NULL,
    tipo           ENUM('vendedor','comprador','producto') NOT NULL DEFAULT 'vendedor',
    calificacion   TINYINT       NOT NULL CHECK (calificacion BETWEEN 1 AND 5),
    titulo         VARCHAR(255)  DEFAULT '',
    comentario     TEXT          DEFAULT NULL,
    aprobada       TINYINT(1)    NOT NULL DEFAULT 1,
    visible        TINYINT(1)    NOT NULL DEFAULT 1,
    fecha_creacion DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (orden_id)       REFERENCES ordenes(id)   ON DELETE CASCADE,
    FOREIGN KEY (producto_id)    REFERENCES productos(id) ON DELETE SET NULL,
    FOREIGN KEY (calificador_id) REFERENCES usuarios(id)  ON DELETE CASCADE,
    FOREIGN KEY (calificado_id)  REFERENCES usuarios(id)  ON DELETE CASCADE,
    INDEX idx_calificado (calificado_id),
    INDEX idx_tipo       (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ayuda_solicitudes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT          DEFAULT NULL,
    nombre         VARCHAR(255) NOT NULL,
    email          VARCHAR(255) NOT NULL,
    telefono       VARCHAR(20)  DEFAULT NULL,
    asunto         VARCHAR(500) NOT NULL,
    mensaje        TEXT         NOT NULL,
    categoria      ENUM('general','comprar','vender','envios','pagos','cuenta','seguridad','reporte','reembolso') NOT NULL DEFAULT 'general',
    prioridad      ENUM('baja','normal','alta','urgente') NOT NULL DEFAULT 'normal',
    numero_ticket  VARCHAR(20)  NOT NULL UNIQUE,
    estado         ENUM('pendiente','en_revision','respondida','cerrada','resuelta') NOT NULL DEFAULT 'pendiente',
    fecha_creacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre   DATETIME     DEFAULT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_estado   (estado),
    INDEX idx_prioridad (prioridad),
    INDEX idx_ticket   (numero_ticket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ayuda_respuestas (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id   INT      NOT NULL,
    admin_id       INT      DEFAULT NULL,
    mensaje        TEXT     NOT NULL,
    es_interno     TINYINT(1) NOT NULL DEFAULT 0,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitud_id) REFERENCES ayuda_solicitudes(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)     REFERENCES usuarios(id)           ON DELETE SET NULL,
    INDEX idx_solicitud (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ayuda_faqs (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    pregunta VARCHAR(500) NOT NULL,
    respuesta TEXT        NOT NULL,
    categoria VARCHAR(50) NOT NULL DEFAULT 'general',
    orden    INT          NOT NULL DEFAULT 0,
    activo   TINYINT(1)   NOT NULL DEFAULT 1,
    INDEX idx_categoria (categoria),
    INDEX idx_activo    (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reportes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reportador_id   INT          NOT NULL,
    reportado_id    INT          DEFAULT NULL,
    producto_id     INT          DEFAULT NULL,
    tipo            ENUM('usuario','producto','mensaje') NOT NULL DEFAULT 'producto',
    motivo          VARCHAR(255) NOT NULL,
    descripcion     TEXT         DEFAULT NULL,
    estado          ENUM('pendiente','revisado','resuelto','descartado') NOT NULL DEFAULT 'pendiente',
    fecha_creacion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion DATETIME    DEFAULT NULL,
    admin_id        INT          DEFAULT NULL,
    FOREIGN KEY (reportador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (reportado_id)  REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (producto_id)   REFERENCES productos(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id)      REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_estado (estado),
    INDEX idx_tipo   (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS testimonios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT DEFAULT NULL,
    nombre_display  VARCHAR(255)    NOT NULL,
    titulo          VARCHAR(255)    NOT NULL,
    contenido       TEXT            NOT NULL,
    tipo            ENUM('comprador','vendedor','usuario') NOT NULL DEFAULT 'usuario',
    calificacion    TINYINT(1)      NOT NULL DEFAULT 5,
    ubicacion       VARCHAR(100)    DEFAULT NULL,
    verificado      TINYINT(1)      NOT NULL DEFAULT 0,
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    fecha_creacion  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guias (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    titulo              VARCHAR(255)    NOT NULL,
    slug                VARCHAR(255)    NOT NULL UNIQUE,
    descripcion_corta   TEXT            DEFAULT NULL,
    contenido           TEXT            NOT NULL,
    categoria           VARCHAR(100)    NOT NULL DEFAULT 'general',
    imagen_url          VARCHAR(500)    DEFAULT NULL,
    autor_id            INT             DEFAULT NULL,
    destacado           TINYINT(1)      NOT NULL DEFAULT 0,
    activo              TINYINT(1)      NOT NULL DEFAULT 1,
    fecha_publicacion   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME        DEFAULT NULL,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_slug      (slug),
    INDEX idx_activo    (activo),
    INDEX idx_destacado (destacado),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STORED PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS crear_orden_desde_carrito;
DROP PROCEDURE IF EXISTS marcar_producto_vendido;
DROP PROCEDURE IF EXISTS actualizar_calificacion_vendedor;
DROP PROCEDURE IF EXISTS crear_solicitud_ayuda;
DROP PROCEDURE IF EXISTS responder_solicitud_ayuda;

DELIMITER //

CREATE PROCEDURE crear_orden_desde_carrito(
    IN p_comprador_id INT,
    IN p_vendedor_id INT,
    IN p_direccion_envio TEXT,
    IN p_ciudad_envio VARCHAR(100),
    IN p_estado_envio VARCHAR(100),
    IN p_codigo_postal_envio VARCHAR(10),
    IN p_telefono_envio VARCHAR(20),
    OUT p_orden_id INT,
    OUT p_numero_orden VARCHAR(50)
)
BEGIN
    DECLARE v_subtotal DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_envio    DECIMAL(10,2) DEFAULT 100.00;
    DECLARE v_total    DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_numero_orden VARCHAR(50);

    SELECT COALESCE(SUM(c.cantidad * c.precio_momento), 0) INTO v_subtotal
    FROM carrito c
    INNER JOIN productos p ON c.producto_id = p.id
    WHERE c.usuario_id = p_comprador_id
      AND p.vendedor_id = p_vendedor_id
      AND p.activo = TRUE
      AND p.vendido = FALSE;

    SET v_total = v_subtotal + v_envio;
    SET v_numero_orden = CONCAT('ESF-', YEAR(NOW()), '-', LPAD(FLOOR(RAND() * 999999), 6, '0'));

    INSERT INTO ordenes (
        numero_orden, comprador_id, vendedor_id,
        subtotal, envio, total,
        direccion_envio, ciudad_envio, estado_envio,
        codigo_postal_envio, telefono_envio,
        estado, estado_pago
    ) VALUES (
        v_numero_orden, p_comprador_id, p_vendedor_id,
        v_subtotal, v_envio, v_total,
        p_direccion_envio, p_ciudad_envio, p_estado_envio,
        p_codigo_postal_envio, p_telefono_envio,
        'pendiente', 'pendiente'
    );

    SET p_orden_id     = LAST_INSERT_ID();
    SET p_numero_orden = v_numero_orden;

    INSERT INTO orden_items (
        orden_id, producto_id, cantidad, precio_unitario, subtotal,
        producto_titulo, producto_descripcion, producto_imagen
    )
    SELECT
        p_orden_id,
        c.producto_id,
        c.cantidad,
        c.precio_momento,
        c.cantidad * c.precio_momento,
        p.titulo,
        p.descripcion,
        (SELECT url_imagen FROM imagenes_productos WHERE producto_id = p.id AND es_principal = 1 LIMIT 1)
    FROM carrito c
    INNER JOIN productos p ON c.producto_id = p.id
    WHERE c.usuario_id = p_comprador_id
      AND p.vendedor_id = p_vendedor_id;

    DELETE FROM carrito
    WHERE usuario_id = p_comprador_id
      AND producto_id IN (SELECT id FROM productos WHERE vendedor_id = p_vendedor_id);
END //

CREATE PROCEDURE marcar_producto_vendido(
    IN p_producto_id INT,
    IN p_orden_id    INT
)
BEGIN
    UPDATE productos
    SET vendido      = TRUE,
        activo       = FALSE,
        stock        = 0,
        fecha_vendido = NOW(),
        ventas_count = ventas_count + 1
    WHERE id = p_producto_id;

    UPDATE usuarios u
    INNER JOIN productos p ON u.id = p.vendedor_id
    SET u.total_ventas = u.total_ventas + 1
    WHERE p.id = p_producto_id;
END //

CREATE PROCEDURE actualizar_calificacion_vendedor(
    IN p_vendedor_id INT
)
BEGIN
    DECLARE v_promedio DECIMAL(3,2);

    SELECT COALESCE(AVG(calificacion), 0.00) INTO v_promedio
    FROM calificaciones
    WHERE calificado_id = p_vendedor_id
      AND tipo = 'vendedor'
      AND aprobada = TRUE;

    UPDATE perfiles
    SET calificacion_promedio = v_promedio
    WHERE usuario_id = p_vendedor_id;
END //

CREATE PROCEDURE crear_solicitud_ayuda(
    IN  p_usuario_id  INT,
    IN  p_nombre      VARCHAR(255),
    IN  p_email       VARCHAR(255),
    IN  p_telefono    VARCHAR(20),
    IN  p_asunto      VARCHAR(500),
    IN  p_mensaje     TEXT,
    IN  p_categoria   ENUM('general','comprar','vender','envios','pagos','cuenta','seguridad','reporte','reembolso'),
    OUT p_solicitud_id   INT,
    OUT p_numero_ticket  VARCHAR(20)
)
BEGIN
    DECLARE v_ticket  VARCHAR(20);
    DECLARE v_existe  INT DEFAULT 1;
    DECLARE v_prioridad ENUM('baja','normal','alta','urgente') DEFAULT 'normal';

    IF p_categoria IN ('reporte','reembolso') THEN
        SET v_prioridad = 'alta';
    ELSEIF p_categoria = 'seguridad' THEN
        SET v_prioridad = 'urgente';
    END IF;

    WHILE v_existe > 0 DO
        SET v_ticket = CONCAT('TK-', DATE_FORMAT(NOW(),'%Y%m%d'), '-', LPAD(FLOOR(RAND()*10000),4,'0'));
        SELECT COUNT(*) INTO v_existe FROM ayuda_solicitudes WHERE numero_ticket = v_ticket;
    END WHILE;

    INSERT INTO ayuda_solicitudes (
        usuario_id, nombre, email, telefono, asunto, mensaje,
        categoria, prioridad, numero_ticket, estado
    ) VALUES (
        p_usuario_id, TRIM(p_nombre), LOWER(TRIM(p_email)),
        NULLIF(TRIM(p_telefono),''),
        TRIM(p_asunto), TRIM(p_mensaje),
        IFNULL(p_categoria,'general'), v_prioridad, v_ticket, 'pendiente'
    );

    SET p_solicitud_id  = LAST_INSERT_ID();
    SET p_numero_ticket = v_ticket;

    IF p_usuario_id IS NOT NULL THEN
        UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = p_usuario_id;
    END IF;
END //

CREATE PROCEDURE responder_solicitud_ayuda(
    IN  p_solicitud_id  INT,
    IN  p_admin_id      INT,
    IN  p_mensaje       TEXT,
    IN  p_es_interno    BOOLEAN,
    IN  p_nuevo_estado  ENUM('pendiente','en_revision','respondida','cerrada','resuelta'),
    OUT p_respuesta_id  INT
)
BEGIN
    DECLARE v_existe INT;

    SELECT COUNT(*) INTO v_existe FROM ayuda_solicitudes WHERE id = p_solicitud_id;
    IF v_existe = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La solicitud no existe';
    END IF;

    INSERT INTO ayuda_respuestas (solicitud_id, admin_id, mensaje, es_interno)
    VALUES (p_solicitud_id, p_admin_id, TRIM(p_mensaje), p_es_interno);

    SET p_respuesta_id = LAST_INSERT_ID();

    UPDATE ayuda_solicitudes
    SET estado = p_nuevo_estado,
        fecha_cierre = IF(p_nuevo_estado IN ('cerrada','resuelta'), NOW(), NULL)
    WHERE id = p_solicitud_id;
END //

DELIMITER ;

-- ============================================================
-- DATOS DE PRUEBA
-- ============================================================

-- Categorías
INSERT INTO categorias (nombre, slug, icono) VALUES
('Electrónica',      'electronica',       'fa-laptop'),
('Ropa y Moda',      'ropa-moda',         'fa-tshirt'),
('Hogar y Jardín',   'hogar-jardin',      'fa-home'),
('Deportes',         'deportes',          'fa-futbol'),
('Libros',           'libros',            'fa-book'),
('Juguetes',         'juguetes',          'fa-gamepad'),
('Vehículos',        'vehiculos',         'fa-car'),
('Música',           'musica',            'fa-music');

-- Usuarios (passwords = "password123" hasheado con bcrypt)
-- Hash generado con bcrypt cost=12: $2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj/RK.s5uBom
INSERT INTO usuarios (email, password_hash, nombre, apellidos, telefono, rol, estado) VALUES
('admin@esfero.com',          '$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj/RK.s5uBom', 'Admin',   'Esfero',    '5512345678', 'admin',    'activo'),
('vendedor@esfero.com',       '$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj/RK.s5uBom', 'Carlos',  'Ramírez',   '5598765432', 'usuario', 'activo'),
('carlos.mendez@example.com', '$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj/RK.s5uBom', 'Carlos',  'Méndez',    '5511223344', 'usuario', 'activo'),
('maria.lopez@example.com',   '$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj/RK.s5uBom', 'María',   'López',     '5544332211', 'usuario', 'activo'),
('ana.vendedora@example.com', '$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj/RK.s5uBom', 'Ana',     'Martínez',  '5566778899', 'usuario', 'activo');

-- Perfiles
INSERT INTO perfiles (usuario_id, descripcion, ubicacion_estado, ubicacion_ciudad, calificacion_promedio) VALUES
(1, 'Administrador del sistema Esfero.',                                         'CDMX',           'Ciudad de México', 0.00),
(2, 'Vendedor de electrónica y gadgets. Envíos a toda la república.',            'Jalisco',        'Guadalajara',      4.80),
(3, 'Comprador frecuente. Siempre responsable con los pagos.',                   'Nuevo León',     'Monterrey',        0.00),
(4, 'Me encanta la moda y los accesorios vintage.',                              'Estado de México','Ecatepec',         0.00),
(5, 'Vendo ropa y artículos de moda en excelente estado.',                       'Jalisco',        'Zapopan',          4.50);

-- Productos
INSERT INTO productos (titulo, descripcion, precio, moneda, stock, estado_producto, categoria_id, vendedor_id, activo, vendido, destacado, ubicacion_estado, ubicacion_ciudad) VALUES
('iPhone 12 Pro Max 256GB',         'iPhone en perfecto estado, incluye cargador original y caja. Sin rayones ni golpes.',                                 14500.00, 'MXN', 1, 'nuevo',  1, 2, 1, 0, 1, 'Jalisco',         'Guadalajara'),
('MacBook Air M1 8GB 256GB',        'Laptop Apple M1 2020. Batería al 91%. Excelente para trabajo y estudio.',                                             16000.00, 'MXN', 1, 'bueno', 1, 2, 1, 0, 1, 'Jalisco',         'Guadalajara'),
('Tenis Nike Air Max 270 Talla 27', 'Tenis casi nuevos, usados solo 3 veces. Sin desgaste en suela.',                                                      1200.00, 'MXN', 1, 'nuevo',  2, 5, 1, 0, 0, 'Jalisco',         'Zapopan'),
('Vestido Floral Talla M',          'Vestido de verano en excelente estado. Lavado en seco una vez.',                                                        350.00, 'MXN', 1, 'bueno', 2, 5, 1, 0, 0, 'Jalisco',         'Zapopan'),
('Silla Gamer RGB Reclinable',      'Silla ergonómica con soporte lumbar. Perfecta para largas horas de trabajo o gaming.',                                 2800.00, 'MXN', 1, 'bueno', 3, 2, 1, 0, 1, 'Jalisco',         'Guadalajara'),
('Bicicleta de Montaña Rodada 26',  'Bicicleta Specialized en muy buen estado. Llantas nuevas, frenos revisados.',                                         3500.00, 'MXN', 1, 'bueno', 4, 2, 1, 0, 0, 'Jalisco',         'Guadalajara'),
('Harry Potter Colección Completa', 'Los 7 libros de la saga. Edición de bolsillo en español. Todos completos y sin rayones.',                               650.00, 'MXN', 1, 'bueno', 5, 5, 1, 0, 0, 'Jalisco',         'Zapopan'),
('PlayStation 5 + 2 Controles',     'PS5 edición disco. Incluye 2 controles DualSense y 3 juegos. Factura disponible.',                                    9500.00, 'MXN', 1, 'nuevo',  6, 2, 1, 0, 1, 'Jalisco',         'Guadalajara');

-- Imágenes de productos (usando placeholder profesional)
INSERT INTO imagenes_productos (producto_id, url_imagen, es_principal, orden) VALUES
(1, 'https://images.unsplash.com/photo-1592286927505-1def25115558?w=500', 1, 1),
(2, 'https://images.unsplash.com/photo-1611186871525-c7ab1c6f8b2c?w=500', 1, 1),
(3, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500',    1, 1),
(4, 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=500', 1, 1),
(5, 'https://images.unsplash.com/photo-1612198790114-5533c8b68478?w=500', 1, 1),
(6, 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?w=500', 1, 1),
(7, 'https://images.unsplash.com/photo-1507842217343-583bb7270b66?w=500', 1, 1),
(8, 'https://images.unsplash.com/photo-1606813907291-d86efa9b94db?w=500', 1, 1);

-- Testimonios de prueba
INSERT INTO testimonios (nombre_display, titulo, contenido, tipo, calificacion, ubicacion, verificado, activo) VALUES
('Carlos M.',    'Excelente experiencia comprando',    'Compré un iPhone 12 en perfecto estado. El vendedor fue muy responsable y el producto llegó exactamente como se describía. ¡Totalmente recomendado!', 'comprador', 5, 'Guadalajara, Jalisco',          1, 1),
('Ana García',   'Vendí mi laptop rápidamente',        'Publiqué mi laptop y en menos de 3 días ya tenía comprador. El proceso fue muy sencillo y la plataforma me dio seguridad en todo momento.', 'vendedor', 5, 'Ciudad de México',                1, 1),
('Roberto L.',   'Gran plataforma para segunda mano',  'He comprado y vendido varios artículos. La protección al comprador es real y el equipo de soporte responde rápido. Mi marketplace favorito en México.', 'usuario', 5, 'Monterrey, Nuevo León',       1, 1),
('María Torres', 'Compra segura y rápida',             'Me daba miedo comprar en línea pero Esfero me dio toda la confianza. El sistema de verificación de vendedores es muy bueno.', 'comprador', 4, 'Puebla, Puebla',                          1, 1),
('Javier H.',    'Vendedor satisfecho',                'Llevo 6 meses vendiendo en Esfero y es la mejor decisión que tomé. Clientes serios y proceso de pago sin complicaciones.', 'vendedor', 5, 'Tijuana, Baja California',               1, 1),
('Laura Ríos',   'Encontré lo que buscaba',            'Buscaba una consola específica y la encontré a buen precio. El vendedor fue honesto con el estado del producto. 100% recomendable.', 'comprador', 5, 'León, Guanajuato',              1, 1);

-- Guías de prueba
INSERT INTO guias (titulo, slug, descripcion_corta, contenido, categoria, imagen_url, autor_id, destacado, activo) VALUES
('Cómo vender de forma segura en Esfero', 'como-vender-seguro', 'Guía completa para nuevos vendedores: fotografías, descripción, precios y más.', '<p>Vender en Esfero es sencillo. Sigue estos pasos para tener éxito.</p><h2>1. Toma buenas fotografías</h2><p>La primera impresión importa. Usa buena iluminación y muestra el producto desde varios ángulos.</p><h2>2. Describe con honestidad</h2><p>Menciona el estado real del producto, incluyendo cualquier defecto.</p><h2>3. Fija un precio justo</h2><p>Investiga precios similares en el mercado.</p>', 'vendedores', 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=800', 1, 1, 1),
('Tips para comprar sin riesgos',         'tips-comprar-sin-riesgos', 'Todo lo que necesitas saber para hacer compras seguras en el marketplace.', '<p>Comprar de segunda mano puede ser una excelente decisión si sigues estos consejos.</p><h2>Verifica al vendedor</h2><p>Revisa su historial de calificaciones y reseñas antes de comprar.</p><h2>Usa el sistema de pagos</h2><p>Nunca pagues fuera de la plataforma para estar protegido.</p>', 'compradores', 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?w=800', 1, 1, 1),
('Cómo fotografiar tus productos',        'como-fotografiar-productos', 'Técnicas simples para tomar fotos profesionales con tu celular.', '<p>Las fotografías son tu mejor herramienta de venta. Aprende a tomarlas bien.</p><h2>Iluminación natural</h2><p>Usa luz de ventana en lugar del flash. Es más suave y muestra mejor los colores.</p><h2>Fondo limpio</h2><p>Un fondo blanco o neutro hace que el producto resalte más.</p>', 'consejos', 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=800', 1, 0, 1);

-- FAQs de ayuda
INSERT INTO ayuda_faqs (pregunta, respuesta, categoria, orden) VALUES
('¿Cómo publico un producto?',                'Ve a "Vender" en el menú, completa el formulario con título, descripción, precio y fotos.', 'vender',  1),
('¿Cómo realizo un pago?',                    'Aceptamos pagos seguros a través de PayPal. Selecciona los productos y ve al checkout.',     'pagos',   1),
('¿Cuánto demora el envío?',                  'Los tiempos de envío dependen del vendedor. Generalmente entre 3 y 7 días hábiles.',        'envios',  1),
('¿Puedo devolver un producto?',              'Sí, tienes 3 días hábiles después de recibir el producto para reportar cualquier problema.', 'comprar', 1),
('¿Cómo cambio mi contraseña?',               'Ve a "Mi Perfil" → "Configuración" y selecciona la opción de cambio de contraseña.',       'cuenta',  1),
('¿Cómo contacto al vendedor?',               'En la página de cada producto encontrarás el botón "Contactar Vendedor" para enviarle un mensaje.', 'comprar', 2);
