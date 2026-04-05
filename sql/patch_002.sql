-- Patch 002: mismatches entre schema y código PHP
SET NAMES utf8mb4;

-- ── 1. categorias: renombrar activo→activa + agregar parent_id y orden ──────
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='esfero' AND TABLE_NAME='categorias' AND COLUMN_NAME='activa');
SET @sql = IF(@col=0, 'ALTER TABLE categorias CHANGE COLUMN activo activa TINYINT(1) NOT NULL DEFAULT 1', 'SELECT "activa ya existe" as info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='esfero' AND TABLE_NAME='categorias' AND COLUMN_NAME='parent_id');
SET @sql = IF(@col=0, 'ALTER TABLE categorias ADD COLUMN parent_id INT DEFAULT NULL AFTER id', 'SELECT "parent_id ya existe" as info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='esfero' AND TABLE_NAME='categorias' AND COLUMN_NAME='orden');
SET @sql = IF(@col=0, 'ALTER TABLE categorias ADD COLUMN orden INT NOT NULL DEFAULT 0', 'SELECT "orden ya existe" as info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. testimonios: renombrar nombre_display→nombre_autor ────────────────────
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='esfero' AND TABLE_NAME='testimonios' AND COLUMN_NAME='nombre_autor');
SET @sql = IF(@col=0, 'ALTER TABLE testimonios CHANGE COLUMN nombre_display nombre_autor VARCHAR(255) NOT NULL', 'SELECT "nombre_autor ya existe" as info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3. Fix encoding: re-insertar datos con utf8mb4 correcto ─────────────────
-- Testimonios (truncar y reinsertar)
TRUNCATE TABLE testimonios;
INSERT INTO testimonios (nombre_autor, titulo, contenido, tipo, calificacion, ubicacion, verificado, activo) VALUES
('Carlos M.',    'Excelente experiencia comprando',   'Compré un iPhone 12 en perfecto estado. El vendedor fue muy responsable y el producto llegó exactamente como se describía. ¡Totalmente recomendado!', 'comprador', 5, 'Guadalajara, Jalisco',        1, 1),
('Ana García',   'Vendí mi laptop rápidamente',       'Publiqué mi laptop y en menos de 3 días ya tenía comprador. El proceso fue muy sencillo y la plataforma me dio seguridad en todo momento.',        'vendedor',  5, 'Ciudad de México',             1, 1),
('Roberto L.',   'Gran plataforma de segunda mano',   'He comprado y vendido varios artículos. La protección al comprador es real y el equipo de soporte responde rápido. Mi marketplace favorito.',       'usuario',   5, 'Monterrey, Nuevo León',       1, 1),
('María Torres', 'Compra segura y rápida',            'Me daba miedo comprar en línea pero Esfero me dio toda la confianza. El sistema de verificación de vendedores es muy bueno.',                       'comprador', 4, 'Puebla, Puebla',               1, 1),
('Javier H.',    'Vendedor satisfecho',               'Llevo 6 meses vendiendo en Esfero y es la mejor decisión que tomé. Clientes serios y proceso de pago sin complicaciones.',                         'vendedor',  5, 'Tijuana, Baja California',    1, 1),
('Laura Ríos',   'Encontré lo que buscaba',           'Buscaba una consola específica y la encontré a buen precio. El vendedor fue honesto con el estado del producto. 100% recomendable.',               'comprador', 5, 'León, Guanajuato',            1, 1);

-- Guías (truncar y reinsertar)
TRUNCATE TABLE guias;
INSERT INTO guias (titulo, slug, descripcion_corta, contenido, categoria, imagen_url, autor_id, destacado, activo) VALUES
('Cómo vender de forma segura en Esfero', 'como-vender-seguro',      'Guía completa para nuevos vendedores: fotografías, descripción, precios y más.',        '<p>Vender en Esfero es sencillo. Sigue estos pasos para tener éxito.</p><h2>1. Toma buenas fotografías</h2><p>La primera impresión importa. Usa buena iluminación y muestra el producto desde varios ángulos.</p><h2>2. Describe con honestidad</h2><p>Menciona el estado real del producto, incluyendo cualquier defecto.</p><h2>3. Fija un precio justo</h2><p>Investiga precios similares en el mercado.</p>', 'vendedores',  'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=800', 1, 1, 1),
('Tips para comprar sin riesgos',         'tips-comprar-sin-riesgos', 'Todo lo que necesitas saber para hacer compras seguras en el marketplace.',              '<p>Comprar de segunda mano puede ser una excelente decisión si sigues estos consejos.</p><h2>Verifica al vendedor</h2><p>Revisa su historial de calificaciones y reseñas antes de comprar.</p><h2>Usa el sistema de pagos</h2><p>Nunca pagues fuera de la plataforma para estar protegido.</p>',                                                                                                                                                      'compradores', 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?w=800', 1, 1, 1),
('Cómo fotografiar tus productos',        'como-fotografiar-productos','Técnicas simples para tomar fotos profesionales con tu celular.',                       '<p>Las fotografías son tu mejor herramienta de venta. Aprende a tomarlas bien.</p><h2>Iluminación natural</h2><p>Usa luz de ventana en lugar del flash. Es más suave y muestra mejor los colores.</p><h2>Fondo limpio</h2><p>Un fondo blanco o neutro hace que el producto resalte más.</p>',                                                                                                                                                      'consejos',    'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=800', 1, 0, 1);

-- Categorías: actualizar orden
UPDATE categorias SET orden=1 WHERE slug='electronica';
UPDATE categorias SET orden=2 WHERE slug='ropa-moda';
UPDATE categorias SET orden=3 WHERE slug='hogar-jardin';
UPDATE categorias SET orden=4 WHERE slug='deportes';
UPDATE categorias SET orden=5 WHERE slug='libros';
UPDATE categorias SET orden=6 WHERE slug='juguetes';
UPDATE categorias SET orden=7 WHERE slug='vehiculos';
UPDATE categorias SET orden=8 WHERE slug='musica';

SELECT 'Patch 002 aplicado correctamente' as resultado;
