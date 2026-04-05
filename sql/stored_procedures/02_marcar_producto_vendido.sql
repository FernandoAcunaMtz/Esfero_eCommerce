-- ═══════════════════════════════════════════════════════════════════════════
-- STORED PROCEDURE: marcar_producto_vendido
-- ═══════════════════════════════════════════════════════════════════════════
-- Descripción: Marca un producto como vendido y actualiza métricas relacionadas
-- Parámetros:
--   - p_producto_id: ID del producto a marcar como vendido
--   - p_orden_id: ID de la orden asociada (para referencia)
-- ═══════════════════════════════════════════════════════════════════════════

USE esfero;

DROP PROCEDURE IF EXISTS marcar_producto_vendido;

DELIMITER //

CREATE PROCEDURE marcar_producto_vendido(
    IN p_producto_id INT,
    IN p_orden_id INT
)
BEGIN
    -- Actualizar producto: marcarlo como vendido, desactivar, poner stock en 0
    UPDATE productos 
    SET vendido = TRUE,
        activo = FALSE,
        stock = 0,
        fecha_vendido = NOW(),
        ventas_count = ventas_count + 1
    WHERE id = p_producto_id;
    
    -- Incrementar contador de ventas del vendedor
    UPDATE usuarios u
    INNER JOIN productos p ON u.id = p.vendedor_id
    SET u.total_ventas = u.total_ventas + 1
    WHERE p.id = p_producto_id;
    
END //

DELIMITER ;

-- ═══════════════════════════════════════════════════════════════════════════
-- Verificación del stored procedure
-- ═══════════════════════════════════════════════════════════════════════════
SELECT 'Stored procedure marcar_producto_vendido creado exitosamente' AS mensaje;

