<?php
/**
 * Procesa el registro directamente contra MySQL (sin pasar por el CGI Python)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/assets/db_direct.php';
require_once __DIR__ . '/includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registro.php');
    exit;
}

// Validar y sanitizar campos
$nombre           = strip_tags(trim($_POST['nombre'] ?? ''));
$apellidos        = strip_tags(trim($_POST['apellidos'] ?? ''));
$email            = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$password         = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$rol              = 'usuario'; // Siempre usuario; no se acepta del POST

if (strlen($nombre) > 100) {
    $_SESSION['register_error'] = 'El nombre es demasiado largo (máximo 100 caracteres).';
    header('Location: registro.php'); exit;
}
if (empty($nombre)) {
    $_SESSION['register_error'] = 'El nombre es requerido.';
    header('Location: registro.php'); exit;
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['register_error'] = 'Ingresa un correo electrónico válido.';
    header('Location: registro.php'); exit;
}
if (strlen($password) < 8) {
    $_SESSION['register_error'] = 'La contraseña debe tener al menos 8 caracteres.';
    header('Location: registro.php'); exit;
}
if ($password !== $password_confirm) {
    $_SESSION['register_error'] = 'Las contraseñas no coinciden.';
    header('Location: registro.php'); exit;
}

// (rol ya fijado arriba como 'usuario')

try {
    $conn = get_db_connection_php();

    // Verificar email duplicado
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $conn->close();
        $_SESSION['register_error'] = 'Este correo ya está registrado.';
        header('Location: registro.php');
        exit;
    }
    $stmt->close();

    // Hash bcrypt (compatible con Python bcrypt)
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // Detectar columna de contraseña
    $col_check = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'password_hash'");
    $pass_col  = ($col_check && $col_check->num_rows > 0) ? 'password_hash' : 'password';

    $stmt = $conn->prepare(
        "INSERT INTO usuarios (email, nombre, apellidos, $pass_col, rol, estado, fecha_registro)
         VALUES (?, ?, ?, ?, ?, 'activo', NOW())"
    );
    $stmt->bind_param('sssss', $email, $nombre, $apellidos, $password_hash, $rol);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Registro DB error: ' . $e->getMessage());
    $_SESSION['register_error'] = 'Error de conexión a la base de datos.';
    header('Location: registro.php');
    exit;
}

// Guardar sesión
$user_data = [
    'id'        => $new_id,
    'email'     => $email,
    'nombre'    => $nombre,
    'apellidos' => $apellidos,
    'rol'       => $rol,
];

save_session_user($user_data);
save_user_token(bin2hex(random_bytes(32)));

$_SESSION['last_activity'] = time();
$_SESSION['login_time']    = time();

// Enviar correo de bienvenida (fallo silencioso — no bloquea el registro)
enviar_bienvenida($email, $nombre);

header('Location: ' . get_redirect_by_role($rol));
exit;
