<?php
// API para obtener contadores de favoritos y carrito
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth_middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Verificar que el usuario esté logueado
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$user = get_session_user();
$user_id = $user['id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Usuario no válido']);
    exit;
}

try {
    $favoritos_count = 0;
    $carrito_count = 0;
    
    if (function_exists('get_favoritos_count')) {
        $favoritos_count = get_favoritos_count($user_id);
    }
    
    if (function_exists('get_carrito_count')) {
        $carrito_count = get_carrito_count($user_id);
    }
    
    echo json_encode([
        'success' => true,
        'favoritos' => $favoritos_count,
        'carrito' => $carrito_count
    ]);
} catch (Exception $e) {
    error_log("Error en api_get_counters.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener contadores',
        'favoritos' => 0,
        'carrito' => 0
    ]);
}

