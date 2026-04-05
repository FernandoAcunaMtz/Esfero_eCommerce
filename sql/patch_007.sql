-- ============================================================
-- PATCH 007 — Tabla simulaciones_log
-- Sistema de simulación de procesos para el panel de admin
-- ============================================================

USE esfero;

CREATE TABLE IF NOT EXISTS simulaciones_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    admin_id      INT          NOT NULL,
    tipo          VARCHAR(50)  NOT NULL,
    parametros    JSON         NOT NULL,
    pasos         JSON         NOT NULL,
    resultado     ENUM('exitoso', 'fallido') NOT NULL,
    mensaje_final TEXT         NOT NULL,
    fecha         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin (admin_id),
    INDEX idx_tipo  (tipo),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
