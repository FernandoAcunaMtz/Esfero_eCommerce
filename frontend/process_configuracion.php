<?php
/**
 * Procesa la actualización de datos personales del usuario.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: configuracion.php');
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

// ── Leer y sanitizar campos ───────────────────────────────────────────────────

$nombre    = trim($_POST['nombre']    ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$telefono  = trim($_POST['telefono']  ?? '');
$descripcion       = trim($_POST['descripcion']       ?? '');
$ubicacion_estado  = trim($_POST['ubicacion_estado']  ?? '');
$ubicacion_ciudad  = trim($_POST['ubicacion_ciudad']  ?? '');
$codigo_postal     = trim($_POST['codigo_postal']     ?? '');

// ── Validaciones ──────────────────────────────────────────────────────────────

if (empty($nombre)) {
    redirect_with_message('configuracion.php', 'El nombre es obligatorio.', 'error');
    exit;
}
if (mb_strlen($nombre) > 100 || mb_strlen($apellidos) > 100) {
    redirect_with_message('configuracion.php', 'El nombre o apellidos son demasiado largos.', 'error');
    exit;
}
if (!empty($telefono) && !preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $telefono)) {
    redirect_with_message('configuracion.php', 'El formato del teléfono no es válido.', 'error');
    exit;
}

// ── Actualizar usuarios ───────────────────────────────────────────────────────

try {
    $stmt = $pdo->prepare("
        UPDATE usuarios
        SET nombre = ?, apellidos = ?, telefono = ?
        WHERE id = ?
    ");
    $stmt->execute([$nombre, $apellidos, $telefono, $usuario_id]);

    // ── Upsert perfiles ───────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO perfiles (usuario_id, descripcion, ubicacion_estado, ubicacion_ciudad, codigo_postal)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            descripcion      = VALUES(descripcion),
            ubicacion_estado = VALUES(ubicacion_estado),
            ubicacion_ciudad = VALUES(ubicacion_ciudad),
            codigo_postal    = VALUES(codigo_postal)
    ");
    $stmt->execute([$usuario_id, $descripcion, $ubicacion_estado, $ubicacion_ciudad, $codigo_postal]);

    // Actualizar nombre en sesión
    $_SESSION['user_data']['nombre']    = $nombre;
    $_SESSION['user_data']['apellidos'] = $apellidos;

    redirect_with_message('configuracion.php', 'Datos actualizados correctamente.', 'success');

} catch (PDOException $e) {
    error_log('process_configuracion — ' . $e->getMessage());
    redirect_with_message('configuracion.php', 'Error al guardar los cambios. Intenta de nuevo.', 'error');
    exit;
}
