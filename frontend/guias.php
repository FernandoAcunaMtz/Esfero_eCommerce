<?php
// Habilitar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Cargar conexión a la base de datos y funciones helper
try {
    require_once __DIR__ . '/includes/db_connection.php';
    require_once __DIR__ . '/includes/sanitize.php';
} catch (Exception $e) {
    error_log("Error al cargar includes en guias.php: " . $e->getMessage());
    // Continuar aunque falle la BD (para ver si es el problema)
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener guías - proceso simple igual que index.php
$guias = [];
try {
    if (function_exists('getGuias')) {
        // Intentar destacadas primero
        $guias = getGuias(null, 50, true);
        if (!is_array($guias)) {
            $guias = [];
        }
        if (empty($guias)) {
            // Si no hay destacadas, todas las activas
            $guias = getGuias(null, 50, false);
            if (!is_array($guias)) {
                $guias = [];
            }
        }
    }
} catch (Exception $e) {
    error_log("Error en guias.php: " . $e->getMessage());
    $guias = [];
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
    <title>Guías y Consejos - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* Forzar visibilidad de las tarjetas blog-card en esta página */
        .blog-card {
            opacity: 1 !important;
            transform: translateY(0) scale(1) !important;
            visibility: visible !important;
        }
        
        /* Diseño de lista vertical - 1 columna, 100% ancho */
        .guias-lista-vertical {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            width: 100%;
        }
        
        .guias-lista-vertical .blog-card {
            width: 100% !important;
            display: flex;
            flex-direction: row;
            align-items: stretch;
            min-height: 250px;
            margin: 0 !important;
            padding: 0 !important;
            /* Efecto glassmorphism */
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 78, 100, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .guias-lista-vertical .blog-card:hover {
            transform: translateY(-4px) !important;
            box-shadow: 0 12px 48px rgba(0, 166, 118, 0.25), 0 0 0 1px rgba(0, 166, 118, 0.1);
            background: rgba(255, 255, 255, 0.95) !important;
        }
        
        .guias-lista-vertical .blog-image {
            flex: 0 0 350px;
            min-width: 350px;
            width: 350px;
            align-self: stretch;
            display: flex;
            overflow: hidden;
            padding: 0 !important;
            margin: 0 !important;
            border-radius: 0;
        }
        
        .guias-lista-vertical .blog-image img,
        .guias-lista-vertical .blog-image > div {
            width: 100% !important;
            height: 100% !important;
            min-height: 100% !important;
            object-fit: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .guias-lista-vertical .blog-image > div {
            border-radius: 0;
        }
        
        .guias-lista-vertical .blog-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2rem;
            margin: 0 !important;
        }
        
        @media (max-width: 768px) {
            .guias-lista-vertical .blog-card {
                flex-direction: column;
                min-height: auto;
            }
            
            .guias-lista-vertical .blog-image {
                flex: 1 1 250px;
                min-width: 100%;
                height: 250px;
            }
        }
        
        /* Modal para mostrar contenido completo */
        .modal-guia {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }
        
        .modal-guia.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .modal-content-guia {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 78, 100, 0.3);
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: modalFadeIn 0.3s ease;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .modal-header-guia {
            padding: 2rem 2rem 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 10;
            border-radius: 20px 20px 0 0;
        }
        
        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: rgba(0, 0, 0, 0.1);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .modal-close:hover {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            transform: rotate(90deg);
        }
        
        .modal-body-guia {
            padding: 2rem;
        }
        
        .modal-body-guia h1 {
            color: #0D87A8;
            font-size: 2rem;
            margin-bottom: 1rem;
            margin-right: 3rem;
        }
        
        .modal-body-guia .guia-categoria-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .modal-body-guia .guia-contenido-completo {
            color: #333;
            line-height: 1.8;
            font-size: 1.05rem;
        }
        
        .modal-body-guia .guia-contenido-completo h2 {
            color: #0D87A8;
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .modal-body-guia .guia-contenido-completo h3 {
            color: #0C9268;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            font-size: 1.25rem;
        }
        
        .modal-body-guia .guia-contenido-completo p {
            margin-bottom: 1rem;
        }
        
        .modal-body-guia .guia-contenido-completo ul,
        .modal-body-guia .guia-contenido-completo ol {
            margin: 1rem 0;
            padding-left: 2rem;
        }
        
        .modal-body-guia .guia-contenido-completo li {
            margin-bottom: 0.5rem;
        }
        
        .modal-loading {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .modal-loading i {
            font-size: 3rem;
            color: #0C9268;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .guia-card-clickable:hover {
            cursor: pointer;
        }
    </style>
</head>
<body class="has-hero">
    <?php include 'components/navbar.php'; ?>
    
    <!-- Hero -->
    <section class="page-hero" style="background: linear-gradient(135deg, #044E65 0%, #0D87A8 50%, #0C9268 100%); padding: 6rem 0 4rem; text-align: center; color: white;">
        <div class="container">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Guías y Consejos</h1>
            <p style="font-size: 1.2rem; opacity: 0.9;">Aprende a comprar y vender de forma segura</p>
        </div>
    </section>

    <!-- Guías Destacadas -->
    <section class="sections" style="background: var(--c-bg, #F2F9FB);">
        <div class="container">
            <p style="text-align: center; font-size: 1.2rem; color: #2B2B2B; margin-bottom: 3rem;">
                Aprende a comprar y vender mejor en Esfero
            </p>
            
            <?php
            // Función para asignar icono según el contenido de la guía
            function obtenerIconoGuia($titulo, $categoria) {
                $titulo_lower = mb_strtolower($titulo, 'UTF-8');
                
                // Mapeo de palabras clave a iconos específicos
                $mapeo_iconos = [
                    // Comprar
                    'comprar' => 'fa-shopping-cart',
                    'como comprar' => 'fa-shopping-bag',
                    'guía de compra' => 'fa-shopping-basket',
                    'compra segura' => 'fa-shield-check',
                    'mejores precios' => 'fa-tags',
                    'comparar' => 'fa-balance-scale',
                    
                    // Vender
                    'vender' => 'fa-store',
                    'como vender' => 'fa-hand-holding-usd',
                    'publicar producto' => 'fa-camera',
                    'foto' => 'fa-camera-retro',
                    'fotografia' => 'fa-image',
                    'imagen' => 'fa-images',
                    'precio' => 'fa-dollar-sign',
                    'costo' => 'fa-money-bill-wave',
                    
                    // Seguridad
                    'seguridad' => 'fa-shield-alt',
                    'seguro' => 'fa-lock',
                    'proteccion' => 'fa-user-shield',
                    'fraude' => 'fa-exclamation-triangle',
                    'estafa' => 'fa-ban',
                    'verificar' => 'fa-check-circle',
                    'autenticidad' => 'fa-certificate',
                    'revisar' => 'fa-search',
                    
                    // Envíos
                    'envio' => 'fa-truck',
                    'envios' => 'fa-shipping-fast',
                    'entreg' => 'fa-box',
                    'paquete' => 'fa-box-open',
                    'transport' => 'fa-truck-moving',
                    'logistica' => 'fa-route',
                    
                    // Pagos
                    'pago' => 'fa-credit-card',
                    'pagos' => 'fa-money-check-alt',
                    'paypal' => 'fa-cc-paypal',
                    'tarjeta' => 'fa-credit-card',
                    'efectivo' => 'fa-money-bill',
                    'transferencia' => 'fa-exchange-alt',
                    'reembolso' => 'fa-undo-alt',
                    'devolucion' => 'fa-reply',
                    
                    // Productos específicos
                    'telefono' => 'fa-mobile-alt',
                    'celular' => 'fa-mobile-alt',
                    'smartphone' => 'fa-mobile-alt',
                    'iphone' => 'fa-mobile-alt',
                    'laptop' => 'fa-laptop',
                    'computadora' => 'fa-desktop',
                    'tablet' => 'fa-tablet-alt',
                    'bicicleta' => 'fa-bicycle',
                    'ropa' => 'fa-tshirt',
                    'zapatos' => 'fa-shoe-prints',
                    'muebles' => 'fa-couch',
                    'electrodomesticos' => 'fa-tv',
                    'consola' => 'fa-gamepad',
                    'juego' => 'fa-gamepad',
                    'cámara' => 'fa-camera',
                    'camara' => 'fa-camera',
                    'audifonos' => 'fa-headphones',
                    'auriculares' => 'fa-headphones',
                    
                    // Consejos y tips
                    'consejo' => 'fa-lightbulb',
                    'tip' => 'fa-lightbulb',
                    'recomendacion' => 'fa-star',
                    'mejores' => 'fa-thumbs-up',
                    'como elegir' => 'fa-question-circle',
                    'guia completa' => 'fa-book',
                    
                    // Contacto y comunicación
                    'mensaje' => 'fa-envelope',
                    'contacto' => 'fa-address-book',
                    'chat' => 'fa-comments',
                    'comunicacion' => 'fa-comment-dots',
                    
                    // Perfil
                    'perfil' => 'fa-user-circle',
                    'cuenta' => 'fa-user-cog',
                    'configuracion' => 'fa-cog',
                    
                    // General
                    'ayuda' => 'fa-life-ring',
                    'preguntas frecuentes' => 'fa-question-circle',
                    'faq' => 'fa-question-circle',
                ];
                
                // Buscar palabras clave en el título
                foreach ($mapeo_iconos as $palabra => $icono) {
                    if (mb_strpos($titulo_lower, $palabra) !== false) {
                        return $icono;
                    }
                }
                
                // Fallback por categoría
                $iconos_categoria = [
                    'comprar' => 'fa-shopping-cart',
                    'vender' => 'fa-store',
                    'seguridad' => 'fa-shield-alt',
                    'envios' => 'fa-truck',
                    'pagos' => 'fa-credit-card',
                    'general' => 'fa-info-circle'
                ];
                
                return $iconos_categoria[$categoria] ?? 'fa-book-open';
            }
            
            // Función para asignar color según el contenido
            function obtenerColorGuia($titulo, $categoria) {
                $titulo_lower = mb_strtolower($titulo, 'UTF-8');
                
                // Colores específicos por tema
                $mapeo_colores = [
                    // Seguridad - Verde
                    'seguridad' => 'linear-gradient(135deg, #2e8b57 0%, #3cb371 100%)',
                    'seguro' => 'linear-gradient(135deg, #2e8b57 0%, #3cb371 100%)',
                    'proteccion' => 'linear-gradient(135deg, #2e8b57 0%, #3cb371 100%)',
                    'fraude' => 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)',
                    'estafa' => 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)',
                    
                    // Envíos - Naranja
                    'envio' => 'linear-gradient(135deg, #F6A623 0%, #f37d00 100%)',
                    'envios' => 'linear-gradient(135deg, #F6A623 0%, #f37d00 100%)',
                    'entreg' => 'linear-gradient(135deg, #F6A623 0%, #f37d00 100%)',
                    
                    // Pagos - Azul
                    'pago' => 'linear-gradient(135deg, #007bff 0%, #0056b3 100%)',
                    'pagos' => 'linear-gradient(135deg, #007bff 0%, #0056b3 100%)',
                    'paypal' => 'linear-gradient(135deg, #0070ba 0%, #003087 100%)',
                    
                    // Vender - Amarillo/Dorado
                    'vender' => 'linear-gradient(135deg, #ffc107 0%, #ff8c00 100%)',
                    'como vender' => 'linear-gradient(135deg, #ffc107 0%, #ff8c00 100%)',
                    'publicar' => 'linear-gradient(135deg, #ffc107 0%, #ff8c00 100%)',
                    'foto' => 'linear-gradient(135deg, #9c27b0 0%, #7b1fa2 100%)',
                    
                    // Productos específicos - Varios colores
                    'telefono' => 'linear-gradient(135deg, #6c757d 0%, #495057 100%)',
                    'laptop' => 'linear-gradient(135deg, #17a2b8 0%, #138496 100%)',
                    'bicicleta' => 'linear-gradient(135deg, #28a745 0%, #20c997 100%)',
                    'consola' => 'linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%)',
                ];
                
                // Buscar palabras clave
                foreach ($mapeo_colores as $palabra => $color) {
                    if (mb_strpos($titulo_lower, $palabra) !== false) {
                        return $color;
                    }
                }
                
                // Fallback por categoría
                $colores_categoria = [
                    'comprar' => 'linear-gradient(135deg, #0D87A8 0%, #0C9268 100%)',
                    'vender' => 'linear-gradient(135deg, #ffc107 0%, #ff8c00 100%)',
                    'seguridad' => 'linear-gradient(135deg, #2e8b57 0%, #3cb371 100%)',
                    'envios' => 'linear-gradient(135deg, #F6A623 0%, #f37d00 100%)',
                    'pagos' => 'linear-gradient(135deg, #007bff 0%, #0056b3 100%)',
                    'general' => 'linear-gradient(135deg, #0D87A8 0%, #0C9268 100%)'
                ];
                
                return $colores_categoria[$categoria] ?? 'linear-gradient(135deg, #0D87A8 0%, #0C9268 100%)';
            }
            ?>
            
            <div class="guias-lista-vertical">
                <?php if (empty($guias)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: #666;">
                        <p>No hay guías disponibles en este momento.</p>
                    </div>
                <?php else:
                    foreach ($guias as $guia):
                        // Usar funciones inteligentes para obtener icono y color según el contenido
                        $icono = obtenerIconoGuia($guia['titulo'], $guia['categoria']);
                        $color = obtenerColorGuia($guia['titulo'], $guia['categoria']);
                        $fecha = date('d F, Y', strtotime($guia['fecha_publicacion']));
                        $categoria_texto = ucfirst($guia['categoria']);
                ?>
                <article class="blog-card animate-in guia-card-clickable" data-guia-id="<?php echo (int)$guia['id']; ?>" style="opacity: 1 !important; transform: translateY(0) scale(1) !important; visibility: visible !important; cursor: pointer;">
                    <div class="blog-image">
                        <?php if (!empty($guia['imagen_url'])): ?>
                            <img src="<?php echo htmlspecialchars($guia['imagen_url']); ?>" alt="<?php echo htmlspecialchars($guia['titulo']); ?>">
                        <?php else: ?>
                            <div style="background: <?php echo $color; ?>; display: flex; align-items: center; justify-content: center; color: white; font-size: 4rem; min-height: 100%;">
                                <i class="fas <?php echo $icono; ?>" style="filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));"></i>
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
                        <span class="blog-link">Leer más <i class="fas fa-arrow-right"></i></span>
                    </div>
                </article>
                <?php 
                    endforeach;
                endif; ?>
            </div>
        </div>
    </section>

    <!-- Modal para mostrar contenido completo de la guía -->
    <div id="modalGuia" class="modal-guia">
        <div class="modal-content-guia">
            <div class="modal-header-guia">
                <button class="modal-close" onclick="cerrarModalGuia()" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
                <div id="modalGuiaHeader">
                    <div class="modal-loading">
                        <i class="fas fa-spinner"></i>
                        <p>Cargando guía...</p>
                    </div>
                </div>
            </div>
            <div class="modal-body-guia">
                <div id="modalGuiaContent">
                    <!-- El contenido se carga aquí -->
                </div>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        // Forzar visibilidad de todas las tarjetas blog-card
        document.addEventListener('DOMContentLoaded', function() {
            const blogCards = document.querySelectorAll('.blog-card');
            blogCards.forEach(function(card) {
                card.classList.add('animate-in');
                card.style.opacity = '1';
                card.style.transform = 'translateY(0) scale(1)';
                card.style.visibility = 'visible';
            });
            
            // Agregar evento click a las tarjetas
            const guiaCards = document.querySelectorAll('.guia-card-clickable');
            guiaCards.forEach(function(card) {
                card.addEventListener('click', function() {
                    const guiaId = this.getAttribute('data-guia-id');
                    if (guiaId) {
                        abrirModalGuia(guiaId);
                    }
                });
            });
        });
        
        // Función para abrir el modal y cargar el contenido
        function abrirModalGuia(guiaId) {
            const modal = document.getElementById('modalGuia');
            const header = document.getElementById('modalGuiaHeader');
            const content = document.getElementById('modalGuiaContent');
            
            // Mostrar modal con loading
            modal.classList.add('active');
            header.innerHTML = '<div class="modal-loading"><i class="fas fa-spinner"></i><p>Cargando guía...</p></div>';
            content.innerHTML = '';
            
            // Cargar contenido de la guía
            fetch('api_get_guia.php?id=' + guiaId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        header.innerHTML = '<h1>Error</h1>';
                        content.innerHTML = '<p style="color: #dc3545;">' + data.error + '</p>';
                        return;
                    }
                    
                    // Categorías y colores
                    const categoriaNombres = {
                        'comprar': 'Comprar',
                        'vender': 'Vender',
                        'envios': 'Envíos',
                        'seguridad': 'Seguridad',
                        'pagos': 'Pagos',
                        'general': 'General'
                    };
                    
                    const categoriaBg = {
                        'comprar': '#e7f3ff',
                        'vender': '#fff3cd',
                        'envios': '#e7f3ff',
                        'seguridad': '#f0f9f6',
                        'pagos': '#fff3cd',
                        'general': '#f0f9f6'
                    };
                    
                    const categoriaColor = {
                        'comprar': '#0D87A8',
                        'vender': '#856404',
                        'envios': '#0D87A8',
                        'seguridad': '#0C9268',
                        'pagos': '#856404',
                        'general': '#0C9268'
                    };
                    
                    const categoria = data.categoria || 'general';
                    const bgColor = categoriaBg[categoria] || '#f0f9f6';
                    const textColor = categoriaColor[categoria] || '#0C9268';
                    const categoriaNombre = categoriaNombres[categoria] || 'General';
                    
                    // Formatear fecha
                    const fecha = new Date(data.fecha_publicacion);
                    const fechaFormateada = fecha.toLocaleDateString('es-ES', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                    
                    // Actualizar header
                    header.innerHTML = `
                        <h1>${escapeHtml(data.titulo)}</h1>
                        <span class="guia-categoria-badge" style="background: ${bgColor}; color: ${textColor};">
                            ${escapeHtml(categoriaNombre)}
                        </span>
                    `;
                    
                    // Actualizar contenido - el contenido HTML viene de la BD y se renderiza directamente
                    content.innerHTML = `
                        <div class="guia-contenido-completo">
                            ${data.contenido || '<p>No hay contenido disponible.</p>'}
                        </div>
                        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                                <span style="color: #666; font-size: 0.9rem;">
                                    <i class="fas fa-eye" style="color: #0C9268;"></i> ${data.vistas} vistas
                                </span>
                                <span style="color: #666; font-size: 0.9rem;">
                                    <i class="fas fa-calendar" style="color: #0C9268;"></i> ${fechaFormateada}
                                </span>
                            </div>
                        </div>
                    `;
                })
                .catch(error => {
                    console.error('Error:', error);
                    header.innerHTML = '<h1>Error</h1>';
                    content.innerHTML = '<p style="color: #dc3545;">Error al cargar la guía. Por favor, intenta nuevamente.</p>';
                });
        }
        
        // Función para cerrar el modal
        function cerrarModalGuia() {
            const modal = document.getElementById('modalGuia');
            modal.classList.remove('active');
        }
        
        // Cerrar modal al hacer click fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalGuia');
            if (event.target === modal) {
                cerrarModalGuia();
            }
        }
        
        // Cerrar modal con ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModalGuia();
            }
        });
        
        // Función para escapar HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
