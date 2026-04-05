-- Patch 004: columna descripcion en categorias + fix encoding FAQs
SET NAMES utf8mb4;

-- 1. Agregar descripcion a categorias si no existe
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='esfero' AND TABLE_NAME='categorias' AND COLUMN_NAME='descripcion');
SET @sql = IF(@col=0, 'ALTER TABLE categorias ADD COLUMN descripcion VARCHAR(255) DEFAULT NULL AFTER nombre', 'SELECT "descripcion ya existe" as info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Fix encoding FAQs: truncar y reinsertar
TRUNCATE TABLE ayuda_faqs;
INSERT INTO ayuda_faqs (pregunta, respuesta, categoria, orden, activo) VALUES
('¿Cómo publico un producto?',      'Ve a "Vender" en el menú, completa el formulario con título, descripción, precio y fotos.',      'vender',  1, 1),
('¿Cómo realizo un pago?',          'Aceptamos pagos seguros a través de PayPal. Selecciona los productos y ve al checkout.',          'pagos',   1, 1),
('¿Cuánto demora el envío?',        'Los tiempos de envío dependen del vendedor. Generalmente entre 3 y 7 días hábiles.',             'envios',  1, 1),
('¿Puedo devolver un producto?',    'Sí, tienes 3 días hábiles después de recibir el producto para reportar cualquier problema.',     'comprar', 1, 1),
('¿Cómo cambio mi contraseña?',     'Ve a "Mi Perfil" → "Configuración" y selecciona la opción de cambio de contraseña.',            'cuenta',  1, 1),
('¿Cómo contacto al vendedor?',     'En la página de cada producto encontrarás el botón "Contactar Vendedor" para enviarle un mensaje.', 'comprar', 2, 1);

SELECT 'Patch 004 OK' as resultado;
SELECT id, LEFT(pregunta, 40) as pregunta FROM ayuda_faqs;
