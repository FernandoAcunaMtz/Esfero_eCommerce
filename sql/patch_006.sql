-- Patch 006: fix encoding nombres de usuarios seed
SET NAMES utf8mb4;
UPDATE usuarios SET nombre='Admin',     apellidos='Esfero'    WHERE email='admin@esfero.com';
UPDATE usuarios SET nombre='Carlos',    apellidos='Ramírez'   WHERE email='vendedor@esfero.com';
UPDATE usuarios SET nombre='Carlos',    apellidos='Méndez'    WHERE email='carlos.mendez@example.com';
UPDATE usuarios SET nombre='María',     apellidos='López'     WHERE email='maria.lopez@example.com';
UPDATE usuarios SET nombre='Ana',       apellidos='Martínez'  WHERE email='ana.vendedora@example.com';
SELECT email, nombre, apellidos FROM usuarios;
SELECT 'Patch 006 OK' as resultado;
