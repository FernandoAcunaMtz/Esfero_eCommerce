-- Patch 001: columnas faltantes + tablas testimonios y guias
SET NAMES utf8mb4;

-- precio_original
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='esfero' AND TABLE_NAME='productos' AND COLUMN_NAME='precio_original');
SET @sql = IF(@col=0, 'ALTER TABLE productos ADD COLUMN precio_original DECIMAL(10,2) DEFAULT NULL AFTER precio', 'SELECT "precio_original ya existe" as info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- favoritos_count
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='esfero' AND TABLE_NAME='productos' AND COLUMN_NAME='favoritos_count');
SET @sql = IF(@col=0, 'ALTER TABLE productos ADD COLUMN favoritos_count INT NOT NULL DEFAULT 0 AFTER ventas_count', 'SELECT "favoritos_count ya existe" as info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fecha_actualizacion
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='esfero' AND TABLE_NAME='productos' AND COLUMN_NAME='fecha_actualizacion');
SET @sql = IF(@col=0, 'ALTER TABLE productos ADD COLUMN fecha_actualizacion DATETIME DEFAULT NULL AFTER fecha_publicacion', 'SELECT "fecha_actualizacion ya existe" as info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tabla testimonios
CREATE TABLE IF NOT EXISTS testimonios (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id      INT DEFAULT NULL,
  nombre_display  VARCHAR(255) NOT NULL,
  titulo          VARCHAR(255) NOT NULL,
  contenido       TEXT NOT NULL,
  tipo            ENUM('comprador','vendedor','usuario') NOT NULL DEFAULT 'usuario',
  calificacion    TINYINT(1) NOT NULL DEFAULT 5,
  ubicacion       VARCHAR(100) DEFAULT NULL,
  verificado      TINYINT(1) NOT NULL DEFAULT 0,
  activo          TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tabla guias
CREATE TABLE IF NOT EXISTS guias (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  titulo              VARCHAR(255) NOT NULL,
  slug                VARCHAR(255) NOT NULL UNIQUE,
  descripcion_corta   TEXT DEFAULT NULL,
  contenido           TEXT NOT NULL,
  categoria           VARCHAR(100) NOT NULL DEFAULT 'general',
  imagen_url          VARCHAR(500) DEFAULT NULL,
  autor_id            INT DEFAULT NULL,
  destacado           TINYINT(1) NOT NULL DEFAULT 0,
  activo              TINYINT(1) NOT NULL DEFAULT 1,
  fecha_publicacion   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME DEFAULT NULL,
  FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_slug      (slug),
  INDEX idx_activo    (activo),
  INDEX idx_destacado (destacado),
  INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: testimonios de prueba
INSERT IGNORE INTO testimonios (id, nombre_display, titulo, contenido, tipo, calificacion, ubicacion, verificado, activo) VALUES
(1, 'Carlos M.', 'Excelente experiencia comprando', 'Compré un iPhone 12 en perfecto estado. El vendedor fue muy responsable y el producto llegó exactamente como se describía. ¡Totalmente recomendado!', 'comprador', 5, 'Guadalajara, Jalisco', 1, 1),
(2, 'Ana García', 'Vendí mi laptop rápidamente', 'Publiqué mi laptop y en menos de 3 días ya tenía comprador. El proceso fue muy sencillo y la plataforma me dio seguridad en todo momento.', 'vendedor', 5, 'Ciudad de México', 1, 1),
(3, 'Roberto L.', 'Gran plataforma para segunda mano', 'He comprado y vendido varios artículos. La protección al comprador es real y el equipo de soporte responde rápido. Mi marketplace favorito en México.', 'usuario', 5, 'Monterrey, Nuevo León', 1, 1),
(4, 'María Torres', 'Compra segura y rápida', 'Me daba miedo comprar en línea pero Esfero me dio toda la confianza. El sistema de verificación de vendedores es muy bueno.', 'comprador', 4, 'Puebla, Puebla', 1, 1),
(5, 'Javier H.', 'Vendedor satisfecho', 'Llevo 6 meses vendiendo en Esfero y es la mejor decisión que tomé. Clientes serios y proceso de pago sin complicaciones.', 'vendedor', 5, 'Tijuana, Baja California', 1, 1),
(6, 'Laura Ríos', 'Encontré lo que buscaba', 'Buscaba una consola específica y la encontré a buen precio. El vendedor fue honesto con el estado del producto. 100% recomendable.', 'comprador', 5, 'León, Guanajuato', 1, 1);

-- Seed: guias de prueba
INSERT IGNORE INTO guias (id, titulo, slug, descripcion_corta, contenido, categoria, imagen_url, autor_id, destacado, activo) VALUES
(1, 'Cómo vender de forma segura en Esfero', 'como-vender-seguro', 'Guía completa para nuevos vendedores: fotografías, descripción, precios y más.', '<p>Vender en Esfero es sencillo. Sigue estos pasos para tener éxito.</p><h2>1. Toma buenas fotografías</h2><p>La primera impresión importa. Usa buena iluminación y muestra el producto desde varios ángulos.</p><h2>2. Describe con honestidad</h2><p>Menciona el estado real del producto, incluyendo cualquier defecto.</p><h2>3. Fija un precio justo</h2><p>Investiga precios similares en el mercado.</p>', 'vendedores', 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=800', 1, 1, 1),
(2, 'Tips para comprar sin riesgos', 'tips-comprar-sin-riesgos', 'Todo lo que necesitas saber para hacer compras seguras en el marketplace.', '<p>Comprar de segunda mano puede ser una excelente decisión si sigues estos consejos.</p><h2>Verifica al vendedor</h2><p>Revisa su historial de calificaciones y reseñas antes de comprar.</p><h2>Usa el sistema de pagos</h2><p>Nunca pagues fuera de la plataforma para estar protegido.</p>', 'compradores', 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?w=800', 1, 1, 1),
(3, 'Cómo fotografiar tus productos', 'como-fotografiar-productos', 'Técnicas simples para tomar fotos profesionales con tu celular.', '<p>Las fotografías son tu mejor herramienta de venta. Aprende a tomarlas bien.</p><h2>Iluminación natural</h2><p>Usa luz de ventana en lugar del flash. Es más suave y muestra mejor los colores.</p><h2>Fondo limpio</h2><p>Un fondo blanco o neutro hace que el producto resalte más.</p>', 'consejos', 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=800', 1, 0, 1);

SELECT 'Patch 001 aplicado correctamente' as resultado;
