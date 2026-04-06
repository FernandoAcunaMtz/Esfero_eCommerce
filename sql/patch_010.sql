-- ============================================================
-- Patch 010 — Sistema de notificaciones
-- Esfero Marketplace
-- ============================================================

CREATE TABLE IF NOT EXISTS notificaciones (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT          NOT NULL,
    tipo           ENUM('mensaje','orden','pago','resena','sistema') NOT NULL DEFAULT 'sistema',
    titulo         VARCHAR(255) NOT NULL,
    mensaje        TEXT         NOT NULL,
    icono          VARCHAR(60)  DEFAULT 'fas fa-bell',
    url            VARCHAR(500) DEFAULT NULL,
    leida          TINYINT(1)   NOT NULL DEFAULT 0,
    fecha_creacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_leida  (usuario_id, leida),
    INDEX idx_usuario_fecha  (usuario_id, fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
