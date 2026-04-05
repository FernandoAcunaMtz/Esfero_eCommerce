<?php
// Manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth_middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que el usuario esté logueado
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión para usar favoritos']);
    exit;
}

// Obtener usuario actual
$user = get_session_user();
$usuario_id = $user['id'] ?? null;

if (!$usuario_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Usuario no válido']);
    exit;
}

// Obtener acción y producto_id
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$producto_id = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : (isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0);

if (!$producto_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID de producto no válido']);
    exit;
}

// Validar que el producto existe
try {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM productos WHERE id = ? AND activo = 1");
    $stmt->execute([$producto_id]);
    if (!$stmt->fetch()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
        exit;
    }
} catch (Exception $e) {
    error_log("Error validando producto: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error al validar producto']);
    exit;
}

// Procesar acción
header('Content-Type: application/json');

try {
    if ($action === 'add' || $action === 'toggle') {
        // Verificar si ya está en favoritos
        if (esFavorito($usuario_id, $producto_id)) {
            if ($action === 'toggle') {
                // Si es toggle y ya está, eliminarlo
                $result = eliminarFavorito($usuario_id, $producto_id);
            } else {
                $result = ['success' => false, 'error' => 'El producto ya está en favoritos'];
            }
        } else {
            // Agregar a favoritos
            $result = agregarFavorito($usuario_id, $producto_id);
        }
    } elseif ($action === 'remove') {
        // Eliminar de favoritos
        $result = eliminarFavorito($usuario_id, $producto_id);
    } else {
        $result = ['success' => false, 'error' => 'Acción no válida'];
    }
    
    echo json_encode($result);
} catch (Exception $e) {
    error_log("Error en process_favoritos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al procesar la solicitud']);
}

