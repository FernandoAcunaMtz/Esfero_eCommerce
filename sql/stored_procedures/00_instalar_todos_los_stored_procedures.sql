-- ═══════════════════════════════════════════════════════════════════════════
-- INSTALAR TODOS LOS STORED PROCEDURES - Esfero Marketplace
-- ═══════════════════════════════════════════════════════════════════════════
-- Este archivo instala los tres stored procedures necesarios para el sistema
-- Ejecutar en el orden especificado para asegurar que no haya dependencias
-- ═══════════════════════════════════════════════════════════════════════════

USE esfero;

-- ═══════════════════════════════════════════════════════════════════════════
-- 1. crear_orden_desde_carrito
-- ═══════════════════════════════════════════════════════════════════════════

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
    
    SET p_orden_id = LAST_INSERT_ID();
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
    AND producto_id IN (
        SELECT id FROM productos WHERE vendedor_id = p_vendedor_id
    );
    
END //

-- ═══════════════════════════════════════════════════════════════════════════
-- 2. marcar_producto_vendido
-- ═══════════════════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS marcar_producto_vendido;

CREATE PROCEDURE marcar_producto_vendido(
    IN p_producto_id INT,
    IN p_orden_id INT
)
BEGIN
    UPDATE productos 
    SET vendido = TRUE,
        activo = FALSE,
        stock = 0,
        fecha_vendido = NOW(),
        ventas_count = ventas_count + 1
    WHERE id = p_producto_id;
    
    UPDATE usuarios u
    INNER JOIN productos p ON u.id = p.vendedor_id
    SET u.total_ventas = u.total_ventas + 1
    WHERE p.id = p_producto_id;
    
END //

-- ═══════════════════════════════════════════════════════════════════════════
-- 3. actualizar_calificacion_vendedor
-- ═══════════════════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS actualizar_calificacion_vendedor;

CREATE PROCEDURE actualizar_calificacion_vendedor(
    IN p_vendedor_id INT
)
BEGIN
    DECLARE v_promedio DECIMAL(3,2);
    
    SELECT COALESCE(AVG(calificacion), 0.00) INTO v_promedio
    FROM calificaciones
    WHERE calificado_id = p_vendedor_id 
    AND tipo = 'vendedor'
    AND aprobada = TRUE
    AND visible = TRUE;
    
    UPDATE usuarios 
    SET calificacion_vendedor = v_promedio
    WHERE id = p_vendedor_id;
    
END //

DELIMITER ;

-- ═══════════════════════════════════════════════════════════════════════════
-- Verificación final
-- ═══════════════════════════════════════════════════════════════════════════

-- ═══════════════════════════════════════════════════════════════════════════
-- 4. crear_solicitud_ayuda
-- ═══════════════════════════════════════════════════════════════════════════

SOURCE sql/stored_procedures/04_crear_solicitud_ayuda.sql;

-- ═══════════════════════════════════════════════════════════════════════════
-- 5. responder_solicitud_ayuda
-- ═══════════════════════════════════════════════════════════════════════════

SOURCE sql/stored_procedures/05_responder_solicitud_ayuda.sql;

DELIMITER ;

-- ═══════════════════════════════════════════════════════════════════════════
-- Verificación final
-- ═══════════════════════════════════════════════════════════════════════════

SELECT '═══════════════════════════════════════════════════════════════' AS '';
SELECT '✅ TODOS LOS STORED PROCEDURES INSTALADOS EXITOSAMENTE' AS '';
SELECT '═══════════════════════════════════════════════════════════════' AS '';

SELECT 
    ROUTINE_NAME as 'Stored Procedure',
    ROUTINE_TYPE as 'Tipo',
    CREATED as 'Creado'
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = 'esfero'
AND ROUTINE_TYPE = 'PROCEDURE'
ORDER BY ROUTINE_NAME;

