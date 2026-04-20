<?php
/**
 * Maneja el retorno de PayPal después del pago
 */
session_start();
require_once __DIR__ . '/includes/auth_middleware.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Obtener parámetros de PayPal
$token = $_GET['token'] ?? '';
$PayerID = $_GET['PayerID'] ?? '';

if (empty($token) || empty($PayerID)) {
    $_SESSION['error_message'] = 'Error: No se recibieron los parámetros necesarios de PayPal.';
    header('Location: checkout.php?error=paypal_return');
    exit;
}

// Obtener la URL base dinámicamente
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Procesando Pago - Esfero</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        h1 { color: #333; margin-bottom: 1rem; }
        p { color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <h1>Procesando tu pago...</h1>
        <p>Por favor espera mientras completamos la transacción.</p>
    </div>

    <script>
        // Capturar el pago de PayPal
        async function capturePayment() {
            try {
                const response = await fetch('api_checkout_directo.php?action=capture_paypal', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        paypal_order_id: '<?php echo htmlspecialchars($token, ENT_QUOTES); ?>',
                        token: '<?php echo htmlspecialchars($token, ENT_QUOTES); ?>'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Redirigir a la página de éxito
                    window.location.href = 'compras.php?success=1&mensaje=' + encodeURIComponent('Pago completado exitosamente');
                } else {
                    // Error al capturar
                    window.location.href = 'checkout.php?error=' + encodeURIComponent(result.error || 'Error al procesar el pago');
                }
            } catch (error) {
                console.error('Error:', error);
                window.location.href = 'checkout.php?error=' + encodeURIComponent('Error de conexión al procesar el pago');
            }
        }

        // Ejecutar al cargar
        capturePayment();
    </script>
</body>
</html>

