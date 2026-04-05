<?php
/**
 * Procesa la publicación de un producto
 */
session_start();
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';

// Verificar autenticación
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$user = get_session_user();
$vendedor_id = $user['id'] ?? null;

if (!$vendedor_id) {
    $_SESSION['error_message'] = 'No se pudo identificar tu usuario.';
    header('Location: publicar_producto.php');
    exit;
}

// Obtener y validar datos del formulario
$titulo = trim($_POST['titulo'] ?? '');
$categoria_id = (int)($_POST['categoria_id'] ?? 0);
$descripcion = trim($_POST['descripcion'] ?? '');
$precio = (float)($_POST['precio'] ?? 0);
$precio_original = !empty($_POST['precio_original']) ? (float)$_POST['precio_original'] : null;
$stock = (int)($_POST['stock'] ?? 1);
$estado_producto = trim($_POST['estado_producto'] ?? 'bueno');
$ubicacion_estado = trim($_POST['ubicacion_estado'] ?? '');
$ubicacion_ciudad = trim($_POST['ubicacion_ciudad'] ?? '');
// Nota: envio_disponible, envio_gratis y sku no existen en la tabla productos

// Validaciones básicas
$errores = [];

if (empty($titulo)) {
    $errores[] = 'El título es requerido';
}

if ($categoria_id <= 0) {
    $errores[] = 'Debes seleccionar una categoría';
}

if (empty($descripcion)) {
    $errores[] = 'La descripción es requerida';
}

if ($precio <= 0) {
    $errores[] = 'El precio debe ser mayor a 0';
}

if (empty($ubicacion_estado)) {
    $errores[] = 'Debes seleccionar un estado';
}

if (empty($ubicacion_ciudad)) {
    $errores[] = 'Debes ingresar una ciudad';
}

// Validar estado del producto
$estados_validos = ['nuevo', 'excelente', 'bueno', 'regular', 'para_repuesto'];
if (!in_array($estado_producto, $estados_validos)) {
    $estado_producto = 'bueno';
}

// Si hay errores, redirigir de vuelta
if (!empty($errores)) {
    $_SESSION['error_message'] = implode('<br>', $errores);
    $_SESSION['form_data'] = $_POST; // Guardar datos del formulario
    header('Location: publicar_producto.php');
    exit;
}

try {
    // Generar slug único del título
    $slug_base = strtolower(trim($titulo));
    $slug_base = preg_replace('/[^a-z0-9]+/', '-', $slug_base);
    $slug_base = trim($slug_base, '-');
    $slug = $slug_base . '-' . time(); // Agregar timestamp para hacerlo único
    
    // Insertar producto en la base de datos (solo columnas que existen)
    $sql = "INSERT INTO productos (
        vendedor_id, 
        categoria_id, 
        titulo, 
        descripcion,
        slug,
        precio, 
        precio_original,
        moneda,
        stock, 
        estado_producto, 
        ubicacion_estado, 
        ubicacion_ciudad,
        activo,
        vendido,
        fecha_publicacion,
        fecha_actualizacion
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW(), NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $vendedor_id,
        $categoria_id,
        $titulo,
        $descripcion,
        $slug,
        $precio,
        $precio_original,
        'MXN', // Moneda por defecto
        $stock,
        $estado_producto,
        $ubicacion_estado,
        $ubicacion_ciudad
    ]);
    
    $producto_id = $pdo->lastInsertId();
    
    // Procesar imágenes si hay
    if (!empty($_FILES['imagenes']['name'][0])) {
        $imagenes = $_FILES['imagenes'];
        $upload_dir = __DIR__ . '/uploads/productos/';
        
        // Crear directorio si no existe
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $es_principal = true;
        foreach ($imagenes['name'] as $key => $name) {
            if ($imagenes['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $imagenes['tmp_name'][$key];
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                $nombre_archivo = 'producto_' . $producto_id . '_' . time() . '_' . $key . '.' . $extension;
                $ruta_completa = $upload_dir . $nombre_archivo;
                
                // Mover archivo
                if (move_uploaded_file($tmp_name, $ruta_completa)) {
                    // Usar ruta absoluta desde la raíz del sitio web
                    $url_imagen = '/frontend/uploads/productos/' . $nombre_archivo;
                    
                    // Guardar en base de datos
                    $sql_img = "INSERT INTO imagenes_productos (producto_id, url_imagen, es_principal, orden) VALUES (?, ?, ?, ?)";
                    $stmt_img = $pdo->prepare($sql_img);
                    $stmt_img->execute([$producto_id, $url_imagen, $es_principal ? 1 : 0, $key + 1]);
                    
                    $es_principal = false; // Solo la primera es principal
                }
            }
        }
    }
    
    $_SESSION['success_message'] = 'Producto publicado exitosamente.';
    header('Location: mis_productos.php');
    exit;
    
} catch (PDOException $e) {
    error_log("Error al publicar producto: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Mostrar error más específico en desarrollo, genérico en producción
    $error_msg = 'Error al guardar el producto. Por favor, intenta de nuevo.';
    if (getenv('APP_DEBUG') === 'true' || isset($_GET['debug'])) {
        $error_msg .= '<br><small>Error: ' . htmlspecialchars($e->getMessage()) . '</small>';
    }
    
    $_SESSION['error_message'] = $error_msg;
    $_SESSION['form_data'] = $_POST;
    header('Location: publicar_producto.php');
    exit;
} catch (Exception $e) {
    error_log("Error inesperado al publicar producto: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error inesperado al guardar el producto. Por favor, intenta de nuevo.';
    $_SESSION['form_data'] = $_POST;
    header('Location: publicar_producto.php');
    exit;
}

