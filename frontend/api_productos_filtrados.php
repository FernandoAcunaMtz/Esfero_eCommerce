<?php
// Endpoint AJAX para obtener productos filtrados
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db_connection.php';

// Funciones simples de sanitización (sin depender de sanitize.php)
function sanitize_int($value, $min = null) {
    $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    $value = filter_var($value, FILTER_VALIDATE_INT);
    if ($value === false) return false;
    if ($min !== null && $value < $min) return false;
    return $value;
}

function sanitize_float($value) {
    $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    return filter_var($value, FILTER_VALIDATE_FLOAT);
}

function sanitize_html($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Solo aceptar peticiones GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener y sanitizar parámetros
$filtros = [];

// Categorías
if (isset($_GET['categoria'])) {
    if (is_array($_GET['categoria'])) {
        $categorias = [];
        foreach ($_GET['categoria'] as $cat) {
            $cat_id = sanitize_int($cat, 1);
            if ($cat_id !== false) {
                $categorias[] = $cat_id;
            }
        }
        if (!empty($categorias)) {
            $filtros['categoria'] = $categorias;
        }
    } else {
        $cat_id = sanitize_int($_GET['categoria'], 1);
        if ($cat_id !== false) {
            $filtros['categoria'] = [$cat_id];
        }
    }
}

// Precio
$precio_min = sanitize_float($_GET['precio_min'] ?? null);
if ($precio_min !== false && $precio_min > 0) {
    $filtros['precio_min'] = $precio_min;
}

$precio_max = sanitize_float($_GET['precio_max'] ?? null);
if ($precio_max !== false && $precio_max > 0) {
    $filtros['precio_max'] = $precio_max;
}

// Estados
if (isset($_GET['estado'])) {
    if (is_array($_GET['estado'])) {
        $estados = [];
        foreach ($_GET['estado'] as $est) {
            $estado_val = sanitize_html($est);
            if (in_array($estado_val, ['nuevo', 'excelente', 'bueno', 'regular', 'para_repuesto'])) {
                $estados[] = $estado_val;
            }
        }
        if (!empty($estados)) {
            $filtros['estado'] = $estados;
        }
    } else {
        $estado_val = sanitize_html($_GET['estado']);
        if (in_array($estado_val, ['nuevo', 'excelente', 'bueno', 'regular', 'para_repuesto'])) {
            $filtros['estado'] = [$estado_val];
        }
    }
}

// Ubicación
$ubicacion = sanitize_html($_GET['ubicacion'] ?? null);
if ($ubicacion) {
    $filtros['ubicacion_estado'] = $ubicacion;
}

// Ordenamiento
$orden = sanitize_html($_GET['orden'] ?? 'fecha');
$filtros['orden'] = $orden;

// Paginación
$pagina = sanitize_int($_GET['pagina'] ?? 1, 1);
if ($pagina === false) {
    $pagina = 1;
}
$limite = sanitize_int($_GET['limite'] ?? 24, 1);
if ($limite === false) {
    $limite = 24;
}
$offset = ($pagina - 1) * $limite;

// Verificar si es destacados o recientes (acepta true, 1, yes, on)
$destacados_param = $_GET['destacados'] ?? '';
$destacados = !empty($destacados_param) && ($destacados_param === 'true' || $destacados_param === '1' || $destacados_param === 'yes' || $destacados_param === 'on');
$recientes_param = $_GET['recientes'] ?? '';
$recientes = !empty($recientes_param) && ($recientes_param === 'true' || $recientes_param === '1' || $recientes_param === 'yes' || $recientes_param === 'on');

// Agregar destacados y recientes a los filtros si están presentes
if ($destacados) {
    $filtros['destacados'] = true;
}
if ($recientes) {
    $filtros['recientes'] = true;
    $filtros['orden'] = 'fecha'; // Forzar orden por fecha para recientes
}

// Obtener productos usando la función de filtrado (que ahora soporta destacados)
$productos = getProductosFiltrados($filtros, $limite, $offset);
// Asegurar que $productos sea un array
if (!is_array($productos)) {
    $productos = [];
}
$total = contarProductosFiltrados($filtros);
$total_paginas = ceil($total / $limite);

// Formatear productos para respuesta
$productos_formateados = [];
foreach ($productos as $producto) {
    // Usar placeholder local si no hay imagen
    $imagen = !empty($producto['imagen_principal']) ? $producto['imagen_principal'] : getPlaceholderImage(400, 400, 'Sin imagen');
    $precio_formateado = formatearPrecio($producto['precio'], $producto['moneda']);
    $estado_texto = getEstadoTexto($producto['estado_producto']);
    $ubicacion = $producto['ubicacion_ciudad'] ?: $producto['ubicacion_estado'] ?: 'Ubicación no especificada';
    
    $productos_formateados[] = [
        'id' => (int)$producto['id'],
        'titulo' => $producto['titulo'],
        'precio' => $precio_formateado,
        'precio_num' => (float)$producto['precio'],
        'precio_original' => $producto['precio_original'] ? formatearPrecio($producto['precio_original'], $producto['moneda']) : null,
        'imagen' => $imagen,
        'estado' => $estado_texto,
        'ubicacion' => $ubicacion,
        'url' => 'producto.php?id=' . (int)$producto['id']
    ];
}

// Obtener contadores por filtro
$contadores = [
    'total' => $total,
    'por_categoria' => [],
    'por_estado' => []
];

// Contadores por categoría (aplicando otros filtros excepto categoría)
$categorias = getCategorias();
$categorias_validas = array_column($categorias, 'id');
if (!empty($filtros['categoria'])) {
    // Validar que las categorías seleccionadas existan
    $filtros['categoria'] = array_intersect($filtros['categoria'], $categorias_validas);
    if (empty($filtros['categoria'])) {
        unset($filtros['categoria']);
    }
}

foreach ($categorias as $cat) {
    $filtros_cat = $filtros;
    unset($filtros_cat['categoria']); // Remover categorías actuales
    $filtros_cat['categoria'] = [(int)$cat['id']];
    // Mantener otros filtros (precio, ubicación, destacados, recientes, etc.)
    // Asegurar que destacados y recientes se mantengan si estaban presentes
    if ($destacados) {
        $filtros_cat['destacados'] = true;
    }
    if ($recientes) {
        $filtros_cat['recientes'] = true;
    }
    $contadores['por_categoria'][$cat['id']] = contarProductosFiltrados($filtros_cat);
}

// Contadores por estado (aplicando otros filtros excepto estado, pero manteniendo destacados/recientes)
$estados_disponibles = ['nuevo', 'excelente', 'bueno', 'regular', 'para_repuesto'];
foreach ($estados_disponibles as $est) {
    $filtros_est = $filtros;
    unset($filtros_est['estado']); // Remover estados actuales
    $filtros_est['estado'] = [$est];
    // Mantener otros filtros (categoría, precio, ubicación, destacados, recientes, etc.)
    // Asegurar que destacados y recientes se mantengan si estaban presentes
    if ($destacados) {
        $filtros_est['destacados'] = true;
    }
    if ($recientes) {
        $filtros_est['recientes'] = true;
    }
    $contadores['por_estado'][$est] = contarProductosFiltrados($filtros_est);
}

echo json_encode([
    'success' => true,
    'productos' => $productos_formateados,
    'paginacion' => [
        'pagina_actual' => $pagina,
        'total_paginas' => $total_paginas,
        'total_productos' => $total,
        'inicio' => $offset + 1,
        'fin' => min($offset + $limite, $total)
    ],
    'contadores' => $contadores,
    'filtros_aplicados' => $filtros
]);

