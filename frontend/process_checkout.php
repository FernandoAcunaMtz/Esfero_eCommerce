<?php
/**
 * process_checkout.php — Crea la orden en DB + orden en PayPal
 * Llamado por checkout.php vía fetch (JSON)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';

if (!is_logged_in() || is_admin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
    exit;
}

$user    = get_session_user();
$user_id = (int)($user['id'] ?? 0);

// ── Leer body JSON ────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$nombre      = trim($body['nombre_destinatario']   ?? '');
$direccion   = trim($body['direccion_envio']        ?? '');
$ciudad      = trim($body['ciudad_envio']           ?? '');
$estado      = trim($body['estado_envio']           ?? '');
$cp          = trim($body['codigo_postal_envio']    ?? '');
$telefono    = trim($body['telefono_envio']         ?? '');
$notas       = trim($body['notas_comprador']        ?? '');

if (!$nombre || !$direccion || !$ciudad || !$estado || !$cp || !$telefono) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan datos de envío']);
    exit;
}

// ── Cargar carrito ────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT c.id AS carrito_id, c.cantidad,
           p.id AS producto_id, p.titulo, p.precio, p.stock,
           p.vendedor_id, p.descripcion,
           (SELECT url_imagen FROM imagenes_productos WHERE producto_id = p.id AND es_principal = 1 LIMIT 1) AS imagen_principal
    FROM carrito c
    JOIN productos p ON p.id = c.producto_id
    WHERE c.usuario_id = ? AND p.activo = 1 AND p.vendido = 0 AND p.stock > 0
");
$stmt->execute([$user_id]);
$items = $stmt->fetchAll();

if (empty($items)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El carrito está vacío']);
    exit;
}

$subtotal = 0.0;
foreach ($items as $item) {
    $subtotal += (float)$item['precio'] * (int)$item['cantidad'];
}
$total = round($subtotal, 2);

// ── Crear orden en DB (estado pendiente) ─────────────────────────────────────
try {
    $pdo->beginTransaction();

    // Un carrito puede tener productos de distintos vendedores.
    // Creamos una orden por vendedor distinto.
    $vendedores = [];
    foreach ($items as $item) {
        $vid = (int)$item['vendedor_id'];
        $vendedores[$vid][] = $item;
    }

    $orden_ids = [];
    foreach ($vendedores as $vendedor_id => $vendor_items) {
        $sub = 0.0;
        foreach ($vendor_items as $vi) {
            $sub += (float)$vi['precio'] * (int)$vi['cantidad'];
        }
        $numero = 'ESF-' . strtoupper(substr(uniqid(), -8)) . '-' . date('Ymd');

        $stmt = $pdo->prepare("
            INSERT INTO ordenes
                (numero_orden, comprador_id, vendedor_id, subtotal, envio, total,
                 direccion_envio, ciudad_envio, estado_envio, codigo_postal_envio,
                 telefono_envio, estado, estado_pago)
            VALUES (?,?,?,?,0,?,?,?,?,?,?,'pendiente','pendiente')
        ");
        $stmt->execute([
            $numero, $user_id, $vendedor_id,
            round($sub, 2), round($sub, 2),
            $direccion, $ciudad, $estado, $cp, $telefono
        ]);
        $orden_id = (int)$pdo->lastInsertId();
        $orden_ids[] = $orden_id;

        foreach ($vendor_items as $vi) {
            $stmt2 = $pdo->prepare("
                INSERT INTO orden_items
                    (orden_id, producto_id, cantidad, precio_unitario, subtotal,
                     producto_titulo, producto_descripcion, producto_imagen)
                VALUES (?,?,?,?,?,?,?,?)
            ");
            $item_sub = round((float)$vi['precio'] * (int)$vi['cantidad'], 2);
            $stmt2->execute([
                $orden_id, $vi['producto_id'], $vi['cantidad'],
                $vi['precio'], $item_sub,
                $vi['titulo'], $vi['descripcion'] ?? '', $vi['imagen_principal'] ?? ''
            ]);
        }
    }

    // Guardar orden_ids en sesión para el capture
    $_SESSION['pending_orden_ids'] = $orden_ids;
    $_SESSION['pending_total']     = $total;
    $_SESSION['pending_user_id']   = $user_id;

    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('process_checkout PDO: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al crear la orden']);
    exit;
}

// ── Crear orden en PayPal ─────────────────────────────────────────────────────
$client_id     = getenv('PAYPAL_CLIENT_ID');
$client_secret = getenv('PAYPAL_CLIENT_SECRET');
$mode          = getenv('PAYPAL_MODE') ?: 'sandbox';
$currency      = getenv('PAYPAL_CURRENCY') ?: 'MXN';
$base_url      = $mode === 'live'
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';

// Obtener access token
$token_response = paypal_request($base_url . '/v1/oauth2/token', 'POST',
    'grant_type=client_credentials',
    ['Content-Type: application/x-www-form-urlencoded'],
    $client_id . ':' . $client_secret
);

if (empty($token_response['access_token'])) {
    error_log('PayPal token error: ' . json_encode($token_response));
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Error al conectar con PayPal']);
    exit;
}
$access_token = $token_response['access_token'];

// Crear la orden PayPal
$order_payload = json_encode([
    'intent'         => 'CAPTURE',
    'purchase_units' => [[
        'amount'      => ['currency_code' => $currency, 'value' => number_format($total, 2, '.', '')],
        'description' => 'Compra en Esfero Marketplace',
    ]],
]);

$pp_order = paypal_request(
    $base_url . '/v2/checkout/orders',
    'POST',
    $order_payload,
    [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'PayPal-Request-Id: ' . uniqid('esfero-', true),
    ]
);

if (empty($pp_order['id'])) {
    error_log('PayPal order error: ' . json_encode($pp_order));
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Error al crear orden en PayPal']);
    exit;
}

// Guardar paypal_order_id en las órdenes DB
foreach ($orden_ids as $oid) {
    $pdo->prepare("UPDATE ordenes SET paypal_order_id = ? WHERE id = ?")
        ->execute([$pp_order['id'], $oid]);
}

echo json_encode([
    'success'        => true,
    'paypal_order_id' => $pp_order['id'],
]);

// ── Helper cURL ───────────────────────────────────────────────────────────────
function paypal_request(string $url, string $method, $body, array $headers, ?string $userpwd = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($userpwd) curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp ?: '{}', true) ?? [];
}
