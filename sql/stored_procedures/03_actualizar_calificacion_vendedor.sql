-- ═══════════════════════════════════════════════════════════════════════════
-- STORED PROCEDURE: actualizar_calificacion_vendedor
-- ═══════════════════════════════════════════════════════════════════════════
-- Descripción: Recalcula y actualiza la calificación promedio de un vendedor
-- Parámetros:
--   - p_vendedor_id: ID del vendedor a actualizar
-- ═══════════════════════════════════════════════════════════════════════════

USE esfero;

DROP PROCEDURE IF EXISTS actualizar_calificacion_vendedor;

DELIMITER //

CREATE PROCEDURE actualizar_calificacion_vendedor(
    IN p_vendedor_id INT
)
BEGIN
    DECLARE v_promedio DECIMAL(3,2);
    
    -- Calcular promedio de calificaciones
    -- Solo cuenta calificaciones aprobadas y visibles de tipo 'vendedor'
    SELECT COALESCE(AVG(calificacion), 0.00) INTO v_promedio
    FROM calificaciones
    WHERE calificado_id = p_vendedor_id 
    AND tipo = 'vendedor'
    AND aprobada = TRUE
    AND visible = TRUE;
    
    -- Actualizar calificación del vendedor en la tabla usuarios
    UPDATE usuarios 
    SET calificacion_vendedor = v_promedio
    WHERE id = p_vendedor_id;
    
END //

DELIMITER ;

-- ═══════════════════════════════════════════════════════════════════════════
-- Verificación del stored procedure
-- ═══════════════════════════════════════════════════════════════════════════
SELECT 'Stored procedure actualizar_calificacion_vendedor creado exitosamente' AS mensaje;

