<?php
// Cargar conexión a la base de datos y funciones helper
require_once 'includes/db_connection.php';
session_start();
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/auth_middleware.php';

// Obtener usuario actual para verificar favoritos
$usuario_actual = null;
$usuario_id = null;
if (function_exists('is_logged_in') && is_logged_in()) {
    $usuario_actual = get_session_user();
    $usuario_id = $usuario_actual['id'] ?? null;
}

// Obtener productos destacados
$productos_destacados = getProductosDestacados(8);

// URL dinámica del botón "Vender ahora"
if (!is_logged_in()) {
    $vender_url = 'registro.php';
} elseif (puede_vender($usuario_id)) {
    $vender_url = 'publicar_producto.php';
} else {
    $vender_url = 'activar_vendedor.php';
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
    <title>Esfero - Tu marketplace de confianza en México</title>
    <meta name="description" content="Esfero Marketplace — compra y vende artículos de segunda mano en México. Protección al comprador, pagos seguros con PayPal y envíos a todo el país.">
    <!-- Preconnect para recursos externos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <!-- Inter: no-render-blocking con display=swap -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
    <!-- FontAwesome: no-render-blocking -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="has-hero">
    <?php include 'components/navbar.php'; ?>
    <main>

    <!-- Hero Section - Optimizado para carga prioritaria en móviles -->
    <section class="hero" id="inicio" data-parallax-speed="0.35" style="order: -1;">
        <canvas id="dotGridCanvas" aria-hidden="true"></canvas>
        <div class="hero-content" style="width: 100%; max-width: 920px; margin: 0 auto; text-align: center; position: relative; z-index: 10;">
            <img src="/assets/img/logo-white.svg" alt="Esfero"
                 style="height: clamp(48px, 8vw, 72px); margin-bottom: 1.25rem; opacity: 0.95; filter: drop-shadow(0 2px 16px rgba(0,0,0,0.25));">
            <h1 style="font-size: clamp(1.5rem, 3.5vw, 2.5rem); line-height: 1.2; margin-bottom: 0.75rem; font-weight: 700;">Dale nueva vida a lo bueno.</h1>
            <p style="font-size: clamp(1rem, 2.2vw, 1.2rem); opacity: 0.9; margin-bottom: 1.25rem; line-height: 1.5;">Encuentra las mejores ofertas de segunda mano con protección al comprador y envíos a todo el país</p>

            <div id="search-bar" style="display: flex; gap: 0.5rem; background: rgba(255,255,255,0.95); border-radius: 14px; padding: 0.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.12); align-items: center; backdrop-filter: saturate(140%) blur(6px); margin: 0 auto 1rem; max-width: 780px; width: 100%; position: relative;">
                <svg style="color: #0D87A8; width: 1.1rem; height: 1.1rem; padding: 0 0.5rem; flex-shrink: 0;" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
                <input id="search-input" type="text" placeholder="Buscar: iPhone, PlayStation, Nike, refrigerador, moto…" style="flex: 1; border: none; outline: none; background: transparent; padding: 0.75rem 0.5rem; font-size: clamp(0.9rem, 2vw, 1rem); min-width: 0;">
                <button id="hero-search-btn" type="button" class="cta-button" style="margin: 0; border-radius: 8px; padding: 0.5rem 0.75rem; white-space: nowrap; background: #0D87A8; font-size: clamp(0.8rem, 1.8vw, 0.9rem); flex-shrink: 0; font-weight: 500;">
                    Buscar
                </button>
            </div>
            <div id="search-suggestions" style="display:none; position: relative; max-width: 780px; width: 100%; margin: 0 auto 0.75rem;"></div>

            <div id="chips" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; margin-bottom: 1rem; padding: 0 0.5rem;">
                <a href="buscar.php?query=Celulares"    class="hero-chip">Celulares</a>
                <a href="buscar.php?query=Consolas"     class="hero-chip">Consolas</a>
                <a href="buscar.php?query=Motos"        class="hero-chip">Motos</a>
                <a href="buscar.php?query=L%C3%ADnea+Blanca" class="hero-chip">Línea Blanca</a>
                <a href="buscar.php?query=Ropa"         class="hero-chip">Ropa</a>
                <a href="buscar.php?query=Muebles"      class="hero-chip">Muebles</a>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; padding: 0 0.5rem;">
                <a href="#categorias" class="cta-button" style="min-width: 160px; max-width: 280px; text-align: center; flex: 1 1 auto;">Explorar categorías</a>
                <a href="<?php echo $vender_url; ?>" class="cta-button" style="min-width: 160px; max-width: 280px; text-align: center; background: linear-gradient(135deg, #F97316 0%, #EA580C 100%); box-shadow: 0 4px 18px rgba(249,115,22,0.35); flex: 1 1 auto;">Vender ahora</a>
            </div>
        </div>
    </section>
    <!-- Productos destacados Section -->
    <section class="sections" id="destacados" style="background: linear-gradient(135deg, #EEF8FA 0%, #daf3f9 100%); margin: 0; width: 100%; display: block;">
        <div class="container">
            <h2 class="section-title">Productos destacados</h2>
            <div id="quick-filters" style="display:flex; gap:0.5rem; flex-wrap:wrap; justify-content:center; margin:-0.5rem 0 1.25rem;">
                <button class="filter-chip"><i class="fas fa-tag"></i> Hasta $1,500</button>
                <button class="filter-chip"><i class="fas fa-truck"></i> Envío gratis</button>
                <button class="filter-chip"><i class="fas fa-star"></i> Como nuevo</button>
                <button class="filter-chip"><i class="fas fa-map-marker-alt"></i> En tu ciudad</button>
            </div>
            <p style="text-align: center; font-size: 1.2rem; color: #2B2B2B; margin-bottom: 1.5rem; width: 100%; margin-left: auto; margin-right: auto;">
                Ofertas seleccionadas por el equipo de Esfero. Encuentra artículos usados en excelente estado.
            </p>
            <div class="portfolio-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.1rem;" id="productsGrid">
                <?php if (empty($productos_destacados)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #666;">
                        <p style="font-size: 1.2rem;">No hay productos destacados disponibles en este momento.</p>
                        <?php if (!is_admin()): ?>
                        <a href="publicar_producto.php" class="cta-button" style="margin-top: 1rem; display: inline-block;">Publicar un producto</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($productos_destacados as $producto): 
                        $imagen = $producto['imagen_principal'] ?: 'https://placehold.co/400x400?text=Sin+imagen';
                        $precio_formateado = formatearPrecio($producto['precio'], $producto['moneda']);
                        $precio_original = $producto['precio_original'] ? formatearPrecio($producto['precio_original'], $producto['moneda']) : null;
                        $descuento = $precio_original ? round((($producto['precio_original'] - $producto['precio']) / $producto['precio_original']) * 100) : 0;
                        $estado_texto = getEstadoTexto($producto['estado_producto']);
                        $ubicacion = $producto['ubicacion_ciudad'] ?: $producto['ubicacion_estado'] ?: 'Ubicación no especificada';
                        $calificacion = $producto['vendedor_calificacion'] ? round($producto['vendedor_calificacion'], 1) : 0;
                        
                        // Verificar si está en favoritos
                        $es_favorito = false;
                        if ($usuario_id && function_exists('esFavorito')) {
                            $es_favorito = esFavorito($usuario_id, $producto['id']);
                        }
                        $icono_favorito = $es_favorito ? 'fas fa-heart' : 'far fa-heart';
                        $color_favorito = $es_favorito ? '#ff4d4f' : '#0D87A8';
                    ?>
                <a href="producto.php?id=<?php echo $producto['id']; ?>" class="portfolio-item animate-in" style="text-decoration:none; color:inherit; display:block;">
                    <div style="position:relative; overflow:hidden; aspect-ratio:1/1; background:#EEF8FA;">
                        <?php if ($imagen): ?>
                        <img src="<?php echo htmlspecialchars($imagen); ?>" alt="<?php echo htmlspecialchars($producto['titulo']); ?>" width="400" height="400" loading="lazy" decoding="async" style="width:100%; height:100%; object-fit:cover;" onerror="this.src='https://placehold.co/400x400?text=Sin+imagen'">
                        <?php else: ?>
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#8BB4C0;"><i class="fas fa-image" style="font-size:2rem;"></i></div>
                        <?php endif; ?>
                        <!-- Badge estado del producto -->
                        <span style="position:absolute; top:10px; left:10px; background:rgba(11,45,60,0.72); backdrop-filter:blur(4px); color:white; padding:0.2rem 0.5rem; border-radius:6px; font-size:0.72rem; font-weight:600; z-index:2;"><?php echo htmlspecialchars($estado_texto); ?></span>
                        <!-- Botón favorito -->
                        <button onclick="event.stopPropagation(); event.preventDefault(); toggleFavorite(<?php echo (int)$producto['id']; ?>, this);"
                                style="position:absolute; top:8px; right:8px; background:rgba(255,255,255,0.92); border:none; width:34px; height:34px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:3; box-shadow:0 2px 8px rgba(0,0,0,0.12); transition:transform 0.15s; backdrop-filter:blur(4px);"
                                data-producto-id="<?php echo (int)$producto['id']; ?>"
                                title="<?php echo $es_favorito ? 'Quitar de favoritos' : 'Agregar a favoritos'; ?>">
                            <i class="<?php echo $icono_favorito; ?>" style="color:<?php echo $color_favorito; ?>; font-size:1rem;"></i>
                        </button>
                    </div>
                    <div class="card-footer">
                        <h3 class="card-title"><?php echo htmlspecialchars($producto['titulo']); ?></h3>
                        <div style="display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap; margin-bottom:0.4rem;">
                            <span class="card-price"><?php echo $precio_formateado; ?></span>
                            <?php if ($precio_original && $descuento > 0): ?>
                                <span class="card-price-original"><?php echo $precio_original; ?></span>
                                <span class="card-discount-badge">-<?php echo $descuento ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:0.4rem;">
                            <p class="card-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ubicacion); ?></p>
                            <?php if (!empty($producto['envio_gratis'])): ?>
                                <span class="card-shipping-badge"><i class="fas fa-truck"></i> Gratis</span>
                            <?php elseif (!empty($producto['envio_disponible'])): ?>
                                <span class="card-shipping-badge"><i class="fas fa-truck"></i> Envío</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

<!-- Sobre Nosotros Section -->
    <section class="sections" id="sobre-nosotros" style="background-color: rgba(4,78,101,0.88); background-image: url('https://images.pexels.com/photos/3965545/pexels-photo-3965545.jpeg?auto=compress&cs=tinysrgb&w=1600'); background-blend-mode: multiply; background-size: cover; background-position: center; background-repeat: no-repeat; position: relative;" data-parallax-speed="0.12">
        <div class="container">
            <h2 class="section-title" style="color: white;">Sobre Esfero</h2>
            <div style="width: 100%; margin: 0 auto; text-align: center; color: white;">
                <p style="font-size: 1.2rem; margin-bottom: 2rem; color: rgba(255,255,255,0.92);">
                    Somos la plataforma mexicana líder en compra y venta de segunda mano. Conectamos a compradores 
                    y vendedores de todo el país con seguridad, confianza y las mejores herramientas del mercado.
                </p>
                <?php
                // Obtener estadísticas reales de la base de datos
                $estadisticas = getEstadisticas();
                $productos_activos = $estadisticas['productos_activos'] ?? 0;
                $usuarios_activos = $estadisticas['usuarios_activos'] ?? 0;
                $estados_cobertura = $estadisticas['estados_cobertura'] ?? 0;
                
                // Formatear números grandes
                $productos_formato = $productos_activos >= 1000 ? number_format($productos_activos / 1000, 1) . 'K+' : $productos_activos;
                $usuarios_formato = $usuarios_activos >= 1000 ? number_format($usuarios_activos / 1000, 1) . 'K+' : $usuarios_activos;
                ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; margin-top: 3rem;">
                    <div style="text-align: center;" data-animate="fade-up">
                        <p aria-label="contador" style="color: #ffffff; font-size: clamp(2rem,5vw,3rem); margin-bottom: 0.5rem; font-weight: 800;"
                            data-count="<?php echo (int)$productos_activos; ?>"><?php echo htmlspecialchars($productos_formato); ?></p>
                        <p style="font-size: 1rem; opacity: 0.88;">Productos Activos</p>
                    </div>
                    <div style="text-align: center;" data-animate="fade-up">
                        <p aria-label="contador" style="color: #ffffff; font-size: clamp(2rem,5vw,3rem); margin-bottom: 0.5rem; font-weight: 800;"
                            data-count="<?php echo (int)$usuarios_activos; ?>"><?php echo htmlspecialchars($usuarios_formato); ?></p>
                        <p style="font-size: 1rem; opacity: 0.88;">Usuarios Activos</p>
                    </div>
                    <div style="text-align: center;" data-animate="fade-up">
                        <p aria-label="contador" style="color: #ffffff; font-size: clamp(2rem,5vw,3rem); margin-bottom: 0.5rem; font-weight: 800;"
                            data-count="<?php echo (int)$estados_cobertura; ?>"><?php echo htmlspecialchars($estados_cobertura); ?></p>
                        <p style="font-size: 1rem; opacity: 0.88;">Estados de México</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Vende en 3 pasos -->
    <section class="sections" id="vende-pasos" style="background: #EEF8FA;" data-parallax-speed="0.12">
        <div class="container">
            <h2 class="section-title">Vende en 3 pasos</h2>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; text-align:center;">
                <div style="background: linear-gradient(135deg, #e8f3ff, #EEF8FA); border:1px solid #e6eef7; border-radius:14px; padding:1rem;">
                    <div style="width:90px; height:90px; border-radius:18px; background: linear-gradient(45deg, #0D87A8, #0C9268); display:flex; align-items:center; justify-content:center; margin:0 auto 0.6rem; color:#fff; font-size:2rem;"><i class="fas fa-camera"></i></div>
                    <h3 style="font-size:1rem; margin-bottom:0.35rem;">1. Publica</h3>
                    <p style="color:#2B2B2B;">Toma fotos, describe el estado y fija el precio</p>
                </div>
                <div style="background: linear-gradient(135deg, #f2efff, #EEF8FA); border:1px solid #ece8ff; border-radius:14px; padding:1rem;">
                    <div style="width:90px; height:90px; border-radius:18px; background: linear-gradient(45deg, #0D87A8, #0C9268); display:flex; align-items:center; justify-content:center; margin:0 auto 0.6rem; color:#fff; font-size:2rem;"><i class="fas fa-comments"></i></div>
                    <h3 style="font-size:1rem; margin-bottom:0.35rem;">2. Recibe ofertas</h3>
                    <p style="color:#2B2B2B;">Chatea con compradores y concreta la venta</p>
                </div>
                <div style="background: linear-gradient(135deg, #e8fff6, #EEF8FA); border:1px solid #dff5ec; border-radius:14px; padding:1rem;">
                    <div style="width:90px; height:90px; border-radius:18px; background: linear-gradient(45deg, #0C9268, #0D87A8); display:flex; align-items:center; justify-content:center; margin:0 auto 0.6rem; color:#fff; font-size:2rem;"><i class="fas fa-truck"></i></div>
                    <h3 style="font-size:1rem; margin-bottom:0.35rem;">3. Envía y cobra</h3>
                    <p style="color:#2B2B2B;">Imprime la guía, envía y recibe el pago seguro</p>
                </div>
            </div>
            <div style="text-align:center; margin-top:1rem;">
                <a href="<?php echo $vender_url; ?>" class="cta-button" style="min-width: 200px;">Vender ahora</a>
            </div>
        </div>
    </section>

    <!-- Pilares de confianza Section -->
    <section class="sections" id="por-que-esfero" style="background: #EEF8FA; position: relative;" data-parallax-speed="0.12">
        <div class="container">
            <!-- Contenedor unificado para botón y tarjetas -->
            <div id="explode-main-container" style="position: relative; min-height: 350px;">
                
                <!-- Botón de explosión -->
                <div id="explode-button-wrapper" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; transition: opacity 0.4s;">
                    <button id="explode-btn" class="explode-button">
                        <span class="explode-btn-icon">✨</span>
                        <span class="explode-btn-text">¿Por qué elegir Esfero?</span>
                        <span class="explode-btn-icon">✨</span>
                        <div class="explode-btn-shine"></div>
                    </button>
                </div>
                
                <!-- Tarjetas (ocultas inicialmente, mismo espacio que el botón) -->
                <div id="benefits-container" style="position: absolute; top: 0; left: 0; width: 100%; opacity: 0; pointer-events: none; transition: opacity 0.4s;">
                    
                    <!-- Subtítulo -->
                    <p id="explode-subtitle" style="text-align: center; font-size: 1.2rem; color: #2B2B2B; margin-bottom: 2rem; width: 100%; margin-left: auto; margin-right: auto;">
                        Diseñado para comprar y vender con tranquilidad: seguridad, envíos y soporte local.
                    </p>
                    
                    <!-- Botón de cerrar -->
                    <button id="collapse-btn" class="collapse-button">
                        <i class="fas fa-times"></i>
                    </button>
                    
                    <!-- Grid de tarjetas -->
                    <div id="benefits-grid" class="team-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); position: relative;">
                <div class="team-member benefit-card" data-card="0" style="background: linear-gradient(135deg, #e8f3ff, #EEF8FA); border: 1px solid #e6eef7; border-radius: 12px; padding: 1rem;">
                    <div class="member-avatar" style="margin-bottom: 0.5rem;">
                        <div style="width: 60px; height: 60px; background: linear-gradient(45deg, #0D87A8, #0C9268); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem; margin: 0 auto;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                    </div>
                    <h3 style="text-align:center; margin-bottom: 0.25rem; font-size: 0.95rem;">Pago protegido</h3>
                    <p class="member-description" style="text-align:center; color:#2B2B2B; font-size: 0.85rem;">Tu dinero está seguro hasta que confirmas la entrega.</p>
                </div>
                <div class="team-member benefit-card" data-card="1" style="background: linear-gradient(135deg, #f2efff, #EEF8FA); border: 1px solid #ece8ff; border-radius: 12px; padding: 1rem;">
                    <div class="member-avatar" style="margin-bottom: 0.5rem;">
                        <div style="width: 60px; height: 60px; background: linear-gradient(45deg, #0D87A8, #0C9268); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem; margin: 0 auto;">
                            <i class="fas fa-truck"></i>
                        </div>
                    </div>
                    <h3 style="text-align:center; margin-bottom: 0.25rem; font-size: 0.95rem;">Envíos confiables</h3>
                    <p class="member-description" style="text-align:center; color:#2B2B2B; font-size: 0.85rem;">Cotiza, imprime y rastrea, con cobertura nacional.</p>
                </div>
                <div class="team-member benefit-card" data-card="2" style="background: linear-gradient(135deg, #e8fff6, #EEF8FA); border: 1px solid #dff5ec; border-radius: 12px; padding: 1rem;">
                    <div class="member-avatar" style="margin-bottom: 0.5rem;">
                        <div style="width: 60px; height: 60px; background: linear-gradient(45deg, #0C9268, #0D87A8); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem; margin: 0 auto;">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <h3 style="text-align:center; margin-bottom: 0.25rem; font-size: 0.95rem;">Vendedores verificados</h3>
                    <p class="member-description" style="text-align:center; color:#2B2B2B; font-size: 0.85rem;">Calificaciones y perfiles para decidir con confianza.</p>
                </div>
                <div class="team-member benefit-card" data-card="3" style="background: linear-gradient(135deg, #fff8e6, #EEF8FA); border: 1px solid #ffefc7; border-radius: 12px; padding: 1rem;">
                    <div class="member-avatar" style="margin-bottom: 0.5rem;">
                        <div style="width: 60px; height: 60px; background: linear-gradient(45deg, #F6A623, #f37d00); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem; margin: 0 auto;">
                            <i class="fas fa-undo"></i>
                        </div>
                    </div>
                    <h3 style="text-align:center; margin-bottom: 0.25rem; font-size: 0.95rem;">Devoluciones simples</h3>
                    <p class="member-description" style="text-align:center; color:#2B2B2B; font-size: 0.85rem;">Si algo no sale bien, te acompañamos en el proceso.</p>
                </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="sections" id="testimonios" style="background: linear-gradient(135deg, #044E65 0%, #0C9268 100%); color: white;" data-parallax-speed="0.16">
        <div class="container">
            <h2 class="section-title" style="color: white;">Experiencias en Esfero</h2>
            <p style="text-align: center; font-size: 1.2rem; margin-bottom: 3rem; width: 100%; margin-left: auto; margin-right: auto; opacity: 0.9;">
                Historias reales de compras y ventas seguras en México
            </p>
            <?php
            // Obtener testimonios reales de la base de datos
            $testimonios = getTestimonios(6);
            ?>
            <div class="testimonials-grid">
                <?php if (empty($testimonios)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: rgba(255,255,255,0.9);">
                        <p>No hay testimonios disponibles en este momento.</p>
                    </div>
                <?php else:
                    foreach ($testimonios as $testimonio):
                        $tipo_texto = $testimonio['tipo'] === 'vendedor' ? 'Vendedor' : ($testimonio['tipo'] === 'comprador' ? 'Comprador' : 'Usuario');
                        $verificado_texto = $testimonio['verificado'] ? 'verificado' : '';
                        $ubicacion_texto = $testimonio['ubicacion'] ? ' • ' . htmlspecialchars($testimonio['ubicacion']) : '';
                ?>
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <div class="quote-icon">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <p>"<?php echo htmlspecialchars($testimonio['contenido']); ?>"</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                        <div class="author-info">
                            <h4><?php echo htmlspecialchars($testimonio['nombre_autor']); ?></h4>
                            <p><?php echo htmlspecialchars($tipo_texto); ?><?php echo $verificado_texto ? ' ' . htmlspecialchars($verificado_texto) : ''; ?><?php echo $ubicacion_texto; ?></p>
                        </div>
                    </div>
                </div>
                <?php 
                    endforeach;
                endif; ?>
            </div>
        </div>
    </section>

    <!-- Guías Section -->
    <section class="sections" id="blog" style="background: var(--c-bg, #F2F9FB);" data-parallax-speed="0.14">
        <div class="container">
            <h2 class="section-title">Guías y Consejos</h2>
            <p style="text-align: center; font-size: 1.2rem; color: #2B2B2B; margin-bottom: 3rem; width: 100%; margin-left: auto; margin-right: auto;">
                Aprende a comprar y vender mejor en Esfero
            </p>
            <?php
            // Obtener guías reales de la base de datos
            $guias = getGuias(null, 6, true);
            
            // Iconos por categoría
            $iconos_categoria = [
                'comprar' => 'fa-shopping-cart',
                'vender' => 'fa-camera',
                'seguridad' => 'fa-shield-alt',
                'envios' => 'fa-truck',
                'pagos' => 'fa-credit-card',
                'general' => 'fa-info-circle'
            ];
            
            // Colores por categoría
            $colores_categoria = [
                'comprar' => 'linear-gradient(45deg, #0D87A8, #0C9268)',
                'vender' => 'linear-gradient(45deg, #0D87A8, #0C9268)',
                'seguridad' => 'linear-gradient(45deg, #2e8b57, #3cb371)',
                'envios' => 'linear-gradient(45deg, #F6A623, #f37d00)',
                'pagos' => 'linear-gradient(45deg, #0C9268, #0D87A8)',
                'general' => 'linear-gradient(45deg, #0D87A8, #0C9268)'
            ];
            ?>
            <div class="blog-grid">
                <?php if (empty($guias)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: #666;">
                        <p>No hay guías disponibles en este momento.</p>
                    </div>
                <?php else:
                    foreach ($guias as $guia):
                        $icono = $iconos_categoria[$guia['categoria']] ?? 'fa-info-circle';
                        $color = $colores_categoria[$guia['categoria']] ?? 'linear-gradient(45deg, #0D87A8, #0C9268)';
                        $fecha = date('d F, Y', strtotime($guia['fecha_publicacion']));
                        $categoria_texto = ucfirst($guia['categoria']);
                ?>
                <article class="blog-card">
                    <div class="blog-image">
                        <?php if ($guia['imagen_url']): ?>
                            <img src="<?php echo htmlspecialchars($guia['imagen_url']); ?>" alt="<?php echo htmlspecialchars($guia['titulo']); ?>" width="800" height="200" loading="lazy" style="width: 100%; height: 200px; object-fit: cover; border-radius: 10px;">
                        <?php else: ?>
                            <div style="width: 100%; height: 200px; background: <?php echo $color; ?>; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                                <i class="fas <?php echo $icono; ?>"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="blog-content">
                        <div class="blog-meta">
                            <span class="blog-date"><?php echo htmlspecialchars($fecha); ?></span>
                            <span class="blog-category"><?php echo htmlspecialchars($categoria_texto); ?></span>
                        </div>
                        <h3><?php echo htmlspecialchars($guia['titulo']); ?></h3>
                        <p><?php 
                            $descripcion = $guia['descripcion_corta'] ?: strip_tags($guia['contenido']);
                            if (function_exists('mb_substr')) {
                                $descripcion = mb_strlen($descripcion) > 100 ? mb_substr($descripcion, 0, 100) . '...' : $descripcion;
                            } else {
                                $descripcion = strlen($descripcion) > 100 ? substr($descripcion, 0, 100) . '...' : $descripcion;
                            }
                            echo htmlspecialchars($descripcion); 
                        ?></p>
                        <a href="guias.php?slug=<?php echo htmlspecialchars($guia['slug']); ?>" class="blog-link">Leer más <i class="fas fa-arrow-right"></i></a>
                    </div>
                </article>
                <?php 
                    endforeach;
                endif; ?>
            </div>
        </div>
    </section>

    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
    <!-- GSAP + ScrollTrigger — defer garantiza que no bloquean el render -->
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script defer src="assets/js/animations.js"></script>
    <script>
        // Grid de productos responsivo — GSAP maneja las animaciones de entrada
        // (el IntersectionObserver fue reemplazado por ScrollTrigger en animations.js)
        
        // Optimizar grid de productos para móviles
        (function() {
            const productsGrid = document.getElementById('productsGrid');
            if (!productsGrid) return;
            
            function adjustGrid() {
                if (window.innerWidth <= 640) {
                    // 2 columnas compactas en móvil (como Amazon/Mercado Libre)
                    productsGrid.style.gridTemplateColumns = '1fr 1fr';
                    productsGrid.style.gap = '0.65rem';
                } else if (window.innerWidth <= 1024) {
                    productsGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(220px, 1fr))';
                    productsGrid.style.gap = '1rem';
                } else {
                    productsGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(260px, 1fr))';
                    productsGrid.style.gap = '1.1rem';
                }
            }
            
            adjustGrid();
            window.addEventListener('resize', adjustGrid);
        })();
        
        // Función para toggle de favoritos
        function toggleFavorite(productoId, buttonElement) {
            // Verificar si el usuario está logueado
            <?php if (!$usuario_id): ?>
                if (confirm('Debes iniciar sesión para agregar productos a favoritos. ¿Deseas ir a la página de inicio de sesión?')) {
                    window.location.href = 'login.php';
                }
                return;
            <?php endif; ?>
            
            const icon = buttonElement.querySelector('i');
            const isFavorite = icon.classList.contains('fas');
            
            // Mostrar estado de carga
            buttonElement.disabled = true;
            icon.style.opacity = '0.5';
            
            // Hacer petición al servidor
            fetch('process_favoritos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=toggle&producto_id=' + productoId
            })
            .then(response => response.json())
            .then(data => {
                buttonElement.disabled = false;
                icon.style.opacity = '1';
                
                if (data.success) {
                    // Cambiar icono
                    if (isFavorite) {
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                        icon.style.color = '#0D87A8';
                        buttonElement.title = 'Agregar a favoritos';
                    } else {
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        icon.style.color = '#ff4d4f';
                        buttonElement.title = 'Quitar de favoritos';
                    }
                    
                    // Actualizar contador del navbar
                    if (typeof window.updateNavbarCounters === 'function') {
                        window.updateNavbarCounters();
                    }
                } else {
                    alert(data.error || 'Error al actualizar favoritos');
                    // Revertir cambio visual si falló
                    if (isFavorite) {
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        icon.style.color = '#ff4d4f';
                    } else {
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                        icon.style.color = '#0D87A8';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                buttonElement.disabled = false;
                icon.style.opacity = '1';
                alert('Error al actualizar favoritos. Por favor, intenta nuevamente.');
            });
        }
    </script>
    <!-- DotGrid background — Vanilla JS Canvas (inspirado en ReactBits DotGrid) -->
    <script>
    (function() {
        var canvas = document.getElementById('dotGridCanvas');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var hero  = document.getElementById('inicio');

        // ── Config ──────────────────────────────────────────────────────────────
        var DOT_R       = 2.2;   // radio del punto (px)
        var GAP         = 22;    // separación entre puntos (px)
        var PROXIMITY   = 130;   // radio de influencia del cursor (px)
        var EASE        = 0.10;  // velocidad de transición (0-1)

        // Colores en RGB  (igual que la paleta Esfero)
        var C_BG        = '#00303f';            // fondo del hero
        var C_IDLE      = [0, 95, 120];          // punto en reposo
        var C_ACTIVE    = [130, 255, 200];      // punto iluminado (brillo máximo)

        var mouse = { x: -9999, y: -9999 };
        var dots  = [];
        var ripples = [];
        var raf   = null;
        var dpr   = window.devicePixelRatio || 1;

        // ── Helpers ─────────────────────────────────────────────────────────────
        function lerp(a, b, t) { return a + (b - a) * t; }
        function lerpC(c1, c2, t) {
            return [
                Math.round(lerp(c1[0], c2[0], t)),
                Math.round(lerp(c1[1], c2[1], t)),
                Math.round(lerp(c1[2], c2[2], t))
            ];
        }

        // ── Build grid ──────────────────────────────────────────────────────────
        function buildDots(w, h) {
            dots = [];
            var ox = (w % GAP) / 2;
            var oy = (h % GAP) / 2;
            for (var x = ox; x < w; x += GAP) {
                for (var y = oy; y < h; y += GAP) {
                    dots.push({ x: x, y: y, b: 0 });
                }
            }
        }

        // ── Resize ──────────────────────────────────────────────────────────────
        function resize() {
            dpr = window.devicePixelRatio || 1;
            var w = hero.offsetWidth;
            var h = hero.offsetHeight;
            canvas.width  = w * dpr;
            canvas.height = h * dpr;
            canvas.style.width  = w + 'px';
            canvas.style.height = h + 'px';
            ctx.scale(dpr, dpr);
            buildDots(w, h);
        }

        // ── Main draw loop ───────────────────────────────────────────────────────
        function draw() {
            var w = canvas.width  / dpr;
            var h = canvas.height / dpr;

            ctx.fillStyle = C_BG;
            ctx.fillRect(0, 0, w, h);

            for (var i = 0; i < dots.length; i++) {
                var d   = dots[i];
                var dx  = d.x - mouse.x;
                var dy  = d.y - mouse.y;
                var dist = Math.sqrt(dx * dx + dy * dy);

                // Proximity from cursor
                var target = dist < PROXIMITY ? (1 - dist / PROXIMITY) : 0;

                // Ripple contribution
                for (var r = 0; r < ripples.length; r++) {
                    var rp   = ripples[r];
                    var rdx  = d.x - rp.x;
                    var rdy  = d.y - rp.y;
                    var rdist = Math.sqrt(rdx * rdx + rdy * rdy);
                    var thick = 45;
                    var diff  = Math.abs(rdist - rp.rad);
                    if (diff < thick) {
                        var t = (1 - diff / thick) * rp.a;
                        if (t > target) target = t;
                    }
                }

                // Smooth
                d.b += (target - d.b) * EASE;

                var color = lerpC(C_IDLE, C_ACTIVE, Math.min(1, d.b));
                var alpha = 0.38 + d.b * 0.62;

                // Radio crece con el hover (efecto de "levantarse")
                var r = DOT_R * (1 + d.b * 2.2);

                // 1. Sombra offset → ilusión de profundidad
                if (d.b > 0.05) {
                    var so = d.b * 4;
                    ctx.beginPath();
                    ctx.arc(d.x + so, d.y + so, r * 0.85, 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(0,0,0,' + (d.b * 0.5) + ')';
                    ctx.fill();
                }

                // 2. Punto principal
                ctx.beginPath();
                ctx.arc(d.x, d.y, r, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(' + color[0] + ',' + color[1] + ',' + color[2] + ',' + alpha + ')';
                ctx.fill();

                // 3. Reflejo especular arriba-izquierda → ilusión de esfera 3D
                if (d.b > 0.12) {
                    ctx.beginPath();
                    ctx.arc(d.x - r * 0.28, d.y - r * 0.30, r * 0.30, 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(255,255,255,' + (d.b * 0.50) + ')';
                    ctx.fill();
                }
            }

            // Advance ripples
            for (var j = ripples.length - 1; j >= 0; j--) {
                ripples[j].rad += 4.5;
                ripples[j].a   -= 0.014;
                if (ripples[j].a <= 0) ripples.splice(j, 1);
            }

            raf = requestAnimationFrame(draw);
        }

        // ── Events ──────────────────────────────────────────────────────────────
        hero.addEventListener('mousemove', function(e) {
            var rect = hero.getBoundingClientRect();
            mouse.x = e.clientX - rect.left;
            mouse.y = e.clientY - rect.top;
        });
        hero.addEventListener('mouseleave', function() {
            mouse.x = -9999;
            mouse.y = -9999;
        });
        hero.addEventListener('click', function(e) {
            var rect = hero.getBoundingClientRect();
            ripples.push({
                x: e.clientX - rect.left,
                y: e.clientY - rect.top,
                rad: 0,
                a: 1
            });
        });

        // Touch support
        hero.addEventListener('touchmove', function(e) {
            var rect = hero.getBoundingClientRect();
            var t = e.touches[0];
            mouse.x = t.clientX - rect.left;
            mouse.y = t.clientY - rect.top;
        }, { passive: true });
        hero.addEventListener('touchend', function() {
            mouse.x = -9999; mouse.y = -9999;
        });

        // ── Init ────────────────────────────────────────────────────────────────
        window.addEventListener('resize', resize);
        resize();
        draw();

        // Pause when hero not visible (perf)
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function(entries) {
                if (entries[0].isIntersecting) {
                    if (!raf) draw();
                } else {
                    cancelAnimationFrame(raf);
                    raf = null;
                }
            }).observe(hero);
        }
    })();
    </script>

    <!-- Toasts -->
    <div id="toast-container" style="position: fixed; right: 20px; bottom: 20px; display: flex; flex-direction: column; gap: 8px; z-index: 9999;"></div>

    <style>
    .hero-chip {
        background: rgba(255,255,255,0.18);
        color: white;
        border: 1px solid rgba(255,255,255,0.25);
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        font-size: clamp(0.8rem, 2vw, 0.9rem);
        text-decoration: none;
        transition: background 0.2s, border-color 0.2s;
        white-space: nowrap;
    }
    .hero-chip:hover { background: rgba(255,255,255,0.32); border-color: rgba(255,255,255,0.5); }
    </style>
    <script>
    (function() {
        function goSearch() {
            var q = (document.getElementById('search-input').value || '').trim();
            if (!q) return;
            window.location.href = 'buscar.php?query=' + encodeURIComponent(q);
        }
        document.getElementById('hero-search-btn').addEventListener('click', goSearch);
        document.getElementById('search-input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') goSearch();
        });
    })();
    </script>
</body>
</html>
