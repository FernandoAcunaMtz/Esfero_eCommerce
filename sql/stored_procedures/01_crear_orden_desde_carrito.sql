-- ═══════════════════════════════════════════════════════════════════════════
-- STORED PROCEDURE: crear_orden_desde_carrito
-- ═══════════════════════════════════════════════════════════════════════════
-- Descripción: Crea una orden de compra desde el carrito del usuario
-- Parámetros:
--   - p_comprador_id: ID del usuario comprador
--   - p_vendedor_id: ID del vendedor
--   - p_direccion_envio: Dirección de envío
--   - p_ciudad_envio: Ciudad de envío
--   - p_estado_envio: Estado de envío
--   - p_codigo_postal_envio: Código postal
--   - p_telefono_envio: Teléfono de contacto
--   - p_orden_id (OUT): ID de la orden creada
--   - p_numero_orden (OUT): Número de orden generado
-- ═══════════════════════════════════════════════════════════════════════════

USE esfero;

DROP PROCEDURE IF EXISTS crear_orden_desde_carrito;

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
    DECLARE v_envio DECIMAL(10,2) DEFAULT 100.00;
    DECLARE v_total DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_numero_orden VARCHAR(50);
    
    -- Calcular subtotal del carrito
    SELECT COALESCE(SUM(c.cantidad * c.precio_momento), 0) INTO v_subtotal
    FROM carrito c
    INNER JOIN productos p ON c.producto_id = p.id
    WHERE c.usuario_id = p_comprador_id 
    AND p.vendedor_id = p_vendedor_id
    AND p.activo = TRUE 
    AND p.vendido = FALSE;
    
    -- Calcular total
    SET v_total = v_subtotal + v_envio;
    
    -- Generar número de orden único (formato: ESF-YYYY-XXXXXX)
    SET v_numero_orden = CONCAT('ESF-', YEAR(NOW()), '-', LPAD(FLOOR(RAND() * 999999), 6, '0'));
    
    -- Crear orden
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
    
    SET p_orden_id = LAST_INSERT_ID();
    SET p_numero_orden = v_numero_orden;
    
    -- Copiar items del carrito a orden_items
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
    
    -- Vaciar carrito (solo productos del vendedor actual)
    DELETE FROM carrito 
    WHERE usuario_id = p_comprador_id 
    AND producto_id IN (
        SELECT id FROM productos WHERE vendedor_id = p_vendedor_id
    );
    
END //

DELIMITER ;

-- ═══════════════════════════════════════════════════════════════════════════
-- Verificación del stored procedure
-- ═══════════════════════════════════════════════════════════════════════════
SELECT 'Stored procedure crear_orden_desde_carrito creado exitosamente' AS mensaje;

