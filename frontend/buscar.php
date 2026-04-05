<?php
// Página de resultados de búsqueda
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/auth_middleware.php';

// Obtener usuario actual para verificar favoritos
$usuario_actual = null;
$usuario_id = null;
if (function_exists('is_logged_in') && is_logged_in()) {
    $usuario_actual = get_session_user();
    $usuario_id = $usuario_actual['id'] ?? null;
}

// Función simple para sanitizar input
function sanitize_input($value) {
    if ($value === null || $value === '') {
        return '';
    }
    return trim(strip_tags((string)$value));
}

// Función para sanitizar HTML
function sanitize_html($value) {
    if ($value === null || $value === '') {
        return '';
    }
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}

// Función para sanitizar enteros
function sanitize_int($value, $min = null) {
    if ($value === null || $value === '') {
        return false;
    }
    $int = (int)$value;
    if ($min !== null && $int < $min) {
        return false;
    }
    return $int;
}

// Función para sanitizar floats
function sanitize_float($value) {
    if ($value === null || $value === '') {
        return null;
    }
    $float = (float)$value;
    return $float > 0 ? $float : null;
}

// Obtener y sanitizar parámetros de búsqueda (soporta 'q' y 'query')
$query = sanitize_html($_GET['query'] ?? $_GET['q'] ?? '');
$categoria = sanitize_int($_GET['categoria'] ?? null, 1);
if ($categoria === false) {
    $categoria = null;
}
$precio_min = sanitize_float($_GET['precio_min'] ?? null);
$precio_max = sanitize_float($_GET['precio_max'] ?? null);
$estado = sanitize_html($_GET['estado'] ?? null);
$ubicacion = sanitize_html($_GET['ubicacion'] ?? null);
$orden = sanitize_html($_GET['orden'] ?? 'relevancia');

// Validar orden
$ordenes_validos = ['relevancia', 'precio_asc', 'precio_desc', 'fecha', 'popularidad'];
if (!in_array($orden, $ordenes_validos)) {
    $orden = 'relevancia';
}

// Paginación
$pagina = sanitize_int($_GET['pagina'] ?? 1, 1);
if ($pagina === false) {
    $pagina = 1;
}
$productos_por_pagina = 24;
$offset = ($pagina - 1) * $productos_por_pagina;

// Realizar búsqueda
$productos = [];
$total_resultados = 0;

if (!empty($query)) {
    try {
        error_log("buscar.php: Iniciando búsqueda - Query: '$query', Categoría: " . ($categoria ?? 'null') . ", Offset: $offset");
        
        $productos = buscarProductos($query, $categoria, $precio_min, $precio_max, $estado, $ubicacion, $orden, $productos_por_pagina, $offset);
        $total_resultados = contarResultadosBusqueda($query, $categoria, $precio_min, $precio_max, $estado, $ubicacion);
        
        error_log("buscar.php: Resultados - Productos encontrados: " . count($productos) . ", Total: $total_resultados");
        
        // Si no hay resultados, intentar búsqueda sin filtros para debug
        if (empty($productos) && isset($pdo)) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1 AND vendido = 0");
                $total_activos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                error_log("buscar.php: Total productos activos en BD: $total_activos");
                
                // Verificar si hay productos que contengan el término
                $query_test = '%' . addcslashes($query, '%_\\') . '%';
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM productos WHERE (titulo LIKE ? OR descripcion LIKE ?) AND activo = 1 AND vendido = 0");
                $stmt->execute([$query_test, $query_test]);
                $total_con_termino = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                error_log("buscar.php: Productos con término '$query': $total_con_termino");
            } catch (Exception $e) {
                error_log("buscar.php: Error en debug: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log("Error en buscar.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $productos = [];
        $total_resultados = 0;
    }
}

$total_paginas = $total_resultados > 0 ? ceil($total_resultados / $productos_por_pagina) : 0;
$inicio = $total_resultados > 0 ? $offset + 1 : 0;
$fin = $total_resultados > 0 ? min($offset + $productos_por_pagina, $total_resultados) : 0;

// Obtener categorías para filtros
$categorias = [];
try {
    $categorias = getCategorias();
} catch (Exception $e) {
    error_log("Error al obtener categorías en buscar.php: " . $e->getMessage());
    $categorias = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0D87A8">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo !empty($query) ? 'Búsqueda: ' . htmlspecialchars($query) : 'Buscar productos'; ?> - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .buscar-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
        }
        
        .buscar-header {
            margin-bottom: 2rem;
        }
        
        .buscar-header h1 {
            color: #0D87A8;
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            margin-bottom: 0.5rem;
        }
        
        .resultados-info {
            color: #666;
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .buscar-layout {
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
        
        .filter-option input[type="radio"] {
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
        
        .sort-options {
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            background: #f5f8ff;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .pagination button, .pagination a {
            padding: 0.5rem 1rem;
            border: 1px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }
        
        .pagination button:hover, .pagination a:hover,
        .pagination button.active, .pagination a.active {
            background: #0D87A8;
            color: white;
            border-color: #0D87A8;
        }
        
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        @media (max-width: 968px) {
            .buscar-layout {
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

    <div class="buscar-container">
        <div class="buscar-header">
            <h1>
                <?php if (!empty($query)): ?>
                    <i class="fas fa-search"></i> Resultados para: "<?php echo htmlspecialchars($query); ?>"
                <?php else: ?>
                    <i class="fas fa-search"></i> Buscar productos
                <?php endif; ?>
            </h1>
            
            <!-- Barra de búsqueda -->
            <div id="search-bar" style="display: flex; gap: 0.5rem; background: rgba(255,255,255,0.95); border-radius: 14px; padding: 0.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.12); align-items: center; backdrop-filter: saturate(140%) blur(6px); margin: 1.5rem 0; max-width: 780px; width: 100%; position: relative; border: 2px solid #e0e0e0;">
                <svg style="color: #0D87A8; width: 1.1rem; height: 1.1rem; padding: 0 0.5rem; flex-shrink: 0;" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
                <input id="search-input" type="text" placeholder="Buscar: iPhone, PlayStation, Nike, refrigerador, moto…" value="<?php echo htmlspecialchars($query ?? ''); ?>" style="flex: 1; border: none; outline: none; background: transparent; padding: 0.75rem 0.5rem; font-size: clamp(0.9rem, 2vw, 1rem); min-width: 0;">
                <button type="button" class="cta-button" style="margin: 0; border-radius: 8px; padding: 0.5rem 0.75rem; white-space: nowrap; background: #0D87A8; font-size: clamp(0.8rem, 1.8vw, 0.9rem); flex-shrink: 0; cursor: pointer; font-weight: 500;">
                    Buscar
                </button>
            </div>
            <div id="search-suggestions" style="display:none; position: relative; max-width: 780px; width: 100%; margin: -0.75rem 0 1.5rem;"></div>
            
            <?php if (!empty($query) && $total_resultados > 0): ?>
                <div class="resultados-info">
                    Mostrando <strong><?php echo $inicio; ?>-<?php echo $fin; ?></strong> de <strong><?php echo number_format($total_resultados); ?></strong> resultado<?php echo $total_resultados != 1 ? 's' : ''; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($query)): ?>
            <div style="text-align: center; padding: 4rem; background: white; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                <i class="fas fa-search" style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h2 style="color: #666; margin-bottom: 1rem;">Ingresa un término de búsqueda</h2>
                <p style="color: #999;">Busca productos por nombre, descripción o categoría</p>
            </div>
        <?php else: ?>
            <div class="buscar-layout">
                <!-- Filtros Sidebar -->
                <aside class="filters-sidebar">
                    <form method="GET" action="buscar.php" id="filtersForm">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
                        <?php if ($orden !== 'relevancia'): ?>
                            <input type="hidden" name="orden" value="<?php echo htmlspecialchars($orden); ?>">
                        <?php endif; ?>
                        
                        <div class="filter-section">
                            <h3 class="filter-title"><i class="fas fa-tags"></i> Categorías</h3>
                            <label class="filter-option">
                                <input type="radio" name="categoria" value="" <?php echo !$categoria ? 'checked' : ''; ?> onchange="document.getElementById('filtersForm').submit();">
                                <span>Todas</span>
                            </label>
                            <?php if (!empty($categorias)): ?>
                                <?php foreach ($categorias as $cat): ?>
                                <label class="filter-option">
                                    <input type="radio" name="categoria" value="<?php echo (int)$cat['id']; ?>" <?php echo $categoria == $cat['id'] ? 'checked' : ''; ?> onchange="document.getElementById('filtersForm').submit();">
                                    <span><?php echo htmlspecialchars($cat['nombre']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="filter-section">
                            <h3 class="filter-title"><i class="fas fa-dollar-sign"></i> Precio</h3>
                            <div class="price-inputs">
                                <input type="number" name="precio_min" placeholder="Mín" min="0" step="0.01" value="<?php echo $precio_min ? htmlspecialchars($precio_min) : ''; ?>">
                                <input type="number" name="precio_max" placeholder="Máx" min="0" step="0.01" value="<?php echo $precio_max ? htmlspecialchars($precio_max) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="filter-section">
                            <h3 class="filter-title"><i class="fas fa-star"></i> Estado</h3>
                            <label class="filter-option">
                                <input type="radio" name="estado" value="" <?php echo !$estado ? 'checked' : ''; ?> onchange="document.getElementById('filtersForm').submit();">
                                <span>Todos</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="estado" value="nuevo" <?php echo $estado === 'nuevo' ? 'checked' : ''; ?> onchange="document.getElementById('filtersForm').submit();">
                                <span>Como nuevo</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="estado" value="excelente" <?php echo $estado === 'excelente' ? 'checked' : ''; ?> onchange="document.getElementById('filtersForm').submit();">
                                <span>Excelente</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="estado" value="bueno" <?php echo $estado === 'bueno' ? 'checked' : ''; ?> onchange="document.getElementById('filtersForm').submit();">
                                <span>Muy bueno</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="estado" value="regular" <?php echo $estado === 'regular' ? 'checked' : ''; ?> onchange="document.getElementById('filtersForm').submit();">
                                <span>Regular</span>
                            </label>
                        </div>
                        
                        <div class="filter-section">
                            <h3 class="filter-title"><i class="fas fa-map-marker-alt"></i> Ubicación</h3>
                            <input type="text" name="ubicacion" placeholder="Ciudad o Estado" value="<?php echo $ubicacion ? htmlspecialchars($ubicacion) : ''; ?>" style="width: 100%; padding: 0.5rem; border: 1px solid #e0e0e0; border-radius: 5px;">
                        </div>
                        
                        <button type="submit" class="cta-button" style="width: 100%; margin-top: 1rem;">
                            <i class="fas fa-filter"></i> Aplicar Filtros
                        </button>
                    </form>
                </aside>
                
                <!-- Resultados -->
                <div class="products-section">
                    <div class="products-toolbar">
                        <div class="sort-options">
                            <label>Ordenar por:</label>
                            <select onchange="actualizarOrden(this.value)">
                                <option value="relevancia" <?php echo $orden === 'relevancia' ? 'selected' : ''; ?>>Más relevantes</option>
                                <option value="precio_asc" <?php echo $orden === 'precio_asc' ? 'selected' : ''; ?>>Precio: menor a mayor</option>
                                <option value="precio_desc" <?php echo $orden === 'precio_desc' ? 'selected' : ''; ?>>Precio: mayor a menor</option>
                                <option value="fecha" <?php echo $orden === 'fecha' ? 'selected' : ''; ?>>Más recientes</option>
                                <option value="popularidad" <?php echo $orden === 'popularidad' ? 'selected' : ''; ?>>Más populares</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="products-grid">
                        <?php if (empty($productos)): ?>
                            <div class="no-results">
                                <i class="fas fa-search" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                                <p style="font-size: 1.2rem; margin-bottom: 1rem;">No se encontraron productos</p>
                                <p style="color: #999;">Intenta con otros términos de búsqueda o ajusta los filtros</p>
                            </div>
                        <?php else:
                            foreach ($productos as $producto):
                                $imagen = !empty($producto['imagen_principal']) ? $producto['imagen_principal'] : 'https://placehold.co/400x400?text=Sin+imagen';
                                $precio_formateado = formatearPrecio($producto['precio'] ?? 0, $producto['moneda'] ?? 'MXN');
                                $estado_texto = getEstadoTexto($producto['estado_producto'] ?? 'bueno');
                                $ubicacion_producto = !empty($producto['ubicacion_ciudad']) ? $producto['ubicacion_ciudad'] : (!empty($producto['ubicacion_estado']) ? $producto['ubicacion_estado'] : 'Ubicación no especificada');
                        ?>
                        <?php
                        // Verificar si está en favoritos
                        $es_favorito = false;
                        if ($usuario_id && function_exists('esFavorito')) {
                            $es_favorito = esFavorito($usuario_id, $producto['id']);
                        }
                        $icono_favorito = $es_favorito ? 'fas fa-heart' : 'far fa-heart';
                        $color_favorito = $es_favorito ? '#ff4d4f' : '#0D87A8';
                        ?>
                        <div class="product-card" onclick="window.location.href='producto.php?id=<?php echo (int)$producto['id']; ?>'">
                            <div class="product-image">
                                <img src="<?php echo htmlspecialchars($imagen); ?>" alt="<?php echo htmlspecialchars($producto['titulo'] ?? 'Producto'); ?>" onerror="this.src='https://placehold.co/400x400?text=Sin+imagen'">
                                <?php if (!empty($estado_texto)): ?>
                                <span class="product-badge"><?php echo htmlspecialchars($estado_texto); ?></span>
                                <?php endif; ?>
                                <button onclick="event.stopPropagation(); toggleFavorite(<?php echo (int)$producto['id']; ?>, this);" style="position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.9); border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 3; box-shadow: 0 2px 8px rgba(0,0,0,0.15); transition: all 0.3s ease;" data-producto-id="<?php echo (int)$producto['id']; ?>" title="<?php echo $es_favorito ? 'Quitar de favoritos' : 'Agregar a favoritos'; ?>">
                                    <i class="<?php echo $icono_favorito; ?>" style="color:<?php echo $color_favorito; ?>; font-size: 1.1rem;"></i>
                                </button>
                            </div>
                            <div class="product-info">
                                <h3 class="product-title"><?php echo htmlspecialchars($producto['titulo'] ?? 'Sin título'); ?></h3>
                                <div class="product-price"><?php echo htmlspecialchars($precio_formateado); ?></div>
                                <div class="product-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ubicacion_producto); ?></div>
                            </div>
                        </div>
                        <?php 
                            endforeach;
                        endif; ?>
                    </div>
                    
                    <?php if ($total_paginas > 1): ?>
                    <div class="pagination">
                        <?php if ($pagina > 1): ?>
                        <a href="?q=<?php echo urlencode($query); ?>&pagina=<?php echo $pagina - 1; ?><?php echo $categoria ? '&categoria=' . $categoria : ''; ?><?php echo $precio_min ? '&precio_min=' . $precio_min : ''; ?><?php echo $precio_max ? '&precio_max=' . $precio_max : ''; ?><?php echo $estado ? '&estado=' . urlencode($estado) : ''; ?><?php echo $ubicacion ? '&ubicacion=' . urlencode($ubicacion) : ''; ?><?php echo $orden !== 'relevancia' ? '&orden=' . urlencode($orden) : ''; ?>">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                        <?php endif; ?>
                        
                        <?php
                        $inicio_pag = max(1, $pagina - 2);
                        $fin_pag = min($total_paginas, $pagina + 2);
                        
                        if ($inicio_pag > 1): ?>
                            <a href="?q=<?php echo urlencode($query); ?>&pagina=1<?php echo $categoria ? '&categoria=' . $categoria : ''; ?><?php echo $precio_min ? '&precio_min=' . $precio_min : ''; ?><?php echo $precio_max ? '&precio_max=' . $precio_max : ''; ?><?php echo $estado ? '&estado=' . urlencode($estado) : ''; ?><?php echo $ubicacion ? '&ubicacion=' . urlencode($ubicacion) : ''; ?><?php echo $orden !== 'relevancia' ? '&orden=' . urlencode($orden) : ''; ?>">1</a>
                            <?php if ($inicio_pag > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $inicio_pag; $i <= $fin_pag; $i++): ?>
                        <a href="?q=<?php echo urlencode($query); ?>&pagina=<?php echo $i; ?><?php echo $categoria ? '&categoria=' . $categoria : ''; ?><?php echo $precio_min ? '&precio_min=' . $precio_min : ''; ?><?php echo $precio_max ? '&precio_max=' . $precio_max : ''; ?><?php echo $estado ? '&estado=' . urlencode($estado) : ''; ?><?php echo $ubicacion ? '&ubicacion=' . urlencode($ubicacion) : ''; ?><?php echo $orden !== 'relevancia' ? '&orden=' . urlencode($orden) : ''; ?>" class="<?php echo $i == $pagina ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($fin_pag < $total_paginas): ?>
                            <?php if ($fin_pag < $total_paginas - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?q=<?php echo urlencode($query); ?>&pagina=<?php echo $total_paginas; ?><?php echo $categoria ? '&categoria=' . $categoria : ''; ?><?php echo $precio_min ? '&precio_min=' . $precio_min : ''; ?><?php echo $precio_max ? '&precio_max=' . $precio_max : ''; ?><?php echo $estado ? '&estado=' . urlencode($estado) : ''; ?><?php echo $ubicacion ? '&ubicacion=' . urlencode($ubicacion) : ''; ?><?php echo $orden !== 'relevancia' ? '&orden=' . urlencode($orden) : ''; ?>"><?php echo $total_paginas; ?></a>
                        <?php endif; ?>
                        
                        <?php if ($pagina < $total_paginas): ?>
                        <a href="?q=<?php echo urlencode($query); ?>&pagina=<?php echo $pagina + 1; ?><?php echo $categoria ? '&categoria=' . $categoria : ''; ?><?php echo $precio_min ? '&precio_min=' . $precio_min : ''; ?><?php echo $precio_max ? '&precio_max=' . $precio_max : ''; ?><?php echo $estado ? '&estado=' . urlencode($estado) : ''; ?><?php echo $ubicacion ? '&ubicacion=' . urlencode($ubicacion) : ''; ?><?php echo $orden !== 'relevancia' ? '&orden=' . urlencode($orden) : ''; ?>">
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'components/footer.php'; ?>
    
    <script>
        function actualizarOrden(orden) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('orden', orden);
            urlParams.set('pagina', '1'); // Resetear a página 1 al cambiar orden
            window.location.href = 'buscar.php?' + urlParams.toString();
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
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>
