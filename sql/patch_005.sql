-- Patch 005: reset passwords a hash PHP bcrypt de 'password123'
SET NAMES utf8mb4;
UPDATE usuarios
SET password_hash = '$2y$10$yPmiCGIjOGxzx06ZU6IyC.dThoqRo6zGZ5UvqalXIbbKBKrtg1y/K'
WHERE email IN (
    'admin@esfero.com',
    'vendedor@esfero.com',
    'carlos.mendez@example.com',
    'maria.lopez@example.com',
    'ana.vendedora@example.com'
);
SELECT email, LEFT(password_hash,7) as prefix FROM usuarios;
SELECT 'Patch 005 OK' as resultado;
