-- Reemplazar URLs de Unsplash del seed inicial que retornan 404
-- Las imágenes del seed_productos.php son independientes y no se tocan
UPDATE imagenes_productos
SET url_imagen = 'https://placehold.co/500x500/044E65/ffffff?text=iPhone+12'
WHERE url_imagen = 'https://images.unsplash.com/photo-1592286927505-1def25115558?w=500';

UPDATE imagenes_productos
SET url_imagen = 'https://placehold.co/500x500/044E65/ffffff?text=PS5'
WHERE url_imagen = 'https://images.unsplash.com/photo-1611186871525-c7ab1c6f8b2c?w=500';

UPDATE imagenes_productos
SET url_imagen = 'https://placehold.co/500x500/0C9268/ffffff?text=Nike'
WHERE url_imagen = 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500';

UPDATE imagenes_productos
SET url_imagen = 'https://placehold.co/500x500/0D87A8/ffffff?text=Ropa'
WHERE url_imagen = 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=500';

UPDATE imagenes_productos
SET url_imagen = 'https://placehold.co/500x500/044E65/ffffff?text=Teclado'
WHERE url_imagen = 'https://images.unsplash.com/photo-1612198790114-5533c8b68478?w=500';

UPDATE imagenes_productos
SET url_imagen = 'https://placehold.co/500x500/0C9268/ffffff?text=Bicicleta'
WHERE url_imagen = 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?w=500';

UPDATE imagenes_productos
SET url_imagen = 'https://placehold.co/500x500/0D87A8/ffffff?text=Libros'
WHERE url_imagen = 'https://images.unsplash.com/photo-1507842217343-583bb7270b66?w=500';

UPDATE imagenes_productos
SET url_imagen = 'https://placehold.co/500x500/044E65/ffffff?text=Guitarra'
WHERE url_imagen = 'https://images.unsplash.com/photo-1606813907291-d86efa9b94db?w=500';
