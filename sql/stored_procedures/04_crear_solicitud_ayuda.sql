-- ═══════════════════════════════════════════════════════════════════════════
-- STORED PROCEDURE: crear_solicitud_ayuda
-- Crea una nueva solicitud de ayuda con validaciones y generación de ticket
-- ═══════════════════════════════════════════════════════════════════════════

DELIMITER //

DROP PROCEDURE IF EXISTS crear_solicitud_ayuda //

CREATE PROCEDURE crear_solicitud_ayuda(
    IN p_usuario_id INT,
    IN p_nombre VARCHAR(255),
    IN p_email VARCHAR(255),
    IN p_telefono VARCHAR(20),
    IN p_asunto VARCHAR(500),
    IN p_mensaje TEXT,
    IN p_categoria ENUM('general', 'comprar', 'vender', 'envios', 'pagos', 'cuenta', 'seguridad', 'reporte', 'reembolso'),
    OUT p_solicitud_id INT,
    OUT p_numero_ticket VARCHAR(20)
)
BEGIN
    DECLARE v_numero_ticket VARCHAR(20);
    DECLARE v_existe INT DEFAULT 1;
    DECLARE v_prioridad ENUM('baja', 'normal', 'alta', 'urgente') DEFAULT 'normal';
    
    -- Validaciones básicas
    IF p_nombre IS NULL OR TRIM(p_nombre) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El nombre es requerido';
    END IF;
    
    IF p_email IS NULL OR p_email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El email es inválido';
    END IF;
    
    IF p_asunto IS NULL OR TRIM(p_asunto) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El asunto es requerido';
    END IF;
    
    IF p_mensaje IS NULL OR TRIM(p_mensaje) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El mensaje es requerido';
    END IF;
    
    -- Determinar prioridad según categoría
    IF p_categoria IN ('reporte', 'reembolso') THEN
        SET v_prioridad = 'alta';
    ELSEIF p_categoria = 'seguridad' THEN
        SET v_prioridad = 'urgente';
    ELSE
        SET v_prioridad = 'normal';
    END IF;
    
    -- Generar número de ticket único
    WHILE v_existe > 0 DO
        SET v_numero_ticket = CONCAT('TK-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(FLOOR(RAND() * 10000), 4, '0'));
        SELECT COUNT(*) INTO v_existe FROM ayuda_solicitudes WHERE numero_ticket = v_numero_ticket;
    END WHILE;
    
    -- Insertar solicitud
    INSERT INTO ayuda_solicitudes (
        usuario_id, nombre, email, telefono, asunto, mensaje, 
        categoria, prioridad, numero_ticket, estado
    ) VALUES (
        p_usuario_id, 
        TRIM(p_nombre), 
        LOWER(TRIM(p_email)), 
        IFNULL(TRIM(p_telefono), NULL),
        TRIM(p_asunto), 
        TRIM(p_mensaje), 
        IFNULL(p_categoria, 'general'),
        v_prioridad,
        v_numero_ticket,
        'pendiente'
    );
    
    -- Obtener ID de la solicitud creada
    SET p_solicitud_id = LAST_INSERT_ID();
    SET p_numero_ticket = v_numero_ticket;
    
    -- Si el usuario está logueado, actualizar última actividad
    IF p_usuario_id IS NOT NULL THEN
        UPDATE usuarios 
        SET ultimo_acceso = NOW() 
        WHERE id = p_usuario_id;
    END IF;
    
END //

DELIMITER ;

