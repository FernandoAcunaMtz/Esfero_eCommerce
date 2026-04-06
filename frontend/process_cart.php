<?php
/**
 * process_cart.php — Operaciones del carrito (PHP/PDO directo, sin backend Python)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';

header('Content-Type: application/json; charset=utf-8');

// ── Auth ─────────────────────────────────────────────────────────────────────
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión para usar el carrito'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Los administradores no pueden usar el carrito'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── PDO disponible ────────────────────────────────────────────────────────────
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user    = get_session_user();
$user_id = (int)($user['id'] ?? 0);
$action  = trim($_POST['action'] ?? $_GET['action'] ?? '');

if (!$user_id || empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Solicitud inválida'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Helper: contar items del carrito ─────────────────────────────────────────
function cart_count(PDO $pdo, int $user_id): int {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(cantidad), 0) FROM carrito WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

try {
    switch ($action) {

        // ── ADD ──────────────────────────────────────────────────────────────
        case 'add':
            $producto_id = (int)($_POST['producto_id'] ?? 0);
            $cantidad    = max(1, min(100, (int)($_POST['cantidad'] ?? 1)));

            if ($producto_id < 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Producto inválido'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Verificar que el producto exista, esté activo y tenga stock
            $stmt = $pdo->prepare(
                "SELECT id, precio, stock, vendedor_id FROM productos
                 WHERE id = ? AND activo = 1 AND vendido = 0 AND stock > 0"
            );
            $stmt->execute([$producto_id]);
            $producto = $stmt->fetch();

            if (!$producto) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Producto no disponible'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // No puedes comprar tu propio producto
            if ((int)$producto['vendedor_id'] === $user_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No puedes comprar tu propio producto'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($cantidad > (int)$producto['stock']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Stock insuficiente'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // INSERT o UPDATE si ya está en el carrito
            $stmt = $pdo->prepare(
                "INSERT INTO carrito (usuario_id, producto_id, cantidad, precio_momento)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     cantidad      = VALUES(cantidad),
                     precio_momento = VALUES(precio_momento)"
            );
            $stmt->execute([$user_id, $producto_id, $cantidad, $producto['precio']]);

            echo json_encode([
                'success'     => true,
                'message'     => 'Producto agregado al carrito',
                'cart_count'  => cart_count($pdo, $user_id),
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ── UPDATE ───────────────────────────────────────────────────────────
        case 'update':
            $carrito_id = (int)($_POST['carrito_id'] ?? 0);
            $cantidad   = max(1, min(100, (int)($_POST['cantidad'] ?? 1)));

            if ($carrito_id < 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de carrito inválido'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Verificar stock disponible
            $stmt = $pdo->prepare(
                "SELECT c.id, p.stock FROM carrito c
                 JOIN productos p ON p.id = c.producto_id
                 WHERE c.id = ? AND c.usuario_id = ?"
            );
            $stmt->execute([$carrito_id, $user_id]);
            $item = $stmt->fetch();

            if (!$item) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Item no encontrado'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($cantidad > (int)$item['stock']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Stock insuficiente'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$cantidad, $carrito_id, $user_id]);

            echo json_encode([
                'success'    => true,
                'cart_count' => cart_count($pdo, $user_id),
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ── REMOVE ───────────────────────────────────────────────────────────
        case 'remove':
            $carrito_id = (int)($_POST['carrito_id'] ?? 0);

            if ($carrito_id < 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de carrito inválido'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM carrito WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$carrito_id, $user_id]);

            echo json_encode([
                'success'    => true,
                'cart_count' => cart_count($pdo, $user_id),
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ── CLEAR ────────────────────────────────────────────────────────────
        case 'clear':
            $stmt = $pdo->prepare("DELETE FROM carrito WHERE usuario_id = ?");
            $stmt->execute([$user_id]);

            echo json_encode(['success' => true, 'cart_count' => 0], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    error_log("process_cart.php PDO error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor'], JSON_UNESCAPED_UNICODE);
}
