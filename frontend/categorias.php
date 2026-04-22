<?php
// Página de Categorías - Esfero
// Habilitar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Cargar conexión a la base de datos (sin sanitize.php)
try {
    require_once __DIR__ . '/includes/db_connection.php';
} catch (Exception $e) {
    error_log("Error al cargar db_connection.php en categorias.php: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Función simple de sanitización HTML (sin depender de sanitize.php)
function simple_sanitize_html($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Obtener categorías reales de la base de datos (con manejo de errores)
$categorias = [];
try {
    if (function_exists('getCategorias')) {
        $categorias = getCategorias();
        if (!is_array($categorias)) {
            $categorias = [];
        }
    }
} catch (Exception $e) {
    error_log("Error al obtener categorías en categorias.php: " . $e->getMessage());
    $categorias = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png">
    <script>if('serviceWorker'in navigator)navigator.serviceWorker.register('/sw.js');</script>
    <title>Categorías - Esfero</title>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* Estilos glassmorphism para tarjetas de categorías */
        .categoria-card {
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.7) !important;
            border: 1px solid rgba(255, 255, 255, 0.8) !important;
            box-shadow: 12px 17px 51px rgba(0, 0, 0, 0.22) !important;
            backdrop-filter: blur(6px);
            transition: all 0.5s ease !important;
        }
        
        .categoria-card:hover {
            border: 1px solid rgba(0, 166, 118, 0.5) !important;
            transform: scale(1.05) !important;
            box-shadow: 15px 20px 60px rgba(0, 166, 118, 0.3) !important;
        }
        
        .categoria-card:active {
            transform: scale(0.98) rotateZ(0.5deg) !important;
        }
        
        .categoria-card img {
            transition: transform 0.5s ease;
        }
        
        .categoria-card:hover img {
            transform: scale(1.15);
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <!-- Categorías Hero -->
    <section class="page-hero" style="background: linear-gradient(135deg, #044E65 0%, #0D87A8 50%, #0C9268 100%); padding: 6rem 0 4rem; text-align: center; color: white;">
        <div class="container">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Explora nuestras Categorías</h1>
            <p style="font-size: 1.2rem; opacity: 0.9;">Encuentra exactamente lo que buscas en miles de productos</p>
        </div>
    </section>

    <!-- Categorías Grid -->
    <section class="sections" style="padding: 4rem 0;">
        <div class="container">
            <?php if (empty($categorias)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <p style="font-size: 1.2rem; margin-bottom: 1rem;">No hay categorías disponibles en este momento.</p>
                </div>
            <?php else: ?>
                <div class="services-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2rem;">
                    <?php 
                    // Función para obtener imagen de categoría
                    function getCategoriaImagen($categoria) {
                        if (!empty($categoria['imagen_banner'])) {
                            return $categoria['imagen_banner'];
                        }

                        // Slugs exactos de la BD (schema.sql)
                        $imagenes_map = [
                            'electronica'  => 'https://images.unsplash.com/photo-1468495244123-6c6c332eeece?w=800&h=600&fit=crop&q=80',
                            'ropa-moda'    => 'https://images.unsplash.com/photo-1558769132-cb1aea458c5e?w=800&h=600&fit=crop&q=80',
                            'hogar-jardin' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=800&h=600&fit=crop&q=80',
                            'deportes'     => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=800&h=600&fit=crop&q=80',
                            'libros'       => 'https://images.unsplash.com/photo-1512820790803-83ca734da794?w=800&h=600&fit=crop&q=80',
                            'juguetes'     => 'https://images.unsplash.com/photo-1558877385-81a1c7e67d72?w=800&h=600&fit=crop&q=80',
                            'vehiculos'    => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&h=600&fit=crop&q=80',
                            'musica'       => 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?w=800&h=600&fit=crop&q=80',
                        ];

                        $slug = $categoria['slug'] ?? '';
                        return $imagenes_map[$slug]
                            ?? 'https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=800&h=600&fit=crop&q=80';
                    }
                    
                    foreach ($categorias as $categoria): 
                        $icono = $categoria['icono'] ?: 'fa-folder';
                        $descripcion = $categoria['descripcion'] ?: 'Explora productos en esta categoría';
                        $total_productos = $categoria['total_productos'] ?? 0;
                        $imagen_categoria = getCategoriaImagen($categoria);
                    ?>
                    <a href="productos.php?categoria=<?php echo (int)$categoria['id']; ?>" 
                       class="categoria-card" 
                       style="box-sizing: border-box; width: 100%; height: 100%; min-height: 350px; background: rgba(255, 255, 255, 0.7); border: 1px solid rgba(255, 255, 255, 0.8); box-shadow: 12px 17px 51px rgba(0, 0, 0, 0.22); backdrop-filter: blur(6px); border-radius: 17px; text-align: center; cursor: pointer; transition: all 0.5s; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; user-select: none; text-decoration: none; color: inherit; overflow: hidden; position: relative;">
                        <div style="position: relative; width: 100%; height: 220px; overflow: hidden; border-radius: 17px 17px 0 0;">
                            <img src="<?php echo htmlspecialchars($imagen_categoria); ?>" 
                                 alt="<?php echo htmlspecialchars($categoria['nombre']); ?>" 
                                 style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;" 
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, #0D87A8, #0C9268); align-items: center; justify-content: center;">
                                <i class="fas <?php echo simple_sanitize_html($icono); ?>" style="font-size: 3rem; color: white; opacity: 0.8;"></i>
                            </div>
                            <?php if ($total_productos > 0): ?>
                            <div style="position: absolute; top: 12px; right: 12px; background: rgba(0, 166, 118, 0.95); color: white; padding: 0.5rem 1rem; border-radius: 25px; font-size: 0.85rem; font-weight: 700; backdrop-filter: blur(10px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.3);">
                                <?php echo $total_productos; ?> producto<?php echo $total_productos != 1 ? 's' : ''; ?>
                            </div>
                            <?php endif; ?>
                            <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 60px; background: linear-gradient(to top, rgba(0, 0, 0, 0.6), transparent);"></div>
                        </div>
                        <div style="padding: 1.5rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between; width: 100%;">
                            <div>
                                <h3 style="margin: 0 0 0.75rem 0; font-size: 1.4rem; font-weight: 800; color: #1a1a1a; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);"><?php echo simple_sanitize_html($categoria['nombre']); ?></h3>
                                <p style="margin: 0 0 1rem 0; color: #555; font-size: 0.95rem; line-height: 1.6; font-weight: 500;"><?php echo simple_sanitize_html($descripcion); ?></p>
                            </div>
                            <span style="display: inline-block; margin-top: auto; color: #0C9268; font-weight: 700; font-size: 1rem; text-transform: uppercase; letter-spacing: 0.5px;">Ver productos →</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
</body>
</html>

