<?php
/**
 * Actualiza el perfil del usuario actual en la base de datos
 */

session_start();
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/db_connection.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil.php?tab=perfil');
    exit;
}

$user = get_session_user();
$usuario_id = $user['id'] ?? null;

if (!$usuario_id || !isset($pdo)) {
    $_SESSION['error_message'] = 'Error al identificar usuario.';
    header('Location: perfil.php?tab=perfil');
    exit;
}

// Funciones simples de sanitización
function simple_sanitize_text($text, $max_length = null) {
    if ($text === null || $text === '') {
        return '';
    }
    $text = (string)$text;
    $text = strip_tags($text);
    $text = trim($text);
    if ($max_length !== null && $max_length > 0 && strlen($text) > $max_length) {
        $text = substr($text, 0, $max_length);
    }
    return $text;
}

function simple_sanitize_email($email) {
    $email = trim((string)$email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : false;
}

function simple_sanitize_telefono($telefono) {
    $telefono = trim((string)$telefono);
    $telefono = preg_replace('/[^0-9+\-() ]/', '', $telefono);
    if (strlen($telefono) > 20) {
        return false;
    }
    return $telefono;
}

function simple_sanitize_codigo_postal($cp) {
    $cp = trim((string)$cp);
    $cp = preg_replace('/[^0-9]/', '', $cp);
    if (strlen($cp) > 10) {
        return false;
    }
    return $cp;
}

function simple_sanitize_url($url) {
    $url = trim((string)$url);
    if (empty($url)) {
        return null;
    }
    return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
}

// Sanitizar y validar todos los campos
$nombre           = simple_sanitize_text($_POST['nombre'] ?? '', 255);
$apellidos        = simple_sanitize_text($_POST['apellidos'] ?? '', 255);
$telefono         = simple_sanitize_telefono($_POST['telefono'] ?? '');
$email            = simple_sanitize_email($_POST['email'] ?? '');
$ubicacion_ciudad = simple_sanitize_text($_POST['ubicacion_ciudad'] ?? '', 100);
$ubicacion_estado = simple_sanitize_text($_POST['ubicacion_estado'] ?? '', 100);
$codigo_postal    = simple_sanitize_codigo_postal($_POST['codigo_postal'] ?? '');

// Manejar subida de foto de perfil — leer actual desde perfiles
$foto_perfil_actual = null;
try {
    $s = $pdo->prepare("SELECT foto_perfil FROM perfiles WHERE usuario_id = ?");
    $s->execute([$usuario_id]);
    $foto_perfil_actual = $s->fetchColumn() ?: null;
} catch (PDOException $e) {}

$foto_perfil = $foto_perfil_actual; // conservar la actual por defecto

if (!empty($_FILES['foto_file']['tmp_name'])) {
    $file     = $_FILES['foto_file'];
    $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime     = mime_content_type($file['tmp_name']);

    if (!isset($allowed[$mime])) {
        $_SESSION['error_message'] = 'Formato de imagen no permitido. Usa JPG, PNG o WebP.';
        header('Location: perfil.php?tab=perfil');
        exit;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        $_SESSION['error_message'] = 'La imagen no puede superar 2 MB.';
        header('Location: perfil.php?tab=perfil');
        exit;
    }

    $upload_dir = __DIR__ . '/uploads/avatars/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext           = $allowed[$mime];
    $nombre_archivo = 'avatar_' . $usuario_id . '_' . time() . '.' . $ext;
    $ruta_completa  = $upload_dir . $nombre_archivo;

    if (move_uploaded_file($file['tmp_name'], $ruta_completa)) {
        $foto_perfil = '/uploads/avatars/' . $nombre_archivo;
        // Borrar avatar anterior si era local
        if ($foto_perfil_actual && str_starts_with($foto_perfil_actual, '/uploads/avatars/')) {
            $old = __DIR__ . $foto_perfil_actual;
            if (file_exists($old)) @unlink($old);
        }
    } else {
        $_SESSION['error_message'] = 'Error al guardar la imagen. Intenta de nuevo.';
        header('Location: perfil.php?tab=perfil');
        exit;
    }
}

// Validaciones
$errors = [];

if (empty($nombre)) {
    $errors[] = 'El nombre es requerido.';
}

if ($email === false) {
    $errors[] = 'El email no es válido.';
}

// Verificar si el email ya existe en otro usuario
if ($email !== false) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $stmt->execute([$email, $usuario_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Este email ya está registrado por otro usuario.';
        }
    } catch (PDOException $e) {
        error_log("Error verificando email: " . $e->getMessage());
    }
}

if (!empty($errors)) {
    $_SESSION['error_message'] = implode('<br>', $errors);
    $_SESSION['form_data'] = $_POST;
    header('Location: perfil.php?tab=perfil');
    exit;
}

// Actualizar en la base de datos
try {
    $pdo->beginTransaction();

    // 1. Actualizar solo columnas que existen en usuarios
    $pdo->prepare("
        UPDATE usuarios SET nombre = ?, apellidos = ?, email = ?, telefono = ?
        WHERE id = ?
    ")->execute([$nombre, $apellidos ?: null, $email, $telefono ?: null, $usuario_id]);

    // 2. Upsert en perfiles (foto + ubicación)
    $pdo->prepare("
        INSERT INTO perfiles (usuario_id, foto_perfil, ubicacion_ciudad, ubicacion_estado, codigo_postal)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            foto_perfil      = VALUES(foto_perfil),
            ubicacion_ciudad = VALUES(ubicacion_ciudad),
            ubicacion_estado = VALUES(ubicacion_estado),
            codigo_postal    = VALUES(codigo_postal)
    ")->execute([
        $usuario_id,
        $foto_perfil,
        $ubicacion_ciudad ?: null,
        $ubicacion_estado ?: null,
        $codigo_postal ?: null,
    ]);

    $pdo->commit();

    // Actualizar sesión para que navbar refleje cambios
    if (isset($_SESSION['user'])) {
        $_SESSION['user']['nombre']      = $nombre;
        $_SESSION['user']['apellidos']   = $apellidos;
        $_SESSION['user']['email']       = $email;
        $_SESSION['user']['telefono']    = $telefono;
        $_SESSION['user']['foto_perfil'] = $foto_perfil;
    }

    $_SESSION['success_message'] = 'Perfil actualizado correctamente.';
    header('Location: perfil.php?tab=perfil');
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error al actualizar perfil: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error al actualizar el perfil. Por favor, intenta de nuevo.';
    header('Location: perfil.php?tab=perfil');
    exit;
}
