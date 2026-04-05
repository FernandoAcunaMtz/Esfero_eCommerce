<?php
/**
 * CSRF Protection Helper
 * Esfero Marketplace
 */

/**
 * Genera un token CSRF y lo almacena en la sesión.
 * Si ya existe uno válido lo reutiliza.
 */
function csrf_generate(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Devuelve el campo HTML hidden con el token CSRF.
 */
function csrf_field(): string {
    $token = csrf_generate();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifica que el token CSRF del request sea válido.
 * Aborta con 403 si no lo es.
 */
function csrf_verify(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $token_recibido = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $token_esperado = $_SESSION['csrf_token'] ?? '';

    if (!$token_esperado || !hash_equals($token_esperado, $token_recibido)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido o expirado']);
        exit;
    }
}
