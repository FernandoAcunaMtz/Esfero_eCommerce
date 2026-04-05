-- ============================================================
-- PATCH 009 — Consolidación a dos roles: usuario / admin
-- Elimina 'cliente' y 'vendedor' como roles separados.
-- La capacidad de vender queda en el campo puede_vender=1.
-- Compatible con MySQL 8.0 (cualquier versión minor)
-- ============================================================

USE esfero;

-- ── 1. Ampliar el ENUM para incluir 'usuario' (sin romper datos existentes) ──
ALTER TABLE usuarios
    MODIFY COLUMN rol ENUM('cliente','vendedor','admin','usuario') NOT NULL DEFAULT 'cliente';

-- ── 2. Migrar datos ─────────────────────────────────────────────────────────

-- Los antiguos 'vendedor' activan el flag antes de cambiar rol
UPDATE usuarios
SET puede_vender = 1
WHERE rol = 'vendedor' AND puede_vender = 0;

-- Unificar 'cliente' y 'vendedor' en 'usuario'
UPDATE usuarios SET rol = 'usuario' WHERE rol IN ('cliente', 'vendedor');

-- ── 3. Reducir ENUM al conjunto final: solo usuario / admin ─────────────────
ALTER TABLE usuarios
    MODIFY COLUMN rol ENUM('usuario','admin') NOT NULL DEFAULT 'usuario';

-- ── 3. Actualizar v_vendedor_stats (usa rol IN ('vendedor','admin')) ────────
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
  AND (u.puede_vender = 1 OR u.rol = 'admin');
