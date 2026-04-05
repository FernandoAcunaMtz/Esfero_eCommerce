<?php
session_start();
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/assets/db_direct.php';

// Bloquear acceso a favoritos para administradores (opcional, puedes quitarlo si quieres)
// if (is_admin()) {
//     $_SESSION['error_message'] = 'Los administradores no pueden acceder a favoritos.';
//     header('Location: admin_dashboard.php');
//     exit;
// }

require_login();

// Obtener usuario actual de la sesión (igual que carrito.php)
$session_user = get_session_user();
$user_id = $session_user['id'] ?? null;

$favoritos = [];

if ($user_id) {
    try {
        $conn = get_db_connection_php();

        // Consultar favoritos con información del producto (similar a carrito.php)
        $stmt = $conn->prepare("
            SELECT 
                f.id AS favorito_id,
                f.producto_id,
                f.fecha_agregado,
                p.titulo,
                p.descripcion,
                p.precio,
                p.moneda,
                p.stock,
                p.vendido,
                p.activo,
                p.estado_producto,
                p.vendedor_id,
                p.ubicacion_ciudad,
                p.ubicacion_estado,
                u.nombre AS vendedor_nombre,
                (
                    SELECT url_imagen 
                    FROM imagenes_productos 
                    WHERE producto_id = p.id AND es_principal = TRUE 
                    LIMIT 1
                ) AS imagen_principal
            FROM favoritos f
            INNER JOIN productos p ON f.producto_id = p.id
            LEFT JOIN usuarios u ON p.vendedor_id = u.id
            WHERE f.usuario_id = ?
            ORDER BY f.fecha_agregado DESC
        ");

        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $favoritos[] = $row;
        }

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log('Error al cargar favoritos: ' . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0D87A8">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Mis Favoritos - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <section style="padding: 6rem 0 4rem; background: #f8f9fa;">
        <div class="container">
            <h1 style="font-size: clamp(1.5rem, 4vw, 2.5rem); margin-bottom: 0.5rem;">Mis Favoritos</h1>
            <p style="color: #666; margin-bottom: 3rem; font-size: clamp(0.9rem, 2vw, 1rem);">Productos que te interesan</p>
            
            <!-- Grid de Productos Favoritos -->
            <div class="portfolio-grid" style="display: grid !important; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)) !important; gap: 1.5rem !important; width: 100% !important; min-height: 200px !important; visibility: visible !important;">
                <?php if (empty($favoritos)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #666;">
                        <i class="fas fa-heart" style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;"></i>
                        <p style="font-size: 1.2rem; margin-bottom: 1rem;">No tienes productos en favoritos aún</p>
                        <a href="catalogo.php" class="cta-button" style="display: inline-block;">Explorar productos</a>
                    </div>
                <?php else: ?>
                    <?php 
                    foreach ($favoritos as $producto): 
                        $imagen = !empty($producto['imagen_principal']) ? $producto['imagen_principal'] : 'https://placehold.co/400x400?text=Sin+imagen';
                        $precio_formateado = formatearPrecio($producto['precio'] ?? 0, $producto['moneda'] ?? 'MXN');
                        $ubicacion = !empty($producto['ubicacion_ciudad']) ? $producto['ubicacion_ciudad'] : (!empty($producto['ubicacion_estado']) ? $producto['ubicacion_estado'] : 'Ubicación no especificada');
                        $es_vendido = isset($producto['vendido']) && $producto['vendido'] == 1;
                        $es_inactivo = isset($producto['activo']) && $producto['activo'] == 0;
                        $producto_id = $producto['producto_id'] ?? $producto['id'] ?? 0;
                    ?>
                    <div class="portfolio-item" style="position: relative !important; background: white !important; border-radius: 14px !important; overflow: hidden !important; box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important; display: block !important; width: 100% !important; min-height: 300px; visibility: visible !important; opacity: 1 !important; <?php echo ($es_vendido || $es_inactivo) ? 'opacity: 0.7;' : ''; ?>">
                        <button onclick="removeFavorite(<?php echo $producto_id; ?>)" style="position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.9); color: #ff4d4f; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 1.25rem; z-index: 10; box-shadow: 0 2px 8px rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-heart"></i>
                        </button>
                        <a href="producto.php?id=<?php echo $producto_id; ?>" style="text-decoration: none; color: inherit; display: block;">
                            <div style="position: relative; overflow: hidden; border-radius: 12px 12px 0 0; aspect-ratio: 1/1;">
                                <img src="<?php echo htmlspecialchars($imagen); ?>" alt="<?php echo htmlspecialchars($producto['titulo'] ?? 'Producto'); ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https://placehold.co/400x400?text=Sin+imagen'">
                                <?php if ($es_vendido): ?>
                                <div style="position: absolute; top: 10px; left: 10px; background: rgba(220, 53, 69, 0.9); color: white; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; z-index: 5;">
                                    Vendido
                                </div>
                                <?php elseif ($es_inactivo): ?>
                                <div style="position: absolute; top: 10px; left: 10px; background: rgba(108, 117, 125, 0.9); color: white; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; z-index: 5;">
                                    No disponible
                                </div>
                                <?php endif; ?>
                            </div>
                            <div style="padding: 1rem;">
                                <h3 style="font-size: clamp(0.9rem, 2vw, 1rem); margin-bottom: 0.5rem; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; color: #333; font-weight: 600;"><?php echo htmlspecialchars($producto['titulo'] ?? 'Sin título'); ?></h3>
                                <p style="font-size: 1.25rem; font-weight: 700; color: #0C9268; margin-bottom: 0.5rem;"><?php echo $precio_formateado; ?></p>
                                <p style="font-size: 0.85rem; color: #666;"><i class="fas fa-map-marker-alt" style="color: #0C9268;"></i> <?php echo htmlspecialchars($ubicacion); ?></p>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
    function removeFavorite(productoId) {
        if (!confirm('¿Deseas eliminar este producto de tus favoritos?')) {
            return;
        }
        
        fetch('process_favoritos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=remove&producto_id=' + productoId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar contador del navbar antes de recargar
                if (typeof window.updateNavbarCounters === 'function') {
                    window.updateNavbarCounters();
                }
                location.reload();
            } else {
                alert(data.error || 'Error al eliminar de favoritos');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar de favoritos');
        });
    }
    </script>
    <style>
    /* Forzar visualización del grid */
    .portfolio-grid {
        display: grid !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    .portfolio-item {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    /* Responsividad para favoritos */
    @media (max-width: 640px) {
        .portfolio-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)) !important;
            gap: 1rem !important;
        }
        
        .portfolio-item h3 {
            font-size: 0.9rem !important;
        }
        
        .portfolio-item p {
            font-size: 1rem !important;
        }
    }
    </style>
</body>
</html>


