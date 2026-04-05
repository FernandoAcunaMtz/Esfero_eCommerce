-- ═══════════════════════════════════════════════════════════════════════════
-- STORED PROCEDURE: responder_solicitud_ayuda
-- Permite a un administrador responder una solicitud de ayuda
-- ═══════════════════════════════════════════════════════════════════════════

DELIMITER //

DROP PROCEDURE IF EXISTS responder_solicitud_ayuda //

CREATE PROCEDURE responder_solicitud_ayuda(
    IN p_solicitud_id INT,
    IN p_admin_id INT,
    IN p_mensaje TEXT,
    IN p_es_interno BOOLEAN,
    IN p_nuevo_estado ENUM('pendiente', 'en_revision', 'respondida', 'cerrada', 'resuelta'),
    OUT p_respuesta_id INT
)
BEGIN
    DECLARE v_solicitud_existe INT;
    DECLARE v_es_admin INT;
    DECLARE v_estado_actual VARCHAR(50);
    
    -- Verificar que la solicitud existe
    SELECT COUNT(*), estado INTO v_solicitud_existe, v_estado_actual
    FROM ayuda_solicitudes
    WHERE id = p_solicitud_id;
    
    IF v_solicitud_existe = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La solicitud no existe';
    END IF;
    
    -- Verificar que el usuario es administrador
    SELECT COUNT(*) INTO v_es_admin
    FROM usuarios
    WHERE id = p_admin_id AND rol = 'admin' AND estado = 'activo';
    
    IF v_es_admin = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No tienes permisos para responder solicitudes';
    END IF;
    
    -- Validar mensaje
    IF p_mensaje IS NULL OR TRIM(p_mensaje) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El mensaje es requerido';
    END IF;
    
    -- Insertar respuesta
    INSERT INTO ayuda_respuestas (
        solicitud_id, admin_id, mensaje, es_interno
    ) VALUES (
        p_solicitud_id, p_admin_id, TRIM(p_mensaje), IFNULL(p_es_interno, FALSE)
    );
    
    SET p_respuesta_id = LAST_INSERT_ID();
    
    -- Actualizar estado de la solicitud si se proporciona un nuevo estado
    IF p_nuevo_estado IS NOT NULL THEN
        UPDATE ayuda_solicitudes
        SET estado = p_nuevo_estado,
            fecha_actualizacion = NOW(),
            fecha_respuesta = CASE 
                WHEN p_nuevo_estado = 'respondida' AND p_es_interno = FALSE THEN NOW()
                ELSE fecha_respuesta
            END
        WHERE id = p_solicitud_id;
    END IF;
    
END //

DELIMITER ;

