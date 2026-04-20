<?php
/**
 * Conexión a la base de datos MySQL
 * Esfero - Marketplace
 */

// Garantizar charset UTF-8 en la respuesta HTTP
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// Cargar variables de entorno
require_once __DIR__ . '/../../config/load_env.php';

// Configuración de base de datos
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'esfero';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASSWORD') ?: '';
$db_port = getenv('DB_PORT') ?: '3306';
$db_charset = getenv('DB_CHARSET') ?: 'utf8mb4';

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=$db_charset";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
} catch (PDOException $e) {
    // En lugar de die(), establecer $pdo como null y registrar el error
    // Esto permite que el script continúe para operaciones que no requieren BD
    $pdo = null;
    error_log("Error de conexión a la base de datos: " . $e->getMessage());
    
    // Solo mostrar error fatal si estamos en modo debug y es crítico
    if (getenv('APP_DEBUG') === 'true') {
        // En desarrollo, podemos mostrar el error pero no detener la ejecución
        // para operaciones que no requieren BD (como registro/login con API)
    }
}

// Función helper para ejecutar queries
function query($sql, $params = []) {
    global $pdo;
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (\Throwable $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

// Función para obtener un usuario por ID
function getUserById($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND estado = 'activo'");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Función para obtener un usuario por email
function getUserByEmail($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND estado = 'activo'");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

// Función para obtener productos destacados
function getProductosDestacados($limite = 8, $offset = 0) {
    global $pdo;
    if (!$pdo) return [];

    try {
        $sql = "SELECT p.*, 
                u.nombre as vendedor_nombre,
                0 as vendedor_calificacion,
                (SELECT url_imagen FROM imagenes_productos 
                 WHERE producto_id = p.id AND es_principal = 1 
                 LIMIT 1) as imagen_principal
                FROM productos p
                LEFT JOIN usuarios u ON p.vendedor_id = u.id
                WHERE p.activo = 1 
                AND p.vendido = 0
                AND (p.destacado = 1 OR p.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 30 DAY))
                ORDER BY p.destacado DESC, p.fecha_publicacion DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($sql);
        if (!$stmt) {
            error_log("Error preparando consulta en getProductosDestacados: " . implode(", ", $pdo->errorInfo()));
            return [];
        }
        
        $stmt->execute([$limite, $offset]);
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Validar y limpiar resultados
        if (!is_array($resultado)) {
            error_log("getProductosDestacados: resultado no es array");
            return [];
        }
        
        // Filtrar productos inválidos
        $productos_validos = [];
        foreach ($resultado as $producto) {
            if (is_array($producto) && isset($producto['id']) && isset($producto['titulo'])) {
                $productos_validos[] = $producto;
            }
        }
        
        return $productos_validos;
    } catch (Exception $e) {
        error_log("Error en getProductosDestacados: " . $e->getMessage() . " en línea " . $e->getLine());
        return [];
    } catch (PDOException $e) {
        error_log("Error PDO en getProductosDestacados: " . $e->getMessage());
        return [];
    }
}

// Función para contar productos destacados
function contarProductosDestacados() {
    global $pdo;
    
    $sql = "SELECT COUNT(*) as total 
            FROM productos p
            WHERE p.activo = 1 
            AND p.vendido = 0
            AND (p.destacado = 1 OR p.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 30 DAY))";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch();
    return (int)$result['total'];
}

// Función para obtener productos por $1500 o menos
function getProductosPorPrecio($precio_max = 1500, $limite = 12) {
    global $pdo;
    
    // Verificar que $pdo esté disponible
    if (!isset($pdo) || $pdo === null) {
        error_log("Error en getProductosPorPrecio: PDO no está disponible");
        return [];
    }
    
    try {
        $sql = "SELECT p.*, 
                u.nombre as vendedor_nombre,
                0 as vendedor_calificacion,
                (SELECT url_imagen FROM imagenes_productos 
                 WHERE producto_id = p.id AND es_principal = 1 
                 LIMIT 1) as imagen_principal
                FROM productos p
                LEFT JOIN usuarios u ON p.vendedor_id = u.id
                WHERE p.activo = 1 
                AND p.vendido = 0
                AND p.precio <= ?
                AND (p.destacado = 1 OR p.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 30 DAY))
                ORDER BY p.destacado DESC, p.precio ASC
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$precio_max, $limite]);
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!is_array($resultado)) {
            return [];
        }
        
        $productos_validos = [];
        foreach ($resultado as $producto) {
            if (is_array($producto) && isset($producto['id']) && isset($producto['titulo'])) {
                $productos_validos[] = $producto;
            }
        }
        
        return $productos_validos;
    } catch (Exception $e) {
        error_log("Error en getProductosPorPrecio: " . $e->getMessage());
        return [];
    }
}

// Función para obtener productos más vendidos
function getProductosMasVendidos($limite = 12) {
    global $pdo;
    
    // Verificar que $pdo esté disponible
    if (!isset($pdo) || $pdo === null) {
        error_log("Error en getProductosMasVendidos: PDO no está disponible");
        return [];
    }
    
    try {
        $sql = "SELECT p.*, 
                u.nombre as vendedor_nombre,
                0 as vendedor_calificacion,
                (SELECT url_imagen FROM imagenes_productos 
                 WHERE producto_id = p.id AND es_principal = 1 
                 LIMIT 1) as imagen_principal
                FROM productos p
                LEFT JOIN usuarios u ON p.vendedor_id = u.id
                WHERE p.activo = 1 
                AND p.vendido = 0
                AND p.ventas_count > 0
                ORDER BY p.ventas_count DESC, p.fecha_publicacion DESC
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limite]);
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!is_array($resultado)) {
            return [];
        }
        
        $productos_validos = [];
        foreach ($resultado as $producto) {
            if (is_array($producto) && isset($producto['id']) && isset($producto['titulo'])) {
                $productos_validos[] = $producto;
            }
        }
        
        return $productos_validos;
    } catch (Exception $e) {
        error_log("Error en getProductosMasVendidos: " . $e->getMessage());
        return [];
    }
}

// Función para obtener productos en tendencia (últimos comprados)
function getProductosEnTendencia($limite = 12) {
    global $pdo;
    
    // Verificar que $pdo esté disponible
    if (!isset($pdo) || $pdo === null) {
        error_log("Error en getProductosEnTendencia: PDO no está disponible");
        return [];
    }
    
    try {
        $sql = "SELECT p.*, 
                u.nombre as vendedor_nombre,
                0 as vendedor_calificacion,
                (SELECT url_imagen FROM imagenes_productos 
                 WHERE producto_id = p.id AND es_principal = 1 
                 LIMIT 1) as imagen_principal
                FROM productos p
                LEFT JOIN usuarios u ON p.vendedor_id = u.id
                WHERE p.activo = 1 
                AND p.vendido = 1
                AND p.fecha_vendido IS NOT NULL
                ORDER BY p.fecha_vendido DESC
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limite]);
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!is_array($resultado)) {
            return [];
        }
        
        $productos_validos = [];
        foreach ($resultado as $producto) {
            if (is_array($producto) && isset($producto['id']) && isset($producto['titulo'])) {
                $productos_validos[] = $producto;
            }
        }
        
        return $productos_validos;
    } catch (Exception $e) {
        error_log("Error en getProductosEnTendencia: " . $e->getMessage());
        return [];
    }
}

// Función para obtener todos los productos activos
function getProductos($categoria = null, $limite = 24, $offset = 0, $orden = 'fecha') {
    global $pdo;
    
    $sql = "SELECT p.*, 
            u.nombre as vendedor_nombre,
            0 as vendedor_calificacion,
            (SELECT url_imagen FROM imagenes_productos 
             WHERE producto_id = p.id AND es_principal = 1 
             LIMIT 1) as imagen_principal
            FROM productos p
            LEFT JOIN usuarios u ON p.vendedor_id = u.id
            WHERE p.activo = 1 AND p.vendido = 0";
    
    $params = [];
    
    if ($categoria && $categoria !== 'todos') {
        $sql .= " AND p.categoria_id = ?";
        $params[] = $categoria;
    }
    
    // Ordenamiento
    switch ($orden) {
        case 'precio_asc':
            $sql .= " ORDER BY p.precio ASC";
            break;
        case 'precio_desc':
            $sql .= " ORDER BY p.precio DESC";
            break;
        case 'popularidad':
            $sql .= " ORDER BY p.vistas DESC, p.favoritos_count DESC";
            break;
        case 'fecha':
        default:
            $sql .= " ORDER BY p.fecha_publicacion DESC";
            break;
    }
    
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limite;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Función para obtener productos con filtros avanzados
function getProductosFiltrados($filtros = [], $limite = 24, $offset = 0) {
    global $pdo;
    
    $sql = "SELECT p.*, 
            u.nombre as vendedor_nombre,
            0 as vendedor_calificacion,
            (SELECT url_imagen FROM imagenes_productos 
             WHERE producto_id = p.id AND es_principal = 1 
             LIMIT 1) as imagen_principal
            FROM productos p
            LEFT JOIN usuarios u ON p.vendedor_id = u.id
            WHERE p.activo = 1 AND p.vendido = 0";
    
    $params = [];
    
    // Filtro por categoría
    if (!empty($filtros['categoria'])) {
        $categoria = is_array($filtros['categoria']) ? $filtros['categoria'] : [$filtros['categoria']];
        $placeholders = implode(',', array_fill(0, count($categoria), '?'));
        $sql .= " AND p.categoria_id IN ($placeholders)";
        $params = array_merge($params, $categoria);
    }
    
    // Filtro por precio mínimo
    if (!empty($filtros['precio_min']) && $filtros['precio_min'] > 0) {
        $sql .= " AND p.precio >= ?";
        $params[] = $filtros['precio_min'];
    }
    
    // Filtro por precio máximo
    if (!empty($filtros['precio_max']) && $filtros['precio_max'] > 0) {
        $sql .= " AND p.precio <= ?";
        $params[] = $filtros['precio_max'];
    }
    
    // Filtro por estado del producto
    if (!empty($filtros['estado'])) {
        $estados = is_array($filtros['estado']) ? $filtros['estado'] : [$filtros['estado']];
        $placeholders = implode(',', array_fill(0, count($estados), '?'));
        $sql .= " AND p.estado_producto IN ($placeholders)";
        $params = array_merge($params, $estados);
    }
    
    // Filtro por ubicación (estado)
    if (!empty($filtros['ubicacion_estado'])) {
        // Escapar caracteres especiales para LIKE
        $ubicacion_escaped = addcslashes($filtros['ubicacion_estado'], '%_\\');
        $ubicacion_like = '%' . $ubicacion_escaped . '%';
        $sql .= " AND (p.ubicacion_estado LIKE ? OR p.ubicacion_ciudad LIKE ?)";
        $params[] = $ubicacion_like;
        $params[] = $ubicacion_like;
    }
    
    // Filtro por ubicación (ciudad)
    if (!empty($filtros['ubicacion_ciudad'])) {
        // Escapar caracteres especiales para LIKE
        $ubicacion_escaped = addcslashes($filtros['ubicacion_ciudad'], '%_\\');
        $sql .= " AND p.ubicacion_ciudad LIKE ?";
        $params[] = '%' . $ubicacion_escaped . '%';
    }
    
    // Filtro por destacados
    if (!empty($filtros['destacados']) && $filtros['destacados'] === true) {
        $sql .= " AND (p.destacado = 1 OR p.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 30 DAY))";
    }
    
    // Filtro por recientes (últimos 30 días)
    if (!empty($filtros['recientes']) && $filtros['recientes'] === true) {
        $sql .= " AND p.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    
    // Ordenamiento
    $orden = $filtros['orden'] ?? 'fecha';
    // Si es destacados, priorizar productos destacados
    if (!empty($filtros['destacados']) && $filtros['destacados'] === true) {
        switch ($orden) {
            case 'precio_asc':
                $sql .= " ORDER BY p.destacado DESC, p.precio ASC";
                break;
            case 'precio_desc':
                $sql .= " ORDER BY p.destacado DESC, p.precio DESC";
                break;
            case 'popularidad':
                $sql .= " ORDER BY p.destacado DESC, p.vistas DESC, p.favoritos_count DESC";
                break;
            case 'fecha':
            default:
                $sql .= " ORDER BY p.destacado DESC, p.fecha_publicacion DESC";
                break;
        }
    } else {
        switch ($orden) {
            case 'precio_asc':
                $sql .= " ORDER BY p.precio ASC";
                break;
            case 'precio_desc':
                $sql .= " ORDER BY p.precio DESC";
                break;
            case 'popularidad':
                $sql .= " ORDER BY p.vistas DESC, p.favoritos_count DESC";
                break;
            case 'fecha':
            default:
                $sql .= " ORDER BY p.fecha_publicacion DESC";
                break;
        }
    }
    
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limite;
    $params[] = $offset;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error en getProductosFiltrados: " . $e->getMessage());
        return [];
    }
}

// Función para contar productos con filtros
function contarProductosFiltrados($filtros = []) {
    global $pdo;
    
    $sql = "SELECT COUNT(*) as total FROM productos p WHERE p.activo = 1 AND p.vendido = 0";
    $params = [];
    
    // Aplicar los mismos filtros que en getProductosFiltrados
    if (!empty($filtros['categoria'])) {
        $categoria = is_array($filtros['categoria']) ? $filtros['categoria'] : [$filtros['categoria']];
        $placeholders = implode(',', array_fill(0, count($categoria), '?'));
        $sql .= " AND p.categoria_id IN ($placeholders)";
        $params = array_merge($params, $categoria);
    }
    
    if (!empty($filtros['precio_min']) && $filtros['precio_min'] > 0) {
        $sql .= " AND p.precio >= ?";
        $params[] = $filtros['precio_min'];
    }
    
    if (!empty($filtros['precio_max']) && $filtros['precio_max'] > 0) {
        $sql .= " AND p.precio <= ?";
        $params[] = $filtros['precio_max'];
    }
    
    if (!empty($filtros['estado'])) {
        $estados = is_array($filtros['estado']) ? $filtros['estado'] : [$filtros['estado']];
        $placeholders = implode(',', array_fill(0, count($estados), '?'));
        $sql .= " AND p.estado_producto IN ($placeholders)";
        $params = array_merge($params, $estados);
    }
    
    if (!empty($filtros['ubicacion_estado'])) {
        // Escapar caracteres especiales para LIKE
        $ubicacion_escaped = addcslashes($filtros['ubicacion_estado'], '%_\\');
        $ubicacion_like = '%' . $ubicacion_escaped . '%';
        $sql .= " AND (p.ubicacion_estado LIKE ? OR p.ubicacion_ciudad LIKE ?)";
        $params[] = $ubicacion_like;
        $params[] = $ubicacion_like;
    }
    
    if (!empty($filtros['ubicacion_ciudad'])) {
        // Escapar caracteres especiales para LIKE
        $ubicacion_escaped = addcslashes($filtros['ubicacion_ciudad'], '%_\\');
        $sql .= " AND p.ubicacion_ciudad LIKE ?";
        $params[] = '%' . $ubicacion_escaped . '%';
    }
    
    // Filtro por destacados
    if (!empty($filtros['destacados']) && $filtros['destacados'] === true) {
        $sql .= " AND (p.destacado = 1 OR p.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 30 DAY))";
    }
    
    // Filtro por recientes (últimos 30 días)
    if (!empty($filtros['recientes']) && $filtros['recientes'] === true) {
        $sql .= " AND p.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int)$result['total'];
    } catch (PDOException $e) {
        error_log("Error contando productos filtrados: " . $e->getMessage());
        return 0;
    }
}

// Función para obtener un producto por ID
function getProductoById($id) {
    global $pdo;
    
    $sql = "SELECT p.*, 
            u.nombre as vendedor_nombre,
            0 as vendedor_calificacion,
            u.ubicacion_estado as vendedor_estado,
            u.ubicacion_ciudad as vendedor_ciudad
            FROM productos p
            LEFT JOIN usuarios u ON p.vendedor_id = u.id
            WHERE p.id = ? AND p.activo = 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Función para buscar productos
function buscarProductos($query, $categoria = null, $precio_min = null, $precio_max = null, $estado = null, $ubicacion = null, $orden = 'relevancia', $limite = 24, $offset = 0) {
    global $pdo;
    
    if (!isset($pdo)) {
        error_log("buscarProductos: PDO no está disponible");
        return [];
    }
    
    // Sanitizar query
    $query = trim($query);
    if (empty($query)) {
        return [];
    }
    
    // Construir SQL base
    $sql = "SELECT p.*, 
            u.nombre as vendedor_nombre,
            0 as vendedor_calificacion,
            (SELECT url_imagen FROM imagenes_productos 
             WHERE producto_id = p.id AND es_principal = 1 
             LIMIT 1) as imagen_principal";
    
    $sql .= " FROM productos p
            LEFT JOIN usuarios u ON p.vendedor_id = u.id
            WHERE p.activo = 1 AND p.vendido = 0";
    
    $params = [];
    
    // Búsqueda usando LIKE (más compatible)
    if (!empty($query)) {
        // Escapar caracteres especiales para LIKE
        $query_escaped = addcslashes($query, '%_\\');
        $search_like = '%' . $query_escaped . '%';
        $search_like_start = $query_escaped . '%';
        
        // Buscar en título y descripción
        $sql .= " AND (p.titulo LIKE ? OR p.descripcion LIKE ? OR p.titulo LIKE ?)";
        $params[] = $search_like_start; // Prioridad a títulos que empiezan con el término
        $params[] = $search_like; // También buscar en descripción
        $params[] = $search_like; // Y títulos que contengan el término
    }
    
    // Filtros adicionales
    if ($categoria && $categoria !== 'todos') {
        $sql .= " AND p.categoria_id = ?";
        $params[] = $categoria;
    }
    
    if ($precio_min !== null && $precio_min > 0) {
        $sql .= " AND p.precio >= ?";
        $params[] = $precio_min;
    }
    
    if ($precio_max !== null && $precio_max > 0) {
        $sql .= " AND p.precio <= ?";
        $params[] = $precio_max;
    }
    
    if ($estado && $estado !== 'todos') {
        $sql .= " AND p.estado_producto = ?";
        $params[] = $estado;
    }
    
    if ($ubicacion) {
        // Escapar caracteres especiales para LIKE
        $ubicacion_escaped = addcslashes($ubicacion, '%_\\');
        $ubicacion_like = '%' . $ubicacion_escaped . '%';
        $sql .= " AND (p.ubicacion_ciudad LIKE ? OR p.ubicacion_estado LIKE ?)";
        $params[] = $ubicacion_like;
        $params[] = $ubicacion_like;
    }
    
    // Ordenamiento
    switch ($orden) {
        case 'precio_asc':
            $sql .= " ORDER BY p.precio ASC";
            break;
        case 'precio_desc':
            $sql .= " ORDER BY p.precio DESC";
            break;
        case 'fecha':
            $sql .= " ORDER BY p.fecha_publicacion DESC";
            break;
        case 'popularidad':
            $sql .= " ORDER BY COALESCE(p.vistas, 0) DESC, COALESCE(p.favoritos_count, 0) DESC";
            break;
        case 'relevancia':
        default:
            if (!empty($query)) {
                // Ordenar por relevancia: títulos que empiezan con el término primero, luego los que contienen
                $query_escaped = addcslashes($query, '%_\\');
                $sql .= " ORDER BY 
                    CASE 
                        WHEN p.titulo LIKE ? THEN 1
                        WHEN p.titulo LIKE ? THEN 2
                        WHEN p.descripcion LIKE ? THEN 3
                        ELSE 4
                    END ASC,
                    p.fecha_publicacion DESC";
                $params[] = $query_escaped . '%'; // Títulos que empiezan
                $params[] = '%' . $query_escaped . '%'; // Títulos que contienen
                $params[] = '%' . $query_escaped . '%'; // Descripciones que contienen
            } else {
                $sql .= " ORDER BY p.fecha_publicacion DESC";
            }
            break;
    }
    
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = (int)$limite;
    $params[] = (int)$offset;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("buscarProductos: Se encontraron " . count($resultados) . " productos para query: '$query'");
        return $resultados;
    } catch (PDOException $e) {
        error_log("Error en búsqueda: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . print_r($params, true));
        return [];
    }
}

// Función para obtener sugerencias de búsqueda
function getSugerenciasBusqueda($query, $limite = 5) {
    global $pdo;
    
    $query = trim($query);
    if (empty($query) || strlen($query) < 2) {
        return [];
    }
    
    // Escapar caracteres especiales para LIKE
    $query_escaped = addcslashes($query, '%_\\');
    $search_like = $query_escaped . '%';
    
    try {
        // Buscar en títulos de productos
        $sql = "SELECT DISTINCT p.titulo as sugerencia, 'producto' as tipo
                FROM productos p
                WHERE p.activo = 1 AND p.vendido = 0 
                AND p.titulo LIKE ?
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$search_like, $limite]);
        $sugerencias = $stmt->fetchAll();
        
        // Si no hay suficientes, buscar en categorías
        if (count($sugerencias) < $limite) {
            $sql_cat = "SELECT nombre as sugerencia, 'categoria' as tipo
                       FROM categorias
                       WHERE activa = 1 AND nombre LIKE ?
                       LIMIT ?";
            $stmt_cat = $pdo->prepare($sql_cat);
            $stmt_cat->execute([$search_like, $limite - count($sugerencias)]);
            $categorias = $stmt_cat->fetchAll();
            $sugerencias = array_merge($sugerencias, $categorias);
        }
        
        return array_slice($sugerencias, 0, $limite);
    } catch (PDOException $e) {
        error_log("Error en sugerencias: " . $e->getMessage());
        return [];
    }
}

// Función para contar resultados de búsqueda
function contarResultadosBusqueda($query, $categoria = null, $precio_min = null, $precio_max = null, $estado = null, $ubicacion = null) {
    global $pdo;
    
    if (!isset($pdo)) {
        return 0;
    }
    
    $query = trim($query);
    
    $sql = "SELECT COUNT(*) as total FROM productos p WHERE p.activo = 1 AND p.vendido = 0";
    $params = [];
    
    if (!empty($query)) {
        // Escapar caracteres especiales para LIKE
        $query_escaped = addcslashes($query, '%_\\');
        $search_like = '%' . $query_escaped . '%';
        
        $sql .= " AND (p.titulo LIKE ? OR p.descripcion LIKE ?)";
        $params[] = $search_like;
        $params[] = $search_like;
    }
    
    if ($categoria && $categoria !== 'todos') {
        $sql .= " AND p.categoria_id = ?";
        $params[] = $categoria;
    }
    
    if ($precio_min !== null && $precio_min > 0) {
        $sql .= " AND p.precio >= ?";
        $params[] = $precio_min;
    }
    
    if ($precio_max !== null && $precio_max > 0) {
        $sql .= " AND p.precio <= ?";
        $params[] = $precio_max;
    }
    
    if ($estado && $estado !== 'todos') {
        $sql .= " AND p.estado_producto = ?";
        $params[] = $estado;
    }
    
    if ($ubicacion) {
        // Escapar caracteres especiales para LIKE
        $ubicacion_escaped = addcslashes($ubicacion, '%_\\');
        $ubicacion_like = '%' . $ubicacion_escaped . '%';
        $sql .= " AND (p.ubicacion_ciudad LIKE ? OR p.ubicacion_estado LIKE ?)";
        $params[] = $ubicacion_like;
        $params[] = $ubicacion_like;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = (int)($result['total'] ?? 0);
        error_log("contarResultadosBusqueda: Total encontrado: $total para query: '$query'");
        return $total;
    } catch (PDOException $e) {
        error_log("Error contando resultados: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . print_r($params, true));
        return 0;
    }
}

// Función para obtener imágenes de un producto
function getImagenesProducto($producto_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM imagenes_productos WHERE producto_id = ? ORDER BY es_principal DESC, orden ASC");
    $stmt->execute([$producto_id]);
    return $stmt->fetchAll();
}

// Función para formatear precio
function formatearPrecio($precio, $moneda = 'MXN') {
    $simbolo = $moneda === 'MXN' ? '$' : $moneda;
    return $simbolo . number_format($precio, 0, '.', ',');
}

// Función para obtener texto del estado del producto
function getEstadoTexto($estado) {
    $estados = [
        'nuevo' => 'Como nuevo',
        'excelente' => 'Excelente',
        'bueno' => 'Muy buen estado',
        'regular' => 'Buen estado',
        'para_repuesto' => 'Para repuesto'
    ];
    return $estados[$estado] ?? 'Buen estado';
}

// Función para obtener URL de imagen placeholder (SVG inline, no requiere conexión externa)
function getPlaceholderImage($ancho = 400, $alto = 400, $texto = 'Sin imagen') {
    $texto_encoded = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    $svg = '<svg width="' . $ancho . '" height="' . $alto . '" xmlns="http://www.w3.org/2000/svg">
        <rect width="100%" height="100%" fill="#e0e0e0"/>
        <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="16" fill="#999" text-anchor="middle" dominant-baseline="middle">' . $texto_encoded . '</text>
    </svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

// Función para obtener testimonios activos
function getTestimonios($limite = 6) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM testimonios 
        WHERE activo = 1 
        ORDER BY fecha_creacion DESC 
        LIMIT ?
    ");
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

// Función para obtener guías activas
function getGuias($categoria = null, $limite = 6, $destacadas = false) {
    global $pdo;
    
    if (!isset($pdo)) {
        error_log("getGuias: PDO no está inicializado");
        return [];
    }
    
    try {
        $sql = "SELECT * FROM guias WHERE activo = 1";
        $params = [];
        
        if ($categoria && $categoria !== 'todos') {
            $sql .= " AND categoria = ?";
            $params[] = $categoria;
        }
        
        if ($destacadas) {
            $sql .= " AND destacado = 1";
        }
        
        $sql .= " ORDER BY destacado DESC, fecha_publicacion DESC LIMIT " . (int)$limite;
        
        $stmt = $pdo->prepare($sql);
        if (!$stmt) {
            error_log("getGuias: Error preparando consulta: " . implode(", ", $pdo->errorInfo()));
            return [];
        }
        
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!is_array($result)) {
            error_log("getGuias: Resultado no es array");
            return [];
        }
        
        // Log para debugging (solo en desarrollo)
        if (count($result) === 0) {
            error_log("getGuias: No se encontraron guías. SQL: " . $sql . " | Params: " . json_encode($params));
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error en getGuias: " . $e->getMessage());
        return [];
    } catch (Exception $e) {
        error_log("Error general en getGuias: " . $e->getMessage());
        return [];
    }
}

// Función para obtener estadísticas del sitio
function getEstadisticas() {
    global $pdo;
    
    $stats = [];
    
    try {
        // Total de productos activos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1 AND vendido = 0");
        $stats['productos_activos'] = $stmt->fetch()['total'];
        
        // Total de usuarios activos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE estado = 'activo'");
        $stats['usuarios_activos'] = $stmt->fetch()['total'];
        
        // Total de estados únicos con productos
        $stmt = $pdo->query("SELECT COUNT(DISTINCT ubicacion_estado) as total FROM productos WHERE activo = 1 AND ubicacion_estado IS NOT NULL AND ubicacion_estado != ''");
        $stats['estados_cobertura'] = $stmt->fetch()['total'];
        
    } catch (PDOException $e) {
        error_log("Error al obtener estadísticas: " . $e->getMessage());
        // Valores por defecto si hay error
        $stats = [
            'productos_activos' => 0,
            'usuarios_activos' => 0,
            'estados_cobertura' => 0
        ];
    }
    
    return $stats;
}

// Función para obtener todas las categorías activas
function getCategorias($parent_id = null) {
    global $pdo;
    
    $sql = "SELECT 
                c.*,
                COUNT(p.id) as total_productos
            FROM categorias c
            LEFT JOIN productos p ON c.id = p.categoria_id 
                AND p.activo = 1 
                AND p.vendido = 0
            WHERE c.activa = 1";
    $params = [];
    
    if ($parent_id === null) {
        $sql .= " AND c.parent_id IS NULL";
    } elseif ($parent_id !== false) {
        $sql .= " AND c.parent_id = ?";
        $params[] = $parent_id;
    }
    
    $sql .= " GROUP BY c.id
              ORDER BY c.orden ASC, c.nombre ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultado = $stmt->fetchAll();
    
    // Asegurar que total_productos sea un entero
    foreach ($resultado as &$cat) {
        $cat['total_productos'] = (int)$cat['total_productos'];
    }
    
    return $resultado;
}

// Función para obtener una categoría por slug
function getCategoriaBySlug($slug) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE slug = ? AND activa = 1");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

// Función para obtener una categoría por ID
function getCategoriaById($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ? AND activa = 1");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Función para obtener favoritos del usuario
function getFavoritosUsuario($usuario_id) {
    global $pdo;
    
    if (!$pdo || !$usuario_id) {
        error_log("getFavoritosUsuario: PDO o usuario_id no válido");
        return [];
    }
    
    try {
        // Mostrar todos los favoritos, incluso si el producto está vendido o inactivo
        // pero marcar visualmente los que no están disponibles
        $sql = "SELECT p.*, 
                u.nombre as vendedor_nombre,
                (SELECT url_imagen FROM imagenes_productos 
                 WHERE producto_id = p.id AND es_principal = 1 
                 LIMIT 1) as imagen_principal,
                f.fecha_agregado,
                CASE 
                    WHEN p.vendido = 1 THEN 'vendido'
                    WHEN p.activo = 0 THEN 'inactivo'
                    ELSE 'disponible'
                END as estado_favorito
                FROM favoritos f
                INNER JOIN productos p ON f.producto_id = p.id
                LEFT JOIN usuarios u ON p.vendedor_id = u.id
                WHERE f.usuario_id = ?
                ORDER BY f.fecha_agregado DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("getFavoritosUsuario: Encontrados " . count($result) . " favoritos para usuario $usuario_id");
        
        return $result;
    } catch (Exception $e) {
        error_log("Error en getFavoritosUsuario: " . $e->getMessage());
        return [];
    }
}

// Función para verificar si un producto está en favoritos del usuario
function esFavorito($usuario_id, $producto_id) {
    global $pdo;
    
    if (!$pdo || !$usuario_id || !$producto_id) {
        return false;
    }
    
    try {
        $sql = "SELECT COUNT(*) as count FROM favoritos 
                WHERE usuario_id = ? AND producto_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $producto_id]);
        $result = $stmt->fetch();
        return ($result && $result['count'] > 0);
    } catch (Exception $e) {
        error_log("Error en esFavorito: " . $e->getMessage());
        return false;
    }
}

// Función para agregar producto a favoritos
function agregarFavorito($usuario_id, $producto_id) {
    global $pdo;
    
    if (!$pdo || !$usuario_id || !$producto_id) {
        return ['success' => false, 'error' => 'Datos inválidos'];
    }
    
    try {
        // Verificar si ya existe
        if (esFavorito($usuario_id, $producto_id)) {
            return ['success' => false, 'error' => 'El producto ya está en favoritos'];
        }
        
        // Agregar a favoritos
        $sql = "INSERT INTO favoritos (usuario_id, producto_id, fecha_agregado) 
                VALUES (?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $producto_id]);
        
        // Actualizar contador en productos
        $sql_update = "UPDATE productos 
                       SET favoritos_count = COALESCE(favoritos_count, 0) + 1 
                       WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$producto_id]);
        
        return ['success' => true, 'message' => 'Producto agregado a favoritos'];
    } catch (Exception $e) {
        error_log("Error en agregarFavorito: " . $e->getMessage());
        return ['success' => false, 'error' => 'Error al agregar a favoritos: ' . $e->getMessage()];
    }
}

// Función para eliminar producto de favoritos
function eliminarFavorito($usuario_id, $producto_id) {
    global $pdo;
    
    if (!$pdo || !$usuario_id || !$producto_id) {
        return ['success' => false, 'error' => 'Datos inválidos'];
    }
    
    try {
        // Eliminar de favoritos
        $sql = "DELETE FROM favoritos 
                WHERE usuario_id = ? AND producto_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $producto_id]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'El producto no está en favoritos'];
        }
        
        // Actualizar contador en productos
        $sql_update = "UPDATE productos 
                       SET favoritos_count = GREATEST(COALESCE(favoritos_count, 0) - 1, 0) 
                       WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$producto_id]);
        
        return ['success' => true, 'message' => 'Producto eliminado de favoritos'];
    } catch (Exception $e) {
        error_log("Error en eliminarFavorito: " . $e->getMessage());
        return ['success' => false, 'error' => 'Error al eliminar de favoritos: ' . $e->getMessage()];
    }
}

// Función para obtener productos del vendedor
function getProductosVendedor($vendedor_id, $estado = null, $limite = null, $offset = 0) {
    global $pdo;
    
    $sql = "SELECT p.*, 
            (SELECT url_imagen FROM imagenes_productos 
             WHERE producto_id = p.id AND es_principal = 1 
             LIMIT 1) as imagen_principal,
            c.nombre as categoria_nombre
            FROM productos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE p.vendedor_id = ?";
    
    $params = [$vendedor_id];
    
    if ($estado === 'activo') {
        $sql .= " AND p.activo = 1 AND p.vendido = 0";
    } elseif ($estado === 'pausado') {
        $sql .= " AND p.activo = 0";
    } elseif ($estado === 'vendido') {
        $sql .= " AND p.vendido = 1";
    }
    
    $sql .= " ORDER BY p.fecha_publicacion DESC";
    
    if ($limite) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Función para contar productos del vendedor
function contarProductosVendedor($vendedor_id, $estado = null) {
    global $pdo;
    
    $sql = "SELECT COUNT(*) as total FROM productos WHERE vendedor_id = ?";
    $params = [$vendedor_id];
    
    if ($estado === 'activo') {
        $sql .= " AND activo = 1 AND vendido = 0";
    } elseif ($estado === 'pausado') {
        $sql .= " AND activo = 0";
    } elseif ($estado === 'vendido') {
        $sql .= " AND vendido = 1";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return (int)$result['total'];
}

// Función para obtener productos pendientes de moderación
function getProductosPendientesModeracion($limite = 50, $offset = 0) {
    global $pdo;
    
    $sql = "SELECT p.*, 
            u.nombre as vendedor_nombre,
            u.email as vendedor_email,
            (SELECT url_imagen FROM imagenes_productos 
             WHERE producto_id = p.id AND es_principal = 1 
             LIMIT 1) as imagen_principal,
            c.nombre as categoria_nombre
            FROM productos p
            LEFT JOIN usuarios u ON p.vendedor_id = u.id
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE p.activo = 0 
            AND p.vendido = 0
            ORDER BY p.fecha_publicacion ASC
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limite, $offset]);
    return $stmt->fetchAll();
}

// Función para contar productos pendientes
function contarProductosPendientes() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 0 AND vendido = 0");
    $result = $stmt->fetch();
    return (int)$result['total'];
}

// Función para obtener productos reportados
function getProductosReportados($limite = 50, $offset = 0) {
    global $pdo;
    
    $sql = "SELECT p.*, 
            u.nombre as vendedor_nombre,
            (SELECT url_imagen FROM imagenes_productos 
             WHERE producto_id = p.id AND es_principal = 1 
             LIMIT 1) as imagen_principal,
            COUNT(r.id) as total_reportes
            FROM productos p
            LEFT JOIN usuarios u ON p.vendedor_id = u.id
            LEFT JOIN reportes r ON r.producto_id = p.id
            WHERE p.activo = 1
            GROUP BY p.id
            HAVING total_reportes > 0
            ORDER BY total_reportes DESC, p.fecha_publicacion DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limite, $offset]);
    return $stmt->fetchAll();
}

// Función para contar productos reportados
function contarProductosReportados() {
    global $pdo;
    
    $sql = "SELECT COUNT(DISTINCT producto_id) as total 
            FROM reportes 
            WHERE producto_id IN (SELECT id FROM productos WHERE activo = 1)";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch();
    return (int)$result['total'];
}

// Función para obtener estadísticas de reportes
function getEstadisticasReportes() {
    global $pdo;
    
    $stats = [];
    
    try {
        // Ingresos totales (suma de órdenes completadas)
        $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM ordenes WHERE estado = 'completada'");
        $stats['ingresos_totales'] = $stmt->fetch()['total'];
        
        // Total de transacciones
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM ordenes WHERE estado = 'completada'");
        $stats['transacciones'] = $stmt->fetch()['total'];
        
        // Usuarios activos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE estado = 'activo'");
        $stats['usuarios_activos'] = $stmt->fetch()['total'];
        
        // Productos activos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1 AND vendido = 0");
        $stats['productos_activos'] = $stmt->fetch()['total'];
        
    } catch (PDOException $e) {
        error_log("Error al obtener estadísticas de reportes: " . $e->getMessage());
        $stats = [
            'ingresos_totales' => 0,
            'transacciones' => 0,
            'usuarios_activos' => 0,
            'productos_activos' => 0
        ];
    }
    
    return $stats;
}

// Función para obtener top categorías por ventas
function getTopCategoriasVentas($limite = 5) {
    global $pdo;
    
    if (!isset($pdo)) {
        return [];
    }
    
    try {
        $sql = "SELECT c.id, c.nombre, COUNT(DISTINCT o.id) as total_ventas
                FROM categorias c
                INNER JOIN productos p ON p.categoria_id = c.id
                INNER JOIN orden_items oi ON oi.producto_id = p.id
                INNER JOIN ordenes o ON o.id = oi.orden_id
                WHERE o.estado = 'completada'
                AND c.activa = 1
                GROUP BY c.id, c.nombre
                ORDER BY total_ventas DESC
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limite]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener top categorías: " . $e->getMessage());
        return [];
    }
}

// Función para obtener top vendedores
function getTopVendedores($limite = 5) {
    global $pdo;
    
    if (!isset($pdo)) {
        return [];
    }
    
    try {
        $sql = "SELECT u.id, u.nombre, 
                COUNT(DISTINCT o.id) as total_ventas,
                COALESCE(SUM(oi.subtotal), 0) as ingresos_totales
                FROM usuarios u
                INNER JOIN productos p ON p.vendedor_id = u.id
                INNER JOIN orden_items oi ON oi.producto_id = p.id
                INNER JOIN ordenes o ON o.id = oi.orden_id
                WHERE o.estado = 'completada'
                AND u.estado = 'activo'
                GROUP BY u.id, u.nombre
                ORDER BY ingresos_totales DESC
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limite]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener top vendedores: " . $e->getMessage());
        return [];
    }
}

// Función para obtener reportes de usuarios con filtros
function getReportesAdmin($filtros = []) {
    global $pdo;
    if (!isset($pdo)) {
        error_log("getReportesAdmin: PDO no está inicializado.");
        return [];
    }
    
    $sql = "SELECT r.*,
            u1.nombre as reportante_nombre, u1.email as reportante_email,
            u2.nombre as reportado_nombre, u2.email as reportado_email,
            p.titulo as producto_titulo,
            u3.nombre as admin_revisor_nombre
            FROM reportes r
            LEFT JOIN usuarios u1 ON r.reportante_id = u1.id
            LEFT JOIN usuarios u2 ON r.reportado_id = u2.id
            LEFT JOIN productos p ON r.producto_id = p.id
            LEFT JOIN usuarios u3 ON r.admin_revisor_id = u3.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filtros['estado']) && $filtros['estado'] !== 'todos') {
        $sql .= " AND r.estado = ?";
        $params[] = $filtros['estado'];
    }
    
    if (!empty($filtros['tipo_reporte']) && $filtros['tipo_reporte'] !== 'todos') {
        $sql .= " AND r.tipo_reporte = ?";
        $params[] = $filtros['tipo_reporte'];
    }
    
    if (!empty($filtros['busqueda'])) {
        $sql .= " AND (r.descripcion LIKE ? OR u1.nombre LIKE ? OR u2.nombre LIKE ? OR p.titulo LIKE ?)";
        $busqueda = '%' . $filtros['busqueda'] . '%';
        $params[] = $busqueda;
        $params[] = $busqueda;
        $params[] = $busqueda;
        $params[] = $busqueda;
    }
    
    $sql .= " ORDER BY r.fecha_reporte DESC";
    
    if (isset($filtros['limite']) && $filtros['limite'] > 0) {
        $sql .= " LIMIT ?";
        $params[] = (int)$filtros['limite'];
        
        if (isset($filtros['offset']) && $filtros['offset'] > 0) {
            $sql .= " OFFSET ?";
            $params[] = (int)$filtros['offset'];
        }
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener reportes admin: " . $e->getMessage());
        return [];
    }
}

// Función para contar reportes con filtros
function contarReportesAdmin($filtros = []) {
    global $pdo;
    if (!isset($pdo)) {
        return 0;
    }
    
    $sql = "SELECT COUNT(*) as total
            FROM reportes r
            LEFT JOIN usuarios u1 ON r.reportante_id = u1.id
            LEFT JOIN usuarios u2 ON r.reportado_id = u2.id
            LEFT JOIN productos p ON r.producto_id = p.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filtros['estado']) && $filtros['estado'] !== 'todos') {
        $sql .= " AND r.estado = ?";
        $params[] = $filtros['estado'];
    }
    
    if (!empty($filtros['tipo_reporte']) && $filtros['tipo_reporte'] !== 'todos') {
        $sql .= " AND r.tipo_reporte = ?";
        $params[] = $filtros['tipo_reporte'];
    }
    
    if (!empty($filtros['busqueda'])) {
        $sql .= " AND (r.descripcion LIKE ? OR u1.nombre LIKE ? OR u2.nombre LIKE ? OR p.titulo LIKE ?)";
        $busqueda = '%' . $filtros['busqueda'] . '%';
        $params[] = $busqueda;
        $params[] = $busqueda;
        $params[] = $busqueda;
        $params[] = $busqueda;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    } catch (PDOException $e) {
        error_log("Error al contar reportes admin: " . $e->getMessage());
        return 0;
    }
}

// Función para obtener estadísticas de reportes
function getEstadisticasReportesAdmin() {
    global $pdo;
    if (!isset($pdo)) {
        return [
            'pendientes' => 0,
            'en_revision' => 0,
            'resueltos' => 0,
            'rechazados' => 0,
            'total' => 0
        ];
    }
    
    try {
        $stmt = $pdo->query("
            SELECT estado, COUNT(*) as total 
            FROM reportes 
            GROUP BY estado
        ");
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $resultado = [
            'pendientes' => 0,
            'en_revision' => 0,
            'resueltos' => 0,
            'rechazados' => 0,
            'total' => 0
        ];
        
        foreach ($stats as $stat) {
            $estado = $stat['estado'];
            $total = (int)$stat['total'];
            $resultado['total'] += $total;
            
            if (isset($resultado[$estado])) {
                $resultado[$estado] = $total;
            }
        }
        
        return $resultado;
    } catch (PDOException $e) {
        error_log("Error al obtener estadísticas de reportes: " . $e->getMessage());
        return [
            'pendientes' => 0,
            'en_revision' => 0,
            'resueltos' => 0,
            'rechazados' => 0,
            'total' => 0
        ];
    }
}

// Función para resolver un reporte
function resolverReporte($reporte_id, $admin_id, $estado, $resolucion = '') {
    global $pdo;
    if (!isset($pdo)) {
        return false;
    }
    
    try {
        $sql = "UPDATE reportes 
                SET estado = ?, 
                    admin_revisor_id = ?, 
                    resolucion = ?,
                    fecha_revision = NOW(),
                    fecha_resolucion = CASE WHEN ? IN ('resuelto', 'rechazado') THEN NOW() ELSE fecha_resolucion END
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$estado, $admin_id, $resolucion, $estado, $reporte_id]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error al resolver reporte: " . $e->getMessage());
        return false;
    }
}

// Función para obtener ventas mensuales (últimos 12 meses)
function getVentasMensuales() {
    global $pdo;
    if (!isset($pdo)) {
        return [];
    }
    
    try {
        $stmt = $pdo->query("
            SELECT 
                DATE_FORMAT(fecha_creacion, '%Y-%m') as mes,
                DATE_FORMAT(fecha_creacion, '%b %Y') as mes_nombre,
                COUNT(*) as total_ventas,
                COALESCE(SUM(total), 0) as ingresos
            FROM ordenes
            WHERE estado = 'completada'
            AND fecha_creacion >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(fecha_creacion, '%Y-%m'), DATE_FORMAT(fecha_creacion, '%b %Y')
            ORDER BY mes ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener ventas mensuales: " . $e->getMessage());
        return [];
    }
}

// Función para obtener estadísticas del dashboard admin
function getEstadisticasDashboard() {
    global $pdo;
    
    $stats = [];
    
    try {
        // Total de usuarios
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE estado = 'activo'");
        $stats['usuarios_totales'] = (int)$stmt->fetch()['total'];
        
        // Productos activos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1 AND vendido = 0");
        $stats['productos_activos'] = (int)$stmt->fetch()['total'];
        
        // Ventas del mes (ingresos totales del mes actual)
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(total), 0) as total 
            FROM ordenes 
            WHERE estado = 'completada' 
            AND MONTH(fecha_creacion) = MONTH(CURRENT_DATE()) 
            AND YEAR(fecha_creacion) = YEAR(CURRENT_DATE())
        ");
        $result = $stmt->fetch();
        $stats['ventas_mes'] = (float)$result['total'];
        
        // Reportes pendientes (si la tabla reportes tiene campo estado, sino contar todos)
        try {
            $stmt = $pdo->query("
                SELECT COUNT(DISTINCT producto_id) as total 
                FROM reportes 
                WHERE producto_id IN (SELECT id FROM productos WHERE activo = 1)
            ");
            $result = $stmt->fetch();
            $stats['reportes_pendientes'] = (int)($result['total'] ?? 0);
        } catch (PDOException $e) {
            // Si hay error (campo estado no existe), contar todos los reportes
            try {
                $stmt = $pdo->query("
                    SELECT COUNT(DISTINCT producto_id) as total 
                    FROM reportes 
                    WHERE producto_id IN (SELECT id FROM productos WHERE activo = 1)
                ");
                $result = $stmt->fetch();
                $stats['reportes_pendientes'] = (int)($result['total'] ?? 0);
            } catch (PDOException $e2) {
                $stats['reportes_pendientes'] = 0;
            }
        }
        
    } catch (PDOException $e) {
        error_log("Error al obtener estadísticas del dashboard: " . $e->getMessage());
        $stats = [
            'usuarios_totales' => 0,
            'productos_activos' => 0,
            'ventas_mes' => 0,
            'reportes_pendientes' => 0
        ];
    }
    
    return $stats;
}

// Función para obtener actividad reciente
function getActividadReciente($limite = 5) {
    global $pdo;
    
    try {
        // Combinar diferentes tipos de actividad
        $actividades = [];
        
        // Usuarios recientes (usar COALESCE para manejar diferentes nombres de campo)
        $stmt = $pdo->prepare("
            SELECT 'usuario' as tipo, id, nombre as titulo, 
                   COALESCE(fecha_registro, fecha_creacion, NOW()) as fecha, 
                   'Nuevo usuario registrado' as descripcion
            FROM usuarios
            WHERE estado = 'activo'
            ORDER BY COALESCE(fecha_registro, fecha_creacion, id) DESC
            LIMIT ?
        ");
        $stmt->execute([$limite]);
        $usuarios = $stmt->fetchAll();
        
        foreach ($usuarios as $usuario) {
            $actividades[] = [
                'tipo' => 'usuario',
                'titulo' => $usuario['titulo'],
                'descripcion' => 'Nuevo usuario registrado',
                'fecha' => $usuario['fecha']
            ];
        }
        
        // Ordenar por fecha y limitar
        usort($actividades, function($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });
        
        return array_slice($actividades, 0, $limite);
        
    } catch (PDOException $e) {
        error_log("Error al obtener actividad reciente: " . $e->getMessage());
        return [];
    }
}

// Función para obtener todos los usuarios con filtros
function getUsuariosAdmin($filtros = []) {
    global $pdo;
    
    if (!isset($pdo)) {
        error_log("Error: PDO no está disponible en getUsuariosAdmin");
        return [];
    }
    
    // Usar subconsultas para evitar problemas con GROUP BY en modo estricto de MySQL
    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM productos p WHERE p.vendedor_id = u.id) as total_productos,
            (SELECT COUNT(*) FROM ordenes o WHERE o.comprador_id = u.id) as total_compras
            FROM usuarios u
            WHERE 1=1";
    
    $params = [];
    
    // Filtro por búsqueda
    if (!empty($filtros['busqueda'])) {
        $sql .= " AND (u.nombre LIKE ? OR u.email LIKE ? OR u.apellidos LIKE ?)";
        $busqueda = '%' . $filtros['busqueda'] . '%';
        $params[] = $busqueda;
        $params[] = $busqueda;
        $params[] = $busqueda;
    }
    
    // Filtro por rol
    if (!empty($filtros['rol']) && $filtros['rol'] !== 'todos') {
        $sql .= " AND u.rol = ?";
        $params[] = $filtros['rol'];
    }
    
    // Filtro por estado
    if (!empty($filtros['estado']) && $filtros['estado'] !== 'todos') {
        $sql .= " AND u.estado = ?";
        $params[] = $filtros['estado'];
    }

    // Filtro por puede_vender
    if (isset($filtros['puede_vender']) && $filtros['puede_vender'] !== 'todos' && $filtros['puede_vender'] !== '') {
        $sql .= " AND u.puede_vender = ?";
        $params[] = (int)$filtros['puede_vender'];
    }

    // Ordenar por fecha_registro (campo que existe en la tabla)
    $sql .= " ORDER BY u.fecha_registro DESC";
    
    // Límite y offset
    if (!empty($filtros['limite'])) {
        $sql .= " LIMIT ?";
        $params[] = (int)$filtros['limite'];
        
        if (!empty($filtros['offset'])) {
            $sql .= " OFFSET ?";
            $params[] = (int)$filtros['offset'];
        }
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        if (!$stmt) {
            error_log("Error preparando consulta en getUsuariosAdmin: " . implode(", ", $pdo->errorInfo()));
            return [];
        }
        
        $stmt->execute($params);
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!is_array($resultado)) {
            error_log("getUsuariosAdmin: resultado no es array");
            return [];
        }
        
        return $resultado;
    } catch (PDOException $e) {
        error_log("Error al obtener usuarios admin: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . json_encode($params));
        return [];
    }
}

// Función para contar usuarios con filtros
function contarUsuariosAdmin($filtros = []) {
    global $pdo;
    
    if (!isset($pdo)) {
        error_log("Error: PDO no está disponible en contarUsuariosAdmin");
        return 0;
    }
    
    $sql = "SELECT COUNT(*) as total FROM usuarios u WHERE 1=1";
    $params = [];
    
    if (!empty($filtros['busqueda'])) {
        $sql .= " AND (u.nombre LIKE ? OR u.email LIKE ? OR u.apellidos LIKE ?)";
        $busqueda = '%' . $filtros['busqueda'] . '%';
        $params[] = $busqueda;
        $params[] = $busqueda;
        $params[] = $busqueda;
    }
    
    if (!empty($filtros['rol']) && $filtros['rol'] !== 'todos') {
        $sql .= " AND u.rol = ?";
        $params[] = $filtros['rol'];
    }
    
    if (!empty($filtros['estado']) && $filtros['estado'] !== 'todos') {
        $sql .= " AND u.estado = ?";
        $params[] = $filtros['estado'];
    }

    if (isset($filtros['puede_vender']) && $filtros['puede_vender'] !== 'todos' && $filtros['puede_vender'] !== '') {
        $sql .= " AND u.puede_vender = ?";
        $params[] = (int)$filtros['puede_vender'];
    }

    try {
        $stmt = $pdo->prepare($sql);
        if (!$stmt) {
            error_log("Error preparando consulta en contarUsuariosAdmin: " . implode(", ", $pdo->errorInfo()));
            return 0;
        }
        
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    } catch (PDOException $e) {
        error_log("Error al contar usuarios admin: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . json_encode($params));
        return 0;
    }
}

// Función para actualizar estado de usuario
function actualizarEstadoUsuario($usuario_id, $nuevo_estado) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $usuario_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al actualizar estado de usuario: " . $e->getMessage());
        return false;
    }
}

// Función para aprobar producto
function aprobarProducto($producto_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE productos SET activo = 1, fecha_actualizacion = NOW() WHERE id = ?");
        $stmt->execute([$producto_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al aprobar producto: " . $e->getMessage());
        return false;
    }
}

// Función para rechazar producto
function rechazarProducto($producto_id, $razon = '') {
    global $pdo;
    
    try {
        // Marcar como inactivo y opcionalmente guardar razón
        $stmt = $pdo->prepare("UPDATE productos SET activo = 0, fecha_actualizacion = NOW() WHERE id = ?");
        $stmt->execute([$producto_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al rechazar producto: " . $e->getMessage());
        return false;
    }
}
