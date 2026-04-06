<?php
/**
 * paypal_capture.php — Captura el pago aprobado por PayPal y confirma la orden
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/mailer.php';

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

// ── Validar sesión de compra ──────────────────────────────────────────────────
$orden_ids      = $_SESSION['pending_orden_ids'] ?? [];
$pending_user   = (int)($_SESSION['pending_user_id'] ?? 0);

if (empty($orden_ids) || $pending_user !== $user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sesión de compra inválida']);
    exit;
}

// ── Leer body JSON ────────────────────────────────────────────────────────────
$body           = json_decode(file_get_contents('php://input'), true) ?? [];
$paypal_order_id = trim($body['paypal_order_id'] ?? '');

if (!$paypal_order_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de orden PayPal requerido']);
    exit;
}

// ── Capturar pago en PayPal ───────────────────────────────────────────────────
$client_id     = getenv('PAYPAL_CLIENT_ID');
$client_secret = getenv('PAYPAL_CLIENT_SECRET');
$mode          = getenv('PAYPAL_MODE') ?: 'sandbox';
$base_url      = $mode === 'live'
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';

// Access token
$token_resp = paypal_request(
    $base_url . '/v1/oauth2/token', 'POST',
    'grant_type=client_credentials',
    ['Content-Type: application/x-www-form-urlencoded'],
    $client_id . ':' . $client_secret
);

if (empty($token_resp['access_token'])) {
    error_log('paypal_capture: token error ' . json_encode($token_resp));
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Error al conectar con PayPal']);
    exit;
}
$access_token = $token_resp['access_token'];

// Capture
$capture_resp = paypal_request(
    $base_url . '/v2/checkout/orders/' . $paypal_order_id . '/capture',
    'POST', '{}',
    [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
    ]
);

$pp_status = $capture_resp['status'] ?? '';

if ($pp_status !== 'COMPLETED') {
    error_log('paypal_capture: unexpected status ' . json_encode($capture_resp));
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'El pago no fue completado por PayPal']);
    exit;
}

// Extraer transaction ID
$transaction_id = $capture_resp['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';
$payer_id       = $capture_resp['payer']['payer_id'] ?? '';

// ── Confirmar órdenes en DB ───────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    foreach ($orden_ids as $orden_id) {
        // Verificar que la orden pertenezca a este usuario y al paypal_order_id
        $stmt = $pdo->prepare(
            "SELECT id, vendedor_id FROM ordenes
             WHERE id = ? AND comprador_id = ? AND paypal_order_id = ? AND estado_pago = 'pendiente'"
        );
        $stmt->execute([$orden_id, $user_id, $paypal_order_id]);
        $orden = $stmt->fetch();

        if (!$orden) continue; // seguridad: saltar si no coincide

        // Actualizar orden
        $pdo->prepare("
            UPDATE ordenes SET
                estado               = 'pago_confirmado',
                estado_pago          = 'completado',
                paypal_payer_id      = ?,
                id_transaccion_paypal = ?,
                fecha_pago           = NOW()
            WHERE id = ?
        ")->execute([$payer_id, $transaction_id, $orden_id]);

        // Descontar stock y marcar vendido si stock llega a 0
        $items_stmt = $pdo->prepare(
            "SELECT producto_id, cantidad FROM orden_items WHERE orden_id = ?"
        );
        $items_stmt->execute([$orden_id]);
        $order_items = $items_stmt->fetchAll();

        foreach ($order_items as $oi) {
            $pdo->prepare("
                UPDATE productos
                SET stock = GREATEST(0, stock - ?)
                WHERE id = ?
            ")->execute([$oi['cantidad'], $oi['producto_id']]);

            // Si stock = 0, marcar como vendido
            $pdo->prepare("
                UPDATE productos SET vendido = 1
                WHERE id = ? AND stock = 0
            ")->execute([$oi['producto_id']]);
        }
    }

    // Vaciar carrito
    $pdo->prepare("DELETE FROM carrito WHERE usuario_id = ?")
        ->execute([$user_id]);

    // Limpiar sesión de compra
    unset($_SESSION['pending_orden_ids'], $_SESSION['pending_total'], $_SESSION['pending_user_id']);

    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('paypal_capture PDO: ' . $e->getMessage());
    // El pago ya fue capturado en PayPal — loguear para revisión manual
    echo json_encode([
        'success' => false,
        'error'   => 'Pago recibido pero error al confirmar la orden. Contacta a soporte con tu ID: ' . $paypal_order_id,
    ]);
    exit;
}

// Enviar confirmación de compra por correo
$stmt_mail = $pdo->prepare("
    SELECT u.email, u.nombre,
           o.numero_orden, o.total,
           oi.producto_titulo AS titulo, oi.cantidad, oi.precio_unitario AS precio
    FROM ordenes o
    JOIN usuarios u ON u.id = o.comprador_id
    JOIN orden_items oi ON oi.orden_id = o.id
    WHERE o.id = ?
");
$stmt_mail->execute([$orden_ids[0] ?? 0]);
$rows_mail = $stmt_mail->fetchAll();

if (!empty($rows_mail)) {
    $items_mail = array_map(fn($r) => [
        'titulo'   => $r['titulo'],
        'cantidad' => $r['cantidad'],
        'precio'   => $r['precio'],
    ], $rows_mail);

    enviar_confirmacion_orden(
        $rows_mail[0]['email'],
        $rows_mail[0]['nombre'],
        $rows_mail[0]['numero_orden'],
        (float)$rows_mail[0]['total'],
        $items_mail
    );
}

echo json_encode([
    'success'        => true,
    'orden_id'       => $orden_ids[0] ?? 0,
    'transaction_id' => $transaction_id,
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
