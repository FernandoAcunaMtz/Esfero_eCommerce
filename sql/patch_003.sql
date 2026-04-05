-- Patch 003 v2: migrar estado_producto via VARCHAR temporal
SET NAMES utf8mb4;

-- 1. Soltar ENUM temporalmente → VARCHAR
ALTER TABLE productos MODIFY COLUMN estado_producto VARCHAR(20) NOT NULL DEFAULT 'bueno';

-- 2. Mapear valores viejos → nuevos
UPDATE productos SET estado_producto = 'nuevo'   WHERE estado_producto = 'como_nuevo';
UPDATE productos SET estado_producto = 'regular'  WHERE estado_producto = 'buen_estado';
UPDATE productos SET estado_producto = 'regular'  WHERE estado_producto = 'aceptable';

-- 3. Restaurar como ENUM con los valores correctos
ALTER TABLE productos MODIFY COLUMN estado_producto
    ENUM('nuevo','excelente','bueno','regular','para_repuesto')
    NOT NULL DEFAULT 'bueno';

-- 4. Afinar estados de los productos seed
UPDATE productos SET estado_producto = 'nuevo'  WHERE titulo LIKE '%iPhone%';
UPDATE productos SET estado_producto = 'nuevo'  WHERE titulo LIKE '%MacBook%';
UPDATE productos SET estado_producto = 'bueno'  WHERE titulo LIKE '%Tenis%';
UPDATE productos SET estado_producto = 'bueno'  WHERE titulo LIKE '%Vestido%';
UPDATE productos SET estado_producto = 'bueno'  WHERE titulo LIKE '%Silla%';
UPDATE productos SET estado_producto = 'bueno'  WHERE titulo LIKE '%Bicicleta%';
UPDATE productos SET estado_producto = 'bueno'  WHERE titulo LIKE '%Harry Potter%';
UPDATE productos SET estado_producto = 'nuevo'  WHERE titulo LIKE '%PlayStation%';

SELECT 'Patch 003 OK' as resultado;
SELECT id, titulo, estado_producto FROM productos ORDER BY id;
