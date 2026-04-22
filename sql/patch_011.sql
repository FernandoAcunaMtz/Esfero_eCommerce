-- Corregir encoding de nombres de categorías (idempotente)
-- Se ejecuta si los nombres quedaron corruptos por importación sin utf8mb4
SET NAMES utf8mb4;
UPDATE categorias SET nombre = 'Electrónica'   WHERE slug = 'electronica';
UPDATE categorias SET nombre = 'Ropa y Moda'   WHERE slug = 'ropa-moda';
UPDATE categorias SET nombre = 'Hogar y Jardín' WHERE slug = 'hogar-jardin';
UPDATE categorias SET nombre = 'Deportes'      WHERE slug = 'deportes';
UPDATE categorias SET nombre = 'Libros'        WHERE slug = 'libros';
UPDATE categorias SET nombre = 'Juguetes'      WHERE slug = 'juguetes';
UPDATE categorias SET nombre = 'Vehículos'     WHERE slug = 'vehiculos';
UPDATE categorias SET nombre = 'Música'        WHERE slug = 'musica';
