-- ============================================================
-- PATCH 008 — Coherencia de rol usuario (comprador/vendedor)
-- Compatible con MySQL 8.0 (cualquier versión minor)
-- ============================================================

USE esfero;

-- ── 1. Columna puede_vender ──────────────────────────────────────────────────
-- Usamos un procedure para verificar antes de ADD COLUMN (compatible con 8.0 < 8.0.29)
DROP PROCEDURE IF EXISTS patch008_add_cols;
DELIMITER $$
CREATE PROCEDURE patch008_add_cols()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME  = 'usuarios'
          AND COLUMN_NAME = 'puede_vender'
    ) THEN
        ALTER TABLE usuarios
            ADD COLUMN puede_vender TINYINT(1) NOT NULL DEFAULT 0
            COMMENT '1 = activó cuenta de vendedor';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME  = 'usuarios'
          AND COLUMN_NAME = 'telefono_verificado'
    ) THEN
        ALTER TABLE usuarios
            ADD COLUMN telefono_verificado TINYINT(1) NOT NULL DEFAULT 0
            COMMENT '1 = teléfono verificado';
    END IF;
END$$
DELIMITER ;

CALL patch008_add_cols();
DROP PROCEDURE IF EXISTS patch008_add_cols;

-- Usuarios con rol=vendedor o rol=admin ya pueden vender
UPDATE usuarios SET puede_vender = 1 WHERE rol IN ('vendedor', 'admin') AND puede_vender = 0;

-- ── 2. Rate limiting — intentos de login ────────────────────────────────────
CREATE TABLE IF NOT EXISTS intentos_login (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    ip               VARCHAR(45)  NOT NULL COMMENT 'IPv4 o IPv6',
    email            VARCHAR(255) NOT NULL,
    intentos         TINYINT      NOT NULL DEFAULT 1,
    bloqueado_hasta  DATETIME     DEFAULT NULL COMMENT 'NULL = no bloqueado',
    ultimo_intento   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ip_email (ip, email),
    INDEX idx_email (email),
    INDEX idx_ip    (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Solicitudes de activación de vendedor ────────────────────────────────
CREATE TABLE IF NOT EXISTS vendedor_solicitudes (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT          NOT NULL UNIQUE,
    descripcion      TEXT         DEFAULT NULL,
    acepto_terminos  TINYINT(1)   NOT NULL DEFAULT 0,
    estado           ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    nota_admin       VARCHAR(500) DEFAULT NULL,
    fecha_solicitud  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion DATETIME     DEFAULT NULL,
    admin_id         INT          DEFAULT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)   REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_estado (estado),
    INDEX idx_fecha  (fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Vista de estadísticas del vendedor ───────────────────────────────────
CREATE OR REPLACE VIEW v_vendedor_stats AS
SELECT
    u.id                                                                AS usuario_id,
    u.nombre,
    u.apellidos,
    u.fecha_registro,
    COALESCE(p.calificacion_promedio, 0)                                AS calificacion_promedio,
    COALESCE(p.descripcion, '')                                         AS descripcion,
    COALESCE(p.foto_perfil, '')                                         AS foto_perfil,
    u.telefono_verificado,
    (SELECT COUNT(*) FROM productos pr
     WHERE pr.vendedor_id = u.id AND pr.activo = 1 AND pr.vendido = 0)  AS productos_activos,
    (SELECT COUNT(*) FROM ordenes o
     WHERE o.vendedor_id = u.id AND o.estado_pago = 'completado')       AS total_ventas_completadas,
    (SELECT COUNT(*) FROM calificaciones c
     WHERE c.calificado_id = u.id AND c.tipo = 'vendedor' AND c.visible = 1) AS total_resenas,
    (SELECT
        CASE WHEN COUNT(*) = 0 THEN 100
        ELSE ROUND(
            SUM(CASE WHEN c2.calificacion >= 4 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 0
        ) END
     FROM calificaciones c2
     WHERE c2.calificado_id = u.id AND c2.tipo = 'vendedor' AND c2.visible = 1
    )                                                                   AS pct_positivo
FROM usuarios u
LEFT JOIN perfiles p ON p.usuario_id = u.id
WHERE u.estado = 'activo'
  AND (u.rol IN ('vendedor', 'admin') OR u.puede_vender = 1);
