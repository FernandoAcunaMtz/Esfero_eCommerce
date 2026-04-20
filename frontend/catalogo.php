<?php
// Habilitar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Cargar conexión a la base de datos y funciones helper
try {
    require_once __DIR__ . '/includes/db_connection.php';
    require_once __DIR__ . '/includes/auth_middleware.php';
} catch (Exception $e) {
    error_log("Error al cargar db_connection.php: " . $e->getMessage());
    // Continuar aunque falle la BD (para ver si es el problema)
}

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

// Funciones simples de sanitización (sin depender de sanitize.php)
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

function simple_sanitize_html($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Catálogo de Productos - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .catalogo-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
        }
        
        .catalogo-header {
            margin-bottom: 2rem;
        }
        
        .catalogo-header h1 {
            color: #0D87A8;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .breadcrumb {
            display: flex;
            gap: 0.5rem;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .breadcrumb a {
            color: #0D87A8;
            text-decoration: none;
        }
        
        .catalogo-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
        }
        
        .filters-sidebar {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .filter-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .filter-section:last-child {
            border-bottom: none;
        }
        
        .filter-title {
            color: #0D87A8;
            font-weight: bold;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .filter-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
            cursor: pointer;
        }
        
        .filter-option input {
            cursor: pointer;
        }
        
        .price-inputs {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .price-inputs input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
        }
        
        .products-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .products-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .results-count {
            color: #666;
        }
        
        .sort-options {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .sort-options select {
            padding: 0.5rem 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.5rem;
        }

        /* Skeleton loader */
        @keyframes shimmer {
            0%   { background-position: -600px 0; }
            100% { background-position: 600px 0; }
        }
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 600px 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 6px;
        }
        .skeleton-card {
            background: white;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
        }
        .skeleton-img  { height: 200px; }
        .skeleton-body { padding: 1rem; }
        .skeleton-line { height: 14px; margin-bottom: 10px; }
        .skeleton-line.short { width: 60%; }
        .skeleton-line.price { height: 20px; width: 40%; margin-top: 6px; }
        
        .product-card {
            background: white;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #0D87A8, #0C9268);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            position: relative;
        }
        
        .product-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #0C9268;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .wishlist-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .wishlist-btn:hover {
            background: white;
            transform: scale(1.1);
        }
        
        .product-info {
            padding: 1rem;
        }
        
        .product-title {
            color: #0D87A8;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #0C9268;
            margin-bottom: 0.5rem;
        }
        
        .product-location {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: #F6A623;
            font-size: 0.85rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination button {
            padding: 0.5rem 1rem;
            border: 1px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .pagination button:hover, .pagination button.active {
            background: #0D87A8;
            color: white;
            border-color: #0D87A8;
        }
        
        @media (max-width: 968px) {
            .catalogo-layout {
                grid-template-columns: 1fr;
            }
            
            .filters-sidebar {
                position: static;
            }
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="catalogo-container">
        <div class="catalogo-header">
            <div class="breadcrumb">
                <a href="index.php">Inicio</a>
                <span>/</span>
                <span>Catálogo</span>
            </div>
            <h1>Catálogo de Productos</h1>
            <p style="color: #666;">Encuentra las mejores ofertas de segunda mano</p>
        </div>
        
        <?php
        // Obtener categorías para filtros (con manejo de errores)
        $categorias_disponibles = [];
        try {
            if (function_exists('getCategorias')) {
                $categorias_disponibles = getCategorias();
                if (!is_array($categorias_disponibles)) {
                    $categorias_disponibles = [];
                }
            }
        } catch (Exception $e) {
            error_log("Error al obtener categorías en catalogo.php: " . $e->getMessage());
            $categorias_disponibles = [];
        }
        
        // Obtener parámetros de filtrado (usando funciones simples)
        $categorias_filtro = [];
        if (isset($_GET['categoria'])) {
            if (is_array($_GET['categoria'])) {
                foreach ($_GET['categoria'] as $cat) {
                    $cat_id = simple_sanitize_int($cat, 1);
                    if ($cat_id !== false) {
                        $categorias_filtro[] = $cat_id;
                    }
                }
            } else {
                $cat_id = simple_sanitize_int($_GET['categoria'], 1);
                if ($cat_id !== false) {
                    $categorias_filtro[] = $cat_id;
                }
            }
        }
        
        $precio_min = simple_sanitize_float($_GET['precio_min'] ?? null);
        $precio_max = simple_sanitize_float($_GET['precio_max'] ?? null);
        $estados_filtro = [];
        if (isset($_GET['estado'])) {
            $estados_validos = ['nuevo', 'excelente', 'bueno', 'regular', 'para_repuesto'];
            if (is_array($_GET['estado'])) {
                foreach ($_GET['estado'] as $est) {
                    $estado_val = strip_tags(trim($est));
                    if (in_array($estado_val, $estados_validos)) {
                        $estados_filtro[] = $estado_val;
                    }
                }
            } else {
                $estado_val = strip_tags(trim($_GET['estado']));
                if (in_array($estado_val, $estados_validos)) {
                    $estados_filtro[] = $estado_val;
                }
            }
        }
        
        $ubicacion_filtro = strip_tags(trim($_GET['ubicacion'] ?? ''));
        $orden = strip_tags(trim($_GET['orden'] ?? 'fecha'));
        if (!in_array($orden, ['fecha', 'precio_asc', 'precio_desc', 'popularidad'])) {
            $orden = 'fecha';
        }
        
        // Paginación
        $pagina_actual = simple_sanitize_int($_GET['pagina'] ?? 1, 1);
        if ($pagina_actual === false) {
            $pagina_actual = 1;
        }
        $productos_por_pagina = 24;
        $offset = ($pagina_actual - 1) * $productos_por_pagina;
        
        // Construir array de filtros
        $filtros = [];
        if (!empty($categorias_filtro)) {
            $filtros['categoria'] = $categorias_filtro;
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
        $filtros['orden'] = $orden;
        
        // Obtener productos con filtros (con manejo de errores)
        $productos = [];
        $total_productos = 0;
        $total_paginas = 0;
        try {
            if (function_exists('getProductosFiltrados')) {
                $productos = getProductosFiltrados($filtros, $productos_por_pagina, $offset);
                if (!is_array($productos)) {
                    $productos = [];
                }
            }
            if (function_exists('contarProductosFiltrados')) {
                $total_productos = contarProductosFiltrados($filtros);
                $total_paginas = ceil($total_productos / $productos_por_pagina);
            }
        } catch (Exception $e) {
            error_log("Error al obtener productos en catalogo.php: " . $e->getMessage());
            $productos = [];
            $total_productos = 0;
            $total_paginas = 0;
        }
        
        // Calcular rango mostrado
        $inicio = $offset + 1;
        $fin = min($offset + $productos_por_pagina, $total_productos);
        ?>
        
        <div class="catalogo-layout">
            <!-- Filtros Sidebar -->
            <aside class="filters-sidebar">
                <form method="GET" action="catalogo.php" id="filtersForm">
                    <div class="filter-section">
                        <h3 class="filter-title">Categorías</h3>
                        <?php foreach ($categorias_disponibles as $cat): ?>
                        <label class="filter-option">
                            <input type="checkbox" name="categoria[]" value="<?php echo (int)$cat['id']; ?>" 
                                   class="filter-checkbox" data-filter="categoria"
                                   <?php echo in_array($cat['id'], $categorias_filtro) ? 'checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($cat['nombre']); ?></span>
                            <span class="filter-count" data-categoria="<?php echo (int)$cat['id']; ?>" style="margin-left: auto; color: #666; font-size: 0.85rem;">(0)</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="filter-section">
                        <h3 class="filter-title">Precio</h3>
                        <div class="price-inputs">
                            <input type="number" name="precio_min" placeholder="Mín" min="0" 
                                   value="<?php echo $precio_min !== false ? htmlspecialchars($precio_min) : ''; ?>">
                            <input type="number" name="precio_max" placeholder="Máx" min="0" 
                                   value="<?php echo $precio_max !== false ? htmlspecialchars($precio_max) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="filter-section">
                        <h3 class="filter-title">Estado</h3>
                        <label class="filter-option">
                            <input type="checkbox" name="estado[]" value="nuevo" 
                                   class="filter-checkbox" data-filter="estado"
                                   <?php echo in_array('nuevo', $estados_filtro) ? 'checked' : ''; ?>>
                            <span>Como nuevo</span>
                            <span class="filter-count" data-estado="nuevo" style="margin-left: auto; color: #666; font-size: 0.85rem;">(0)</span>
                        </label>
                        <label class="filter-option">
                            <input type="checkbox" name="estado[]" value="excelente" 
                                   class="filter-checkbox" data-filter="estado"
                                   <?php echo in_array('excelente', $estados_filtro) ? 'checked' : ''; ?>>
                            <span>Excelente</span>
                            <span class="filter-count" data-estado="excelente" style="margin-left: auto; color: #666; font-size: 0.85rem;">(0)</span>
                        </label>
                        <label class="filter-option">
                            <input type="checkbox" name="estado[]" value="bueno" 
                                   class="filter-checkbox" data-filter="estado"
                                   <?php echo in_array('bueno', $estados_filtro) ? 'checked' : ''; ?>>
                            <span>Muy bueno</span>
                            <span class="filter-count" data-estado="bueno" style="margin-left: auto; color: #666; font-size: 0.85rem;">(0)</span>
                        </label>
                        <label class="filter-option">
                            <input type="checkbox" name="estado[]" value="regular" 
                                   class="filter-checkbox" data-filter="estado"
                                   <?php echo in_array('regular', $estados_filtro) ? 'checked' : ''; ?>>
                            <span>Buen estado</span>
                            <span class="filter-count" data-estado="regular" style="margin-left: auto; color: #666; font-size: 0.85rem;">(0)</span>
                        </label>
                    </div>
                    
                    <div class="filter-section">
                        <h3 class="filter-title">Ubicación</h3>
                        <input type="text" name="ubicacion" placeholder="Estado o Ciudad" 
                               value="<?php echo $ubicacion_filtro ? htmlspecialchars($ubicacion_filtro) : ''; ?>" 
                               style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 5px;">
                    </div>
                    
                    <input type="hidden" name="orden" id="ordenInput" value="<?php echo htmlspecialchars($orden); ?>">
                    
                    <button type="submit" id="applyFiltersBtn" class="cta-button" style="width: 100%; margin-top: 1rem;">
                        Aplicar Filtros
                    </button>
                    <a href="catalogo.php" class="cta-button" style="width: 100%; margin-top: 0.5rem; display: block; text-align: center; background: #f0f0f0; color: #666; text-decoration: none;">
                        Limpiar Filtros
                    </a>
                </form>
            </aside>
            
            <!-- Productos -->
            <div class="products-section">
                <div class="products-toolbar">
                    <div class="results-count" id="resultsCount">
                        Mostrando <strong><?php echo $inicio; ?>-<?php echo $fin; ?></strong> de <strong><?php echo $total_productos; ?></strong> productos
                    </div>
                    <div class="sort-options">
                        <label>Ordenar por:</label>
                        <select id="sortSelect" onchange="applySort(this.value)">
                            <option value="fecha" <?php echo $orden === 'fecha' ? 'selected' : ''; ?>>Más recientes</option>
                            <option value="precio_asc" <?php echo $orden === 'precio_asc' ? 'selected' : ''; ?>>Precio: menor a mayor</option>
                            <option value="precio_desc" <?php echo $orden === 'precio_desc' ? 'selected' : ''; ?>>Precio: mayor a menor</option>
                            <option value="popularidad" <?php echo $orden === 'popularidad' ? 'selected' : ''; ?>>Más populares</option>
                        </select>
                    </div>
                </div>
                
                <div id="loadingIndicator" style="display: none; text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #0C9268;"></i>
                    <p style="margin-top: 1rem; color: #666;">Cargando productos...</p>
                </div>
                
                <div class="products-grid" id="productsGrid">
                    <?php if (empty($productos)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #666;">
                            <p style="font-size: 1.2rem; margin-bottom: 1rem;">No hay productos disponibles en este momento.</p>
                            <a href="publicar_producto.php" class="cta-button" style="display: inline-block;">Publicar un producto</a>
                        </div>
                    <?php else:
                        foreach ($productos as $producto):
                            $imagen = $producto['imagen_principal'] ?: 'https://placehold.co/400x400?text=Sin+imagen';
                            $precio_formateado = formatearPrecio($producto['precio'], $producto['moneda']);
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
                    <div class="product-card" onclick="window.location.href='producto.php?id=<?php echo (int)$producto['id']; ?>'">
                        <div class="product-image" style="background: #f5f8ff; display: flex; align-items: center; justify-content: center; height: 200px; overflow: hidden; position: relative;">
                            <img src="<?php echo htmlspecialchars($imagen, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($producto['titulo'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https://placehold.co/400x400?text=Sin+imagen'">
                            <span class="product-badge"><?php echo htmlspecialchars($estado_texto, ENT_QUOTES, 'UTF-8'); ?></span>
                            <button class="wishlist-btn" onclick="event.stopPropagation(); toggleFavorite(<?php echo (int)$producto['id']; ?>, this);" data-producto-id="<?php echo (int)$producto['id']; ?>" title="<?php echo $es_favorito ? 'Quitar de favoritos' : 'Agregar a favoritos'; ?>">
                                <i class="<?php echo $icono_favorito; ?>" style="color:<?php echo $color_favorito; ?>;"></i>
                            </button>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($producto['titulo'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="product-price"><?php echo htmlspecialchars($precio_formateado, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="product-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ubicacion, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="product-rating">
                                <?php
                                $estrellas_llenas = floor($calificacion);
                                $media_estrella = ($calificacion - $estrellas_llenas) >= 0.5;
                                for ($i = 0; $i < 5; $i++):
                                    if ($i < $estrellas_llenas):
                                ?>
                                <i class="fas fa-star"></i>
                                <?php elseif ($i == $estrellas_llenas && $media_estrella): ?>
                                <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                <i class="far fa-star"></i>
                                <?php endif; endfor; ?>
                                <span style="color:#666;"><?php echo $calificacion > 0 ? $calificacion : 'Nuevo'; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    endif; ?>
                </div>
                
                <div class="pagination" id="paginationContainer" style="<?php echo $total_paginas <= 1 ? 'display: none;' : ''; ?>">
                    <?php 
                    // Construir query string para paginación
                    $query_params = [];
                    if (!empty($categorias_filtro)) {
                        foreach ($categorias_filtro as $cat) {
                            $query_params[] = 'categoria[]=' . urlencode($cat);
                        }
                    }
                    if ($precio_min !== false && $precio_min > 0) {
                        $query_params[] = 'precio_min=' . urlencode($precio_min);
                    }
                    if ($precio_max !== false && $precio_max > 0) {
                        $query_params[] = 'precio_max=' . urlencode($precio_max);
                    }
                    if (!empty($estados_filtro)) {
                        foreach ($estados_filtro as $est) {
                            $query_params[] = 'estado[]=' . urlencode($est);
                        }
                    }
                    if ($ubicacion_filtro) {
                        $query_params[] = 'ubicacion=' . urlencode($ubicacion_filtro);
                    }
                    if ($orden !== 'fecha') {
                        $query_params[] = 'orden=' . urlencode($orden);
                    }
                    $query_string = !empty($query_params) ? '&' . implode('&', $query_params) : '';
                    ?>
                    <?php if ($pagina_actual > 1): ?>
                    <a href="?pagina=<?php echo $pagina_actual - 1; ?><?php echo $query_string; ?>" style="text-decoration: none; color: inherit;">
                        <button>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $inicio_pag = max(1, $pagina_actual - 2);
                    $fin_pag = min($total_paginas, $pagina_actual + 2);
                    
                    for ($i = $inicio_pag; $i <= $fin_pag; $i++):
                    ?>
                    <a href="?pagina=<?php echo $i; ?><?php echo $query_string; ?>" style="text-decoration: none; color: inherit;">
                        <button class="<?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </button>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($pagina_actual < $total_paginas): ?>
                    <a href="?pagina=<?php echo $pagina_actual + 1; ?><?php echo $query_string; ?>" style="text-decoration: none; color: inherit;">
                        <button>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script>
        let currentPage = <?php echo $pagina_actual; ?>;
        let isLoading = false;
        
        // Función para obtener filtros actuales
        function getCurrentFilters() {
            const form = document.getElementById('filtersForm');
            const formData = new FormData(form);
            const filters = {};
            
            // Categorías
            const categorias = formData.getAll('categoria[]');
            if (categorias.length > 0) {
                filters.categoria = categorias;
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
            if (isLoading) return;
            
            isLoading = true;
            currentPage = page;
            
            const loadingIndicator = document.getElementById('loadingIndicator');
            const productsGrid = document.getElementById('productsGrid');
            const resultsCount = document.getElementById('resultsCount');
            const paginationContainer = document.getElementById('paginationContainer');
            
            // Mostrar skeleton loaders
            loadingIndicator.style.display = 'none';
            productsGrid.innerHTML = Array(6).fill(null).map(() => `
                <div class="skeleton-card">
                    <div class="skeleton skeleton-img"></div>
                    <div class="skeleton-body">
                        <div class="skeleton skeleton-line"></div>
                        <div class="skeleton skeleton-line short"></div>
                        <div class="skeleton skeleton-line price"></div>
                    </div>
                </div>`).join('');
            
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
                    
                    let errorMessage = 'Error al cargar productos. Por favor, intenta de nuevo.';
                    if (error.name === 'AbortError') {
                        errorMessage = 'La solicitud tardó demasiado. Por favor, verifica tu conexión e intenta de nuevo.';
                    } else if (error.message.includes('JSON')) {
                        errorMessage = 'Error en la respuesta del servidor. Por favor, recarga la página.';
                    }
                    
                    productsGrid.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #666;"><p>${errorMessage}</p><button onclick="location.reload()" style="padding: 0.75rem 1.5rem; background: #0C9268; color: white; border: none; border-radius: 8px; cursor: pointer; margin-top: 1rem;">Recargar Página</button></div>`;
                })
                .finally(() => {
                    isLoading = false;
                });
        }
        
        // Actualizar grid de productos
        function updateProductsGrid(productos) {
            const grid = document.getElementById('productsGrid');
            
            if (productos.length === 0) {
                grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #666;"><p style="font-size: 1.2rem; margin-bottom: 1rem;">No hay productos disponibles con estos filtros.</p><a href="publicar_producto.php" class="cta-button" style="display: inline-block;">Publicar un producto</a></div>';
                return;
            }
            
            let html = '';
            productos.forEach(producto => {
                html += `
                    <div class="product-card" onclick="window.location.href='${producto.url}'">
                        <div class="product-image" style="background: #f5f8ff; display: flex; align-items: center; justify-content: center; height: 200px; overflow: hidden; position: relative;">
                            <img src="${escapeHtml(producto.imagen)}" alt="${escapeHtml(producto.titulo)}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https://placehold.co/400x400?text=Sin+imagen'">
                            <span class="product-badge">${escapeHtml(producto.estado)}</span>
                            <button class="wishlist-btn" onclick="event.stopPropagation(); toggleFavorite(${producto.id}, this);" data-producto-id="${producto.id}" title="Agregar a favoritos">
                                <i class="far fa-heart" style="color:#0D87A8;"></i>
                            </button>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title">${escapeHtml(producto.titulo)}</h3>
                            <div class="product-price">${escapeHtml(producto.precio)}</div>
                            <div class="product-location"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(producto.ubicacion)}</div>
                        </div>
                    </div>
                `;
            });
            
            grid.innerHTML = html;
        }
        
        // Actualizar contador de resultados
        function updateResultsCount(paginacion) {
            const resultsCount = document.getElementById('resultsCount');
            resultsCount.innerHTML = `Mostrando <strong>${paginacion.inicio}-${paginacion.fin}</strong> de <strong>${paginacion.total_productos}</strong> productos`;
        }
        
        // Actualizar paginación
        function updatePagination(paginacion, filters) {
            const container = document.getElementById('paginationContainer');
            
            if (paginacion.total_paginas <= 1) {
                container.style.display = 'none';
                return;
            }
            
            container.style.display = 'flex';
            
            let html = '';
            const inicio = Math.max(1, paginacion.pagina_actual - 2);
            const fin = Math.min(paginacion.total_paginas, paginacion.pagina_actual + 2);
            
            if (paginacion.pagina_actual > 1) {
                html += `<button onclick="loadProducts(getCurrentFilters(), ${paginacion.pagina_actual - 1})"><i class="fas fa-chevron-left"></i></button>`;
            }
            
            for (let i = inicio; i <= fin; i++) {
                const activeClass = i === paginacion.pagina_actual ? 'active' : '';
                html += `<button class="${activeClass}" onclick="loadProducts(getCurrentFilters(), ${i})">${i}</button>`;
            }
            
            if (paginacion.pagina_actual < paginacion.total_paginas) {
                html += `<button onclick="loadProducts(getCurrentFilters(), ${paginacion.pagina_actual + 1})"><i class="fas fa-chevron-right"></i></button>`;
            }
            
            container.innerHTML = html;
        }
        
        // Actualizar contadores de filtros
        function updateFilterCounts(contadores) {
            // Contadores por categoría
            if (contadores.por_categoria) {
                Object.keys(contadores.por_categoria).forEach(catId => {
                    const countElement = document.querySelector(`.filter-count[data-categoria="${catId}"]`);
                    if (countElement) {
                        countElement.textContent = `(${contadores.por_categoria[catId]})`;
                    }
                });
            }
            
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
            if (page > 1) params.append('pagina', page);
            
            if (filters.categoria) {
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
        
        // Función para aplicar ordenamiento
        function applySort(ordenValue) {
            document.getElementById('ordenInput').value = ordenValue;
            const filters = getCurrentFilters();
            loadProducts(filters, 1);
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
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
            const applyBtn = document.getElementById('applyFiltersBtn');
            const filtersForm = document.getElementById('filtersForm');
            
            if (filtersForm) {
                filtersForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Construir URL con los filtros del formulario
                    const formData = new FormData(filtersForm);
                    const params = new URLSearchParams();
                    
                    // Categorías
                    const categorias = formData.getAll('categoria[]');
                    categorias.forEach(cat => params.append('categoria[]', cat));
                    
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
                    const newURL = 'catalogo.php' + (params.toString() ? '?' + params.toString() : '');
                    window.location.href = newURL;
                });
            }
            
            // Auto-aplicar filtros al cambiar checkboxes (opcional, comentado para que sea manual)
            // document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
            //     checkbox.addEventListener('change', function() {
            //         const filters = getCurrentFilters();
            //         loadProducts(filters, 1);
            //     });
            // });
            
            // Auto-aplicar al cambiar precio (con debounce)
            let priceTimeout;
            document.querySelectorAll('input[name="precio_min"], input[name="precio_max"]').forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(priceTimeout);
                    priceTimeout = setTimeout(() => {
                        const filters = getCurrentFilters();
                        loadProducts(filters, 1);
                    }, 1000); // 1 segundo de debounce
                });
            });
            
            // Auto-aplicar al cambiar ubicación (con debounce)
            let ubicacionTimeout;
            document.querySelector('input[name="ubicacion"]').addEventListener('input', function() {
                clearTimeout(ubicacionTimeout);
                ubicacionTimeout = setTimeout(() => {
                    const filters = getCurrentFilters();
                    loadProducts(filters, 1);
                }, 1000);
            });
        });
    </script>
</body>
</html>


