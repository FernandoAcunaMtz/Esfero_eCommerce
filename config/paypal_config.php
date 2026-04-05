<?php
/**
 * Configuración de PayPal
 * Esfero - Marketplace
 */

// Cargar variables de entorno antes de usarlas
require_once __DIR__ . '/load_env.php';

return [
    'mode' => getenv('PAYPAL_MODE') ?: 'sandbox', // 'sandbox' o 'live'
    'client_id' => getenv('PAYPAL_CLIENT_ID') ?: 'your-paypal-client-id',
    'client_secret' => getenv('PAYPAL_CLIENT_SECRET') ?: 'your-paypal-client-secret',
    'sandbox_url' => 'https://api.sandbox.paypal.com',
    'live_url' => 'https://api.paypal.com',
    'currency' => 'MXN',
    'return_url' => getenv('PAYPAL_RETURN_URL') ?: 'http://localhost/esfero/frontend/checkout.php?success=true',
    'cancel_url' => getenv('PAYPAL_CANCEL_URL') ?: 'http://localhost/esfero/frontend/checkout.php?canceled=true'
];
