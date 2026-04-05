<?php
/**
 * Logout - Cierra sesión del usuario
 * Esfero - Marketplace
 */

session_start();

require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/api_helper.php';

logout_user();
clear_user_token();

header('Location: /index.php');
exit;
?>
