<?php
// Manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Cargar conexión a la base de datos y funciones helper
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/auth_middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener usuario actual para verificar favoritos
$usuario_actual = null;
$usuario_id = null;
if (function_exists('is_logged_in') && is_logged_in()) {
    $usuario_actual = get_session_user();
    $usuario_id = $usuario_actual['id'] ?? null;
}

// Funciones simples de sanitización (sin sanitize.php)
function simple_sanitize_html($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function simple_sanitize_int($value, $min = null) {
    $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    $value = filter_var($value, FILTER_VALIDATE_INT);
    if ($value === false) return false;
    if ($min !== null && $value < $min) return false;
    return $value;
}

function simple_sanitize_float($value) {
    $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    return filter_var($value, FILTER_VALIDATE_FLOAT);
}

// Funciones wrapper para simplificar verificaciones
function safe_formatearPrecio($precio, $moneda = 'MXN') {
    return function_exists('formatearPrecio') ? formatearPrecio($precio, $moneda) : '$' . number_format($precio, 2);
}

function safe_getEstadoTexto($estado) {
    return function_exists('getEstadoTexto') ? getEstadoTexto($estado) : 'Buen estado';
}

function safe_getPlaceholderImage($width = 400, $height = 400, $text = 'Sin imagen') {
    if (function_exists('getPlaceholderImage')) {
        try {
            return getPlaceholderImage($width, $height, $text);
        } catch (Exception $e) {
            // Fallback a base64 SVG
        }
    }
    return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTgiIGZpbGw9IiM5OTk5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5TaW4gaW1hZ2VuPC90ZXh0Pjwvc3ZnPg==';
}

// Página de Listado de Productos - Esfero
// Sanitizar y validar parámetros GET
$categoria = null;
if (isset($_GET['categoria']) && $_GET['categoria'] !== '') {
    $categoria_val = simple_sanitize_int($_GET['categoria'], 1);
    if ($categoria_val !== false) {
        $categoria = $categoria_val;
    }
}

// Detectar destacados (acepta true, 1, o cualquier valor no vacío)
$destacados_param = $_GET['destacados'] ?? '';
$destacados = !empty($destacados_param) && ($destacados_param === 'true' || $destacados_param === '1' || $destacados_param === 'yes' || $destacados_param === 'on');

// Detectar recientes (acepta true, 1, o cualquier valor no vacío)
$recientes_param = $_GET['recientes'] ?? '';
$recientes = !empty($recientes_param) && ($recientes_param === 'true' || $recientes_param === '1' || $recientes_param === 'yes' || $recientes_param === 'on');

$pagina = 1;
if (isset($_GET['pagina']) && $_GET['pagina'] !== '') {
    $pagina_val = simple_sanitize_int($_GET['pagina'], 1);
    if ($pagina_val !== false) {
        $pagina = $pagina_val;
    }
}
$limite = 24;
$offset = ($pagina - 1) * $limite;

// Obtener parámetros de filtrado adicionales
$precio_min = (isset($_GET['precio_min']) && $_GET['precio_min'] !== '') ? simple_sanitize_float($_GET['precio_min']) : false;
$precio_max = (isset($_GET['precio_max']) && $_GET['precio_max'] !== '') ? simple_sanitize_float($_GET['precio_max']) : false;
$estados_filtro = [];
if (isset($_GET['estado'])) {
    if (is_array($_GET['estado'])) {
        foreach ($_GET['estado'] as $est) {
            $estado_val = simple_sanitize_html($est);
            if (in_array($estado_val, ['nuevo', 'excelente', 'bueno', 'regular', 'para_repuesto'])) {
                $estados_filtro[] = $estado_val;
            }
        }
    } else {
        $estado_val = simple_sanitize_html($_GET['estado']);
        if (in_array($estado_val, ['nuevo', 'excelente', 'bueno', 'regular', 'para_repuesto'])) {
            $estados_filtro[] = $estado_val;
        }
    }
}

$ubicacion_filtro = simple_sanitize_html($_GET['ubicacion'] ?? null);
$orden = simple_sanitize_html($_GET['orden'] ?? 'fecha');

// Títulos según el tipo de página
if ($destacados) {
    $titulo = 'Productos Destacados';
    $subtitulo = 'Descubre las mejores ofertas y productos populares';
    
    // Inicializar variables para evitar errores
    $productos_por_precio = [];
    $productos_mas_vendidos = [];
    $productos_tendencia = [];
    
    // Obtener productos destacados básicos
    $productos = [];
    $total_productos = 0;
    $total_paginas = 0;
    
    if (function_exists('getProductosDestacados')) {
        try {
            $productos = getProductosDestacados($limite, $offset);
            if (!is_array($productos)) {
                $productos = [];
            }
        } catch (Exception $e) {
            $productos = [];
        }
    }
    
    if (function_exists('contarProductosDestacados')) {
        try {
            $total_productos = contarProductosDestacados();
            $total_paginas = $total_productos > 0 ? ceil($total_productos / $limite) : 0;
        } catch (Exception $e) {
            $total_productos = 0;
            $total_paginas = 0;
        }
    }
} else {
    // Construir filtros
    $filtros = [];
    if ($categoria) {
        $filtros['categoria'] = [$categoria];
    }
    if ($precio_min !== false && $precio_min > 0) {
        $filtros['precio_min'] = $precio_min;
    }
    if ($precio_max !== false && $precio_max > 0) {
        $filtros['precio_max'] = $precio_max;
    }
    if (!empty($estados_filtro)) {
        $filtros['estado'] = $estados_filtro;
    }
    if ($ubicacion_filtro) {
        $filtros['ubicacion_estado'] = $ubicacion_filtro;
    }
    
    // Si es recientes, forzar orden por fecha
    if ($recientes) {
        $titulo = 'Productos Recientes';
        $subtitulo = 'Los últimos productos publicados';
        $filtros['orden'] = 'fecha'; // Forzar orden por fecha
    } else {
        // Si hay categoría, mostrar nombre de la categoría
        if ($categoria) {
            $categoria_info = getCategoriaById($categoria);
            if ($categoria_info) {
                $titulo = $categoria_info['nombre'];
                $subtitulo = $categoria_info['descripcion'] ?: 'Productos en esta categoría';
            } else {
                $titulo = 'Productos';
                $subtitulo = 'Encuentra lo que buscas';
            }
        } else {
            $titulo = 'Productos';
            $subtitulo = 'Encuentra lo que buscas';
        }
        $filtros['orden'] = $orden;
    }
    
    // Siempre usar función de filtros si hay categoría o filtros aplicados
    if (!empty($filtros) || $categoria) {
        // Si hay categoría pero no está en filtros, agregarla
        if ($categoria && empty($filtros['categoria'])) {
            $filtros['categoria'] = [$categoria];
        }
        $productos = getProductosFiltrados($filtros, $limite, $offset);
        $total_productos = contarProductosFiltrados($filtros);
    } else {
        // Solo si no hay filtros ni categoría, usar función simple
        $orden_final = $recientes ? 'fecha' : $orden;
        $productos = getProductos(null, $limite, $offset, $orden_final);
        global $pdo;
        $sql_count = "SELECT COUNT(*) as total FROM productos WHERE activo = 1 AND vendido = 0";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute();
        $total_productos = $stmt_count->fetch()['total'];
    }
    $total_paginas = ceil($total_productos / $limite);
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
    <title><?php echo $titulo; ?> - Esfero</title>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* Scrollbar personalizado para el sidebar de filtros */
        #filtersSidebar::-webkit-scrollbar {
            width: 6px;
        }
        #filtersSidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        #filtersSidebar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        #filtersSidebar::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Responsive para secciones destacadas */
        @media (max-width: 768px) {
            .portfolio-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)) !important;
                gap: 1rem !important;
            }
            
            .portfolio-item h3 {
                font-size: 0.9rem !important;
                min-height: 2rem !important;
            }
            
            .portfolio-item p {
                font-size: 1rem !important;
            }
        }
        
        @media (max-width: 480px) {
            .portfolio-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.75rem !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <!-- Hero -->
    <section class="page-hero" style="background: linear-gradient(135deg, #044E65 0%, #0D87A8 50%, #0C9268 100%); padding: 6rem 0 4rem; text-align: center; color: white;">
        <div class="container">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem;"><?php echo $titulo; ?></h1>
            <p style="font-size: 1.2rem; opacity: 0.9;"><?php echo $subtitulo; ?></p>
        </div>
    </section>

    <?php if ($destacados): ?>
    <!-- Secciones Especiales de Destacados - TEMPORALMENTE COMENTADO PARA DEBUG -->
    <section class="sections" style="padding: 3rem 0; background: var(--c-bg, #F2F9FB);">
        <div class="container">

            <!-- Lista básica de productos destacados (funcionalidad original) -->
            <?php if (!empty($productos)): ?>
            <div class="portfolio-grid" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1.5rem;">
                <?php foreach ($productos as $producto): 
                    if (!isset($producto['id']) || !isset($producto['titulo'])) continue;
                    $imagen = !empty($producto['imagen_principal']) ? htmlspecialchars($producto['imagen_principal']) : '';
                    $precio = isset($producto['precio']) ? (float)$producto['precio'] : 0;
                    $precio_formateado = '$' . number_format($precio, 2);
                    $estado_texto = 'Buen estado';
                    if (isset($producto['estado_producto'])) {
                        $estados = ['nuevo' => 'Como nuevo', 'excelente' => 'Excelente', 'bueno' => 'Muy bueno', 'regular' => 'Buen estado', 'para_repuesto' => 'Para repuesto'];
                        $estado_texto = isset($estados[$producto['estado_producto']]) ? $estados[$producto['estado_producto']] : 'Buen estado';
                    }
                    $ubicacion = !empty($producto['ubicacion_ciudad']) ? htmlspecialchars($producto['ubicacion_ciudad']) : (!empty($producto['ubicacion_estado']) ? htmlspecialchars($producto['ubicacion_estado']) : 'Ubicación no especificada');
                    
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
                        <img src="<?php echo $imagen; ?>" alt="<?php echo htmlspecialchars($producto['titulo']); ?>" loading="lazy" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#8BB4C0;"><i class="fas fa-image" style="font-size:2rem;"></i></div>
                        <?php endif; ?>
                        <span style="position:absolute; top:10px; left:10px; background:rgba(11,45,60,0.72); backdrop-filter:blur(4px); color:white; padding:0.2rem 0.5rem; border-radius:6px; font-size:0.72rem; font-weight:600; z-index:2;"><?php echo htmlspecialchars($estado_texto); ?></span>
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
                        </div>
                        <p class="card-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ubicacion); ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: #666;">
                <p style="font-size: 1.1rem;">No hay productos destacados disponibles.</p>
            </div>
            <?php endif; ?>
            
        </div>
    </section>
    
    <?php else: ?>
    <!-- Filtros y Productos (Layout normal para no-destacados) -->
    <section class="sections" style="padding: 3rem 0;">
        <div class="container">
            <!-- Botón para abrir filtros en móvil -->
            <button id="mobileFiltersToggle" style="display: none; width: 100%; padding: 0.75rem; background: #0C9268; color: white; border: none; border-radius: 8px; font-weight: 600; margin-bottom: 1.5rem; cursor: pointer; font-size: 1rem;">
                <i class="fas fa-filter"></i> Filtros
            </button>
            
            <!-- Overlay para filtros móvil -->
            <div id="mobileFiltersOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100vh; background: rgba(0,0,0,0.5); z-index: 9998; opacity: 0; transition: opacity 0.3s ease;"></div>
            
            <div style="display: grid; grid-template-columns: 220px 1fr; gap: 1.5rem;" id="productsLayout">
                
                <!-- Sidebar de Filtros -->
                <aside id="filtersSidebar" style="background: #EEF8FA; padding: 1rem; border-radius: 12px; height: fit-content; max-height: calc(100vh - 120px); overflow-y: auto; position: sticky; top: 80px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">Filtros</h3>
                        <button id="mobileFiltersClose" style="display: none; background: none; border: none; font-size: 1.5rem; color: #666; cursor: pointer; padding: 0.25rem;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <form method="GET" action="productos.php" id="filtersForm">
                        <?php if ($destacados): ?>
                            <input type="hidden" name="destacados" value="1">
                        <?php endif; ?>
                        <?php if ($recientes): ?>
                            <input type="hidden" name="recientes" value="1">
                        <?php endif; ?>
                        <?php if ($categoria): ?>
                            <input type="hidden" name="categoria" value="<?php echo (int)$categoria; ?>">
                        <?php endif; ?>
                        
                        <!-- Rango de Precio -->
                        <div style="margin-bottom: 1rem;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Precio</h4>
                            <div style="display: flex; gap: 0.4rem; align-items: center;">
                                <input type="number" name="precio_min" placeholder="Mín" min="0" 
                                       value="<?php echo $precio_min !== false ? htmlspecialchars($precio_min) : ''; ?>" 
                                       style="width: 100%; padding: 0.4rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.85rem;">
                                <span style="font-size: 0.85rem; color: #666;">-</span>
                                <input type="number" name="precio_max" placeholder="Máx" min="0" 
                                       value="<?php echo $precio_max !== false ? htmlspecialchars($precio_max) : ''; ?>" 
                                       style="width: 100%; padding: 0.4rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.85rem;">
                            </div>
                        </div>
                        
                        <!-- Estado del Producto -->
                        <div style="margin-bottom: 1rem;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Estado</h4>
                            <label style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem; cursor: pointer; font-size: 0.85rem;">
                                <div>
                                    <input type="checkbox" name="estado[]" value="nuevo" 
                                           class="filter-checkbox" data-filter="estado"
                                           <?php echo in_array('nuevo', $estados_filtro) ? 'checked' : ''; ?>> Como nuevo
                                </div>
                                <span class="filter-count" data-estado="nuevo" style="color: #666; font-size: 0.75rem;">(0)</span>
                            </label>
                            <label style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem; cursor: pointer; font-size: 0.85rem;">
                                <div>
                                    <input type="checkbox" name="estado[]" value="excelente" 
                                           class="filter-checkbox" data-filter="estado"
                                           <?php echo in_array('excelente', $estados_filtro) ? 'checked' : ''; ?>> Excelente
                                </div>
                                <span class="filter-count" data-estado="excelente" style="color: #666; font-size: 0.75rem;">(0)</span>
                            </label>
                            <label style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem; cursor: pointer; font-size: 0.85rem;">
                                <div>
                                    <input type="checkbox" name="estado[]" value="bueno" 
                                           class="filter-checkbox" data-filter="estado"
                                           <?php echo in_array('bueno', $estados_filtro) ? 'checked' : ''; ?>> Muy bueno
                                </div>
                                <span class="filter-count" data-estado="bueno" style="color: #666; font-size: 0.75rem;">(0)</span>
                            </label>
                            <label style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem; cursor: pointer; font-size: 0.85rem;">
                                <div>
                                    <input type="checkbox" name="estado[]" value="regular" 
                                           class="filter-checkbox" data-filter="estado"
                                           <?php echo in_array('regular', $estados_filtro) ? 'checked' : ''; ?>> Buen estado
                                </div>
                                <span class="filter-count" data-estado="regular" style="color: #666; font-size: 0.75rem;">(0)</span>
                            </label>
                        </div>
                        
                        <!-- Ubicación -->
                        <div style="margin-bottom: 1rem;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Ubicación</h4>
                            <input type="text" name="ubicacion" placeholder="Estado o Ciudad" 
                                   value="<?php echo $ubicacion_filtro ? htmlspecialchars($ubicacion_filtro) : ''; ?>" 
                                   style="width: 100%; padding: 0.4rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.85rem;">
                        </div>
                        
                        <input type="hidden" name="orden" id="ordenInput" value="<?php echo htmlspecialchars($orden); ?>">
                        
                        <button type="submit" id="applyFiltersBtn" style="width: 100%; padding: 0.6rem; background: #0C9268; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.3s ease; margin-top: 0.5rem;">
                            Aplicar Filtros
                        </button>
                        <a href="productos.php<?php echo $destacados ? '?destacados=1' : ($recientes ? '?recientes=1' : ($categoria ? '?categoria=' . $categoria : '')); ?>" 
                           style="width: 100%; padding: 0.6rem; background: #f0f0f0; color: #666; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.3s ease; display: block; text-align: center; text-decoration: none; margin-top: 0.4rem;">
                            Limpiar Filtros
                        </a>
                    </form>
                </aside>
                
                <!-- Grid de Productos -->
                <div>
                    <?php if (empty($productos)): ?>
                        <div style="text-align: center; padding: 3rem; color: #666;">
                            <p style="font-size: 1.1rem;">No hay productos disponibles.</p>
                        </div>
                    <?php else: ?>
                        <div class="portfolio-grid" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1.5rem;">
                            <?php foreach ($productos as $producto): 
                                if (!isset($producto['id']) || !isset($producto['titulo'])) continue;
                            
                            // Preparar datos del producto
                            $imagen = !empty($producto['imagen_principal']) && filter_var($producto['imagen_principal'], FILTER_VALIDATE_URL) 
                                ? $producto['imagen_principal'] 
                                : safe_getPlaceholderImage(400, 400, 'Sin imagen');
                            $precio = isset($producto['precio']) ? (float)$producto['precio'] : 0;
                            $moneda = isset($producto['moneda']) ? $producto['moneda'] : 'MXN';
                            $precio_formateado = safe_formatearPrecio($precio, $moneda);
                            $estado_producto = isset($producto['estado_producto']) ? $producto['estado_producto'] : 'bueno';
                            $estado_texto = safe_getEstadoTexto($estado_producto);
                            
                            $ubicacion = !empty($producto['ubicacion_ciudad']) ? $producto['ubicacion_ciudad'] : (!empty($producto['ubicacion_estado']) ? $producto['ubicacion_estado'] : 'Ubicación no especificada');
                            
                            // Verificar si está en favoritos
                            $es_favorito = false;
                            if ($usuario_id && function_exists('esFavorito')) {
                                $es_favorito = esFavorito($usuario_id, $producto['id']);
                            }
                            $icono_favorito = $es_favorito ? 'fas fa-heart' : 'far fa-heart';
                            $color_favorito = $es_favorito ? '#ff4d4f' : '#0D87A8';
                        ?>
                        <a href="producto.php?id=<?php echo $producto['id']; ?>" class="portfolio-item animate-in" style="background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); text-decoration: none; color: inherit; display: block; opacity: 1 !important; transform: translateY(0) scale(1) !important; transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-4px) scale(1)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)';">
                            <div style="position: relative; overflow: hidden; border-radius: 12px 12px 0 0; aspect-ratio: 1/1; background: #f5f8ff;">
                                <img src="<?php echo htmlspecialchars($imagen); ?>" alt="<?php echo htmlspecialchars($producto['titulo']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;" onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTgiIGZpbGw9IiM5OTk5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5TaW4gaW1hZ2VuPC90ZXh0Pjwvc3ZnPg==';">
                                <span style="position: absolute; top: 10px; left: 10px; background: #0C9268; color: white; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; z-index: 2;"><?php echo htmlspecialchars($estado_texto); ?></span>
                                <button onclick="event.stopPropagation(); event.preventDefault(); toggleFavorite(<?php echo (int)$producto['id']; ?>, this);" style="position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.9); border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 3; box-shadow: 0 2px 8px rgba(0,0,0,0.15); transition: all 0.3s ease;" data-producto-id="<?php echo (int)$producto['id']; ?>" title="<?php echo $es_favorito ? 'Quitar de favoritos' : 'Agregar a favoritos'; ?>">
                                    <i class="<?php echo $icono_favorito; ?>" style="color:<?php echo $color_favorito; ?>; font-size: 1.1rem;"></i>
                                </button>
                                <?php 
                                if (isset($producto['precio_original']) && isset($producto['precio']) && 
                                    is_numeric($producto['precio_original']) && is_numeric($producto['precio']) &&
                                    (float)$producto['precio_original'] > (float)$producto['precio'] && 
                                    (float)$producto['precio_original'] > 0): 
                                    $precio_orig = (float)$producto['precio_original'];
                                    $precio_act = (float)$producto['precio'];
                                    $descuento = round((($precio_orig - $precio_act) / $precio_orig) * 100);
                                ?>
                                <span style="position: absolute; top: 10px; right: 10px; background: #F6A623; color: white; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; z-index: 2;">-<?php echo (int)$descuento; ?>%</span>
                                <?php endif; ?>
                            </div>
                            <div style="padding: 1rem; background: #ffffff;">
                                <h3 style="font-size: 1rem; margin-bottom: 0.5rem; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; color: #333; font-weight: 600; min-height: 2.5rem;"><?php echo htmlspecialchars($producto['titulo']); ?></h3>
                                <div style="display: flex; align-items: baseline; gap: 0.5rem; margin-bottom: 0.5rem;">
                                    <p style="font-size: 1.25rem; font-weight: 700; color: #0C9268; margin: 0;"><?php echo $precio_formateado; ?></p>
                                    <?php 
                                    if (isset($producto['precio_original']) && isset($producto['precio']) && 
                                        is_numeric($producto['precio_original']) && is_numeric($producto['precio']) &&
                                        (float)$producto['precio_original'] > (float)$producto['precio']): 
                                        $precio_orig = (float)$producto['precio_original'];
                                        $moneda_orig = isset($producto['moneda']) ? $producto['moneda'] : 'MXN';
                                        $precio_orig_formateado = safe_formatearPrecio($precio_orig, $moneda_orig);
                                    ?>
                                    <p style="font-size: 0.9rem; color: #9aa5b1; text-decoration: line-through; margin: 0;"><?php echo htmlspecialchars($precio_orig_formateado); ?></p>
                                    <?php endif; ?>
                                </div>
                                <p style="font-size: 0.85rem; color: #666; margin: 0;"><i class="fas fa-map-marker-alt" style="color: #0C9268;"></i> <?php echo htmlspecialchars($ubicacion); ?></p>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <?php endif; ?>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script>
        let currentPage = <?php echo $pagina; ?>;
        let isLoading = false;
        const isDestacados = <?php echo $destacados ? 'true' : 'false'; ?>;
        const isRecientes = <?php echo $recientes ? 'true' : 'false'; ?>;
        const categoriaId = <?php echo $categoria ? (int)$categoria : 'null'; ?>;
        
        // Función para obtener filtros actuales
        function getCurrentFilters() {
            const form = document.getElementById('filtersForm');
            const formData = new FormData(form);
            const filters = {};
            
            // Mantener parámetros de página
            if (categoriaId) {
                filters.categoria = [categoriaId];
            }
            
            // Categorías adicionales (si hay)
            const categorias = formData.getAll('categoria[]');
            if (categorias.length > 0) {
                if (filters.categoria) {
                    filters.categoria = [...filters.categoria, ...categorias];
                } else {
                    filters.categoria = categorias;
                }
            }
            
            // Precio
            const precioMin = formData.get('precio_min');
            if (precioMin && precioMin > 0) {
                filters.precio_min = precioMin;
            }
            const precioMax = formData.get('precio_max');
            if (precioMax && precioMax > 0) {
                filters.precio_max = precioMax;
            }
            
            // Estados
            const estados = formData.getAll('estado[]');
            if (estados.length > 0) {
                filters.estado = estados;
            }
            
            // Ubicación
            const ubicacion = formData.get('ubicacion');
            if (ubicacion && ubicacion.trim()) {
                filters.ubicacion = ubicacion.trim();
            }
            
            // Orden
            filters.orden = document.getElementById('ordenInput').value || 'fecha';
            
            return filters;
        }
        
        // Función para construir URL de API
        function buildApiUrl(filters, page = 1) {
            const params = new URLSearchParams();
            params.append('pagina', page);
            params.append('limite', 24);
            
            // Agregar destacados o recientes si aplica
            if (isDestacados) {
                params.append('destacados', '1');
            }
            if (isRecientes) {
                params.append('recientes', '1');
            }
            
            if (categoriaId) {
                params.append('categoria[]', categoriaId);
            }
            if (filters.categoria) {
                filters.categoria.forEach(cat => params.append('categoria[]', cat));
            }
            if (filters.precio_min) params.append('precio_min', filters.precio_min);
            if (filters.precio_max) params.append('precio_max', filters.precio_max);
            if (filters.estado) {
                filters.estado.forEach(est => params.append('estado[]', est));
            }
            if (filters.ubicacion) params.append('ubicacion', filters.ubicacion);
            if (filters.orden) params.append('orden', filters.orden);
            
            return 'api_productos_filtrados.php?' + params.toString();
        }
        
        // Función para cargar productos vía AJAX
        function loadProducts(filters, page = 1) {
            if (isLoading) return; // Permitir AJAX para destacados también
            
            isLoading = true;
            currentPage = page;
            
            const loadingIndicator = document.getElementById('loadingIndicator');
            const productsGrid = document.getElementById('productsGrid');
            const resultsCount = document.getElementById('resultsCount');
            const paginationContainer = document.getElementById('paginationContainer');
            
            // Mostrar loading
            if (loadingIndicator) loadingIndicator.style.display = 'block';
            if (productsGrid) productsGrid.style.opacity = '0.5';
            
            // Timeout para la petición
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 15000);
            
            fetch(buildApiUrl(filters, page), { signal: controller.signal })
                .then(response => {
                    clearTimeout(timeoutId);
                    
                    if (response.status === 401 || response.status === 403) {
                        alert('Tu sesión ha expirado. Por favor, inicia sesión nuevamente.');
                        window.location.href = 'login.php';
                        return null;
                    }
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const contentType = response.headers.get("content-type");
                    if (!contentType || !contentType.includes("application/json")) {
                        throw new Error("Respuesta no es JSON");
                    }
                    
                    return response.json();
                })
                .then(data => {
                    if (!data) return;
                    if (data.success) {
                        // Actualizar productos
                        updateProductsGrid(data.productos);
                        
                        // Actualizar contador
                        updateResultsCount(data.paginacion);
                        
                        // Actualizar paginación
                        updatePagination(data.paginacion, filters);
                        
                        // Actualizar contadores de filtros
                        updateFilterCounts(data.contadores);
                        
                        // Actualizar URL sin recargar
                        updateURL(filters, page);
                    }
                })
                .catch(error => {
                    clearTimeout(timeoutId);
                    console.error('Error cargando productos:', error);
                    
                    if (productsGrid) {
                        let errorMessage = 'Error al cargar productos. Por favor, intenta de nuevo.';
                        if (error.name === 'AbortError') {
                            errorMessage = 'La solicitud tardó demasiado. Por favor, verifica tu conexión e intenta de nuevo.';
                        } else if (error.message.includes('JSON')) {
                            errorMessage = 'Error en la respuesta del servidor. Por favor, recarga la página.';
                        }
                        
                        productsGrid.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #666;"><p>${errorMessage}</p><button onclick="location.reload()" style="padding: 0.75rem 1.5rem; background: #0C9268; color: white; border: none; border-radius: 8px; cursor: pointer; margin-top: 1rem;">Recargar Página</button></div>`;
                    }
                })
                .finally(() => {
                    isLoading = false;
                    if (loadingIndicator) loadingIndicator.style.display = 'none';
                    if (productsGrid) productsGrid.style.opacity = '1';
                });
        }
        
        // Actualizar grid de productos
        function updateProductsGrid(productos) {
            const grid = document.getElementById('productsGrid');
            if (!grid) return;
            
            if (productos.length === 0) {
                grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #666;"><p style="font-size: 1.2rem; margin-bottom: 1rem;">No hay productos disponibles con estos filtros.</p></div>';
                return;
            }
            
            let html = '';
            productos.forEach(producto => {
                const descuento = producto.precio_original && producto.precio_num < parseFloat(producto.precio_original.replace(/[^0-9.]/g, '')) ? 
                    Math.round(((parseFloat(producto.precio_original.replace(/[^0-9.]/g, '')) - producto.precio_num) / parseFloat(producto.precio_original.replace(/[^0-9.]/g, ''))) * 100) : 0;
                
                html += `
                    <a href="${producto.url}" class="portfolio-item animate-in" style="background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); text-decoration: none; color: inherit; display: block; opacity: 1 !important; transform: translateY(0) scale(1) !important; transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-4px) scale(1)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)';">
                        <div style="position: relative; overflow: hidden; border-radius: 12px 12px 0 0; aspect-ratio: 1/1; background: #f5f8ff;">
                            <img src="${escapeHtml(producto.imagen)}" alt="${escapeHtml(producto.titulo)}" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;" onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZTBlMGUwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNiIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSI+U2luIGltYWdlbjwvdGV4dD48L3N2Zz4=';">
                            <span style="position: absolute; top: 10px; left: 10px; background: #0C9268; color: white; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; z-index: 2;">${escapeHtml(producto.estado)}</span>
                            ${descuento > 0 ? `<span style="position: absolute; top: 10px; right: 10px; background: #F6A623; color: white; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; z-index: 2;">-${descuento}%</span>` : ''}
                            <button onclick="event.stopPropagation(); event.preventDefault(); toggleFavorite(${producto.id}, this);" style="position: absolute; top: 10px; right: ${descuento > 0 ? '50px' : '10px'}; background: rgba(255,255,255,0.9); border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 3; box-shadow: 0 2px 8px rgba(0,0,0,0.15); transition: all 0.3s ease;" data-producto-id="${producto.id}" title="Agregar a favoritos">
                                <i class="far fa-heart" style="color:#0D87A8; font-size: 1.1rem;"></i>
                            </button>
                        </div>
                        <div style="padding: 1rem; background: #ffffff;">
                            <h3 style="font-size: 1rem; margin-bottom: 0.5rem; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; color: #333; font-weight: 600; min-height: 2.5rem;">${escapeHtml(producto.titulo)}</h3>
                            <div style="display: flex; align-items: baseline; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <p style="font-size: 1.25rem; font-weight: 700; color: #0C9268; margin: 0;">${escapeHtml(producto.precio)}</p>
                                ${producto.precio_original ? `<p style="font-size: 0.9rem; color: #9aa5b1; text-decoration: line-through; margin: 0;">${escapeHtml(producto.precio_original)}</p>` : ''}
                            </div>
                            <p style="font-size: 0.85rem; color: #666; margin: 0;"><i class="fas fa-map-marker-alt" style="color: #0C9268;"></i> ${escapeHtml(producto.ubicacion)}</p>
                        </div>
                    </a>
                `;
            });
            
            grid.innerHTML = html;
        }
        
        // Actualizar contador de resultados
        function updateResultsCount(paginacion) {
            const resultsCount = document.getElementById('resultsCount');
            if (resultsCount) {
                resultsCount.innerHTML = `Mostrando ${paginacion.inicio}-${paginacion.fin} de ${paginacion.total_productos} productos`;
            }
        }
        
        // Actualizar paginación
        function updatePagination(paginacion, filters) {
            const container = document.getElementById('paginationContainer');
            if (!container) return;
            
            if (paginacion.total_paginas <= 1) {
                container.style.display = 'none';
                return;
            }
            
            container.style.display = 'flex';
            
            let html = '';
            const inicio = Math.max(1, paginacion.pagina_actual - 2);
            const fin = Math.min(paginacion.total_paginas, paginacion.pagina_actual + 2);
            
            if (paginacion.pagina_actual > 1) {
                html += `<a href="#" onclick="event.preventDefault(); loadProducts(getCurrentFilters(), ${paginacion.pagina_actual - 1});" style="padding: 0.5rem 1rem; border: 1px solid #ddd; background: white; border-radius: 8px; cursor: pointer; text-decoration: none; color: inherit;">«</a>`;
            }
            
            for (let i = inicio; i <= fin; i++) {
                const activeStyle = i === paginacion.pagina_actual ? 
                    'border: 1px solid #0C9268; background: #0C9268; color: white;' : 
                    'border: 1px solid #ddd; background: white; color: inherit;';
                html += `<a href="#" onclick="event.preventDefault(); loadProducts(getCurrentFilters(), ${i});" style="padding: 0.5rem 1rem; ${activeStyle} border-radius: 8px; cursor: pointer; text-decoration: none;">${i}</a>`;
            }
            
            if (paginacion.pagina_actual < paginacion.total_paginas) {
                html += `<a href="#" onclick="event.preventDefault(); loadProducts(getCurrentFilters(), ${paginacion.pagina_actual + 1});" style="padding: 0.5rem 1rem; border: 1px solid #ddd; background: white; border-radius: 8px; cursor: pointer; text-decoration: none; color: inherit;">»</a>`;
            }
            
            container.innerHTML = html;
        }
        
        // Actualizar contadores de filtros
        function updateFilterCounts(contadores) {
            // Contadores por estado
            if (contadores.por_estado) {
                Object.keys(contadores.por_estado).forEach(estado => {
                    const countElement = document.querySelector(`.filter-count[data-estado="${estado}"]`);
                    if (countElement) {
                        countElement.textContent = `(${contadores.por_estado[estado]})`;
                    }
                });
            }
        }
        
        // Actualizar URL sin recargar
        function updateURL(filters, page) {
            const params = new URLSearchParams();
            if (categoriaId) params.append('categoria', categoriaId);
            if (isDestacados) params.append('destacados', '1');
            if (isRecientes) params.append('recientes', '1');
            if (page > 1) params.append('pagina', page);
            
            if (filters.categoria && !categoriaId) {
                filters.categoria.forEach(cat => params.append('categoria[]', cat));
            }
            if (filters.precio_min) params.append('precio_min', filters.precio_min);
            if (filters.precio_max) params.append('precio_max', filters.precio_max);
            if (filters.estado) {
                filters.estado.forEach(est => params.append('estado[]', est));
            }
            if (filters.ubicacion) params.append('ubicacion', filters.ubicacion);
            if (filters.orden && filters.orden !== 'fecha') params.append('orden', filters.orden);
            
            const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.pushState({}, '', newURL);
        }
        
        // Función de escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Función para aplicar ordenamiento
        function applySort(ordenValue) {
            document.getElementById('ordenInput').value = ordenValue;
            const filters = getCurrentFilters();
            loadProducts(filters, 1);
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Permitir AJAX para todos los casos
            
            // Cargar contadores iniciales
            const filters = getCurrentFilters();
            fetch(buildApiUrl(filters, currentPage))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateFilterCounts(data.contadores);
                    }
                });
            
            // Botón aplicar filtros - Recargar página con filtros en URL
            const filtersForm = document.getElementById('filtersForm');
            
            if (filtersForm) {
                filtersForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Construir URL con los filtros del formulario
                    const formData = new FormData(filtersForm);
                    const params = new URLSearchParams();
                    
                    // Mantener destacados/recientes si aplica
                    if (isDestacados) {
                        params.append('destacados', '1');
                    }
                    if (isRecientes) {
                        params.append('recientes', '1');
                    }
                    
                    // Mantener categoría principal si existe
                    if (categoriaId) {
                        params.append('categoria', categoriaId);
                    }
                    
                    // Categorías adicionales
                    const categorias = formData.getAll('categoria[]');
                    categorias.forEach(cat => {
                        if (!categoriaId || cat != categoriaId) {
                            params.append('categoria[]', cat);
                        }
                    });
                    
                    // Precio
                    const precioMin = formData.get('precio_min');
                    if (precioMin && precioMin > 0) {
                        params.append('precio_min', precioMin);
                    }
                    const precioMax = formData.get('precio_max');
                    if (precioMax && precioMax > 0) {
                        params.append('precio_max', precioMax);
                    }
                    
                    // Estados
                    const estados = formData.getAll('estado[]');
                    estados.forEach(est => params.append('estado[]', est));
                    
                    // Ubicación
                    const ubicacion = formData.get('ubicacion');
                    if (ubicacion && ubicacion.trim()) {
                        params.append('ubicacion', ubicacion.trim());
                    }
                    
                    // Orden
                    const orden = formData.get('orden') || 'fecha';
                    if (orden !== 'fecha') {
                        params.append('orden', orden);
                    }
                    
                    // Recargar página con los parámetros
                    const newURL = 'productos.php' + (params.toString() ? '?' + params.toString() : '');
                    window.location.href = newURL;
                });
            }
            
            // Auto-aplicar al cambiar precio (con debounce)
            let priceTimeout;
            document.querySelectorAll('input[name="precio_min"], input[name="precio_max"]').forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(priceTimeout);
                    priceTimeout = setTimeout(() => {
                        const filters = getCurrentFilters();
                        loadProducts(filters, 1);
                    }, 1000);
                });
            });
            
            // Auto-aplicar al cambiar ubicación (con debounce)
            const ubicacionInput = document.querySelector('input[name="ubicacion"]');
            if (ubicacionInput) {
                let ubicacionTimeout;
                ubicacionInput.addEventListener('input', function() {
                    clearTimeout(ubicacionTimeout);
                    ubicacionTimeout = setTimeout(() => {
                        const filters = getCurrentFilters();
                        loadProducts(filters, 1);
                    }, 1000);
                });
            }
        });
    
    // Responsividad de filtros móvil
    (function() {
        const filtersToggle = document.getElementById('mobileFiltersToggle');
        const filtersOverlay = document.getElementById('mobileFiltersOverlay');
        const filtersSidebar = document.getElementById('filtersSidebar');
        const filtersClose = document.getElementById('mobileFiltersClose');
        const productsLayout = document.getElementById('productsLayout');
        
        function checkScreenSize() {
            if (window.innerWidth <= 768) {
                // Modo móvil
                filtersToggle.style.display = 'block';
                filtersSidebar.style.display = 'none';
                productsLayout.style.gridTemplateColumns = '1fr';
                
                // Convertir sidebar en drawer
                filtersSidebar.style.position = 'fixed';
                filtersSidebar.style.top = '0';
                filtersSidebar.style.right = '-100%';
                filtersSidebar.style.width = '85%';
                filtersSidebar.style.maxWidth = '320px';
                filtersSidebar.style.height = '100vh';
                filtersSidebar.style.overflowY = 'auto';
                filtersSidebar.style.zIndex = '9999';
                filtersSidebar.style.borderRadius = '0';
                filtersSidebar.style.transition = 'right 0.3s ease';
                filtersClose.style.display = 'block';
            } else {
                // Modo desktop
                filtersToggle.style.display = 'none';
                filtersSidebar.style.display = 'block';
                productsLayout.style.gridTemplateColumns = '220px 1fr';
                
                // Restaurar sidebar normal
                filtersSidebar.style.position = 'sticky';
                filtersSidebar.style.top = '80px';
                filtersSidebar.style.right = 'auto';
                filtersSidebar.style.width = 'auto';
                filtersSidebar.style.maxWidth = 'none';
                filtersSidebar.style.height = 'fit-content';
                filtersSidebar.style.overflowY = 'visible';
                filtersSidebar.style.zIndex = 'auto';
                filtersSidebar.style.borderRadius = '12px';
                filtersClose.style.display = 'none';
                filtersOverlay.style.display = 'none';
                filtersOverlay.classList.remove('active');
                filtersSidebar.classList.remove('active');
            }
        }
        
        function openFilters() {
            filtersOverlay.style.display = 'block';
            setTimeout(() => {
                filtersOverlay.classList.add('active');
                filtersOverlay.style.opacity = '1';
            }, 10);
            filtersSidebar.style.display = 'block';
            setTimeout(() => {
                filtersSidebar.classList.add('active');
                filtersSidebar.style.right = '0';
            }, 10);
            document.body.style.overflow = 'hidden';
        }
        
        function closeFilters() {
            filtersOverlay.classList.remove('active');
            filtersOverlay.style.opacity = '0';
            filtersSidebar.classList.remove('active');
            filtersSidebar.style.right = '-100%';
            setTimeout(() => {
                if (window.innerWidth <= 768) {
                    filtersOverlay.style.display = 'none';
                    filtersSidebar.style.display = 'none';
                }
            }, 300);
            document.body.style.overflow = '';
        }
        
        if (filtersToggle) {
            filtersToggle.addEventListener('click', openFilters);
        }
        
        if (filtersClose) {
            filtersClose.addEventListener('click', closeFilters);
        }
        
        if (filtersOverlay) {
            filtersOverlay.addEventListener('click', closeFilters);
        }
        
        // Verificar tamaño al cargar y al redimensionar
        checkScreenSize();
        window.addEventListener('resize', checkScreenSize);
        
        // Cerrar con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && window.innerWidth <= 768) {
                closeFilters();
            }
        });
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
</body>
</html>


