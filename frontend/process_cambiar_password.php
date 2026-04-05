<?php
/**
 * Procesa el cambio de contraseña del usuario autenticado.
 * Verifica la contraseña actual antes de permitir el cambio.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: configuracion.php#seguridad');
    exit;
}

csrf_verify();
require_login();

$user       = get_session_user();
$usuario_id = $user['id'] ?? null;

if (!$usuario_id || !isset($pdo)) {
    redirect_with_message('configuracion.php', 'Error de sesión. Vuelve a iniciar sesión.', 'error');
    exit;
}

$password_actual  = $_POST['password_actual']  ?? '';
$password_nueva   = $_POST['password_nueva']   ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// ── Validaciones ──────────────────────────────────────────────────────────────

if (empty($password_actual) || empty($password_nueva) || empty($password_confirm)) {
    redirect_with_message('configuracion.php', 'Todos los campos son obligatorios.', 'error');
    exit;
}

if (strlen($password_nueva) < 8) {
    redirect_with_message('configuracion.php', 'La nueva contraseña debe tener al menos 8 caracteres.', 'error');
    exit;
}

if ($password_nueva !== $password_confirm) {
    redirect_with_message('configuracion.php', 'La nueva contraseña y su confirmación no coinciden.', 'error');
    exit;
}

if ($password_actual === $password_nueva) {
    redirect_with_message('configuracion.php', 'La nueva contraseña debe ser diferente a la actual.', 'error');
    exit;
}

// ── Verificar contraseña actual ───────────────────────────────────────────────

try {
    $stmt = $pdo->prepare("SELECT password_hash FROM usuarios WHERE id = ? AND estado = 'activo'");
    $stmt->execute([$usuario_id]);
    $row = $stmt->fetch();

    if (!$row) {
        redirect_with_message('configuracion.php', 'Usuario no encontrado.', 'error');
        exit;
    }

    // password_verify() es compatible con hashes $2b$ (Python bcrypt) y $2y$ (PHP bcrypt)
    if (!password_verify($password_actual, $row['password_hash'])) {
        redirect_with_message('configuracion.php', 'La contraseña actual es incorrecta.', 'error');
        exit;
    }

} catch (PDOException $e) {
    error_log('process_cambiar_password — verificación: ' . $e->getMessage());
    redirect_with_message('configuracion.php', 'Error al verificar la contraseña. Intenta de nuevo.', 'error');
    exit;
}

// ── Actualizar contraseña ─────────────────────────────────────────────────────

try {
    // PASSWORD_BCRYPT genera hash $2y$ compatible con Python's bcrypt.checkpw()
    $nuevo_hash = password_hash($password_nueva, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
    $stmt->execute([$nuevo_hash, $usuario_id]);

    if ($stmt->rowCount() === 0) {
        redirect_with_message('configuracion.php', 'No se pudo actualizar la contraseña. Intenta de nuevo.', 'error');
        exit;
    }

    redirect_with_message('configuracion.php', 'Contraseña actualizada correctamente.', 'success');

} catch (PDOException $e) {
    error_log('process_cambiar_password — actualización: ' . $e->getMessage());
    redirect_with_message('configuracion.php', 'Error al guardar la contraseña. Intenta de nuevo.', 'error');
    exit;
}
