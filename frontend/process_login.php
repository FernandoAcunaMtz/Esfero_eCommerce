<?php
/**
 * Procesa el login directamente contra MySQL (sin pasar por el CGI Python)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/assets/db_direct.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

csrf_verify();

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$email_validado = filter_var($email, FILTER_VALIDATE_EMAIL);

if (empty($email) || $email_validado === false || empty($password)) {
    $_SESSION['login_error'] = 'Ingresa un correo válido y tu contraseña.';
    header('Location: login.php');
    exit;
}

$email = $email_validado;

// ── Rate limiting ────────────────────────────────────────────────────────────
// Usa $pdo de auth_middleware (ya incluido). Si la tabla no existe aún, ignora.
$rl = verificar_rate_limit($email);
if ($rl['bloqueado'] ?? false) {
    $mins = $rl['minutos'] ?? LOGIN_BLOQUEO_MINS;
    $_SESSION['login_error'] = "Demasiados intentos fallidos. Espera $mins minuto(s) antes de intentarlo de nuevo.";
    header('Location: login.php');
    exit;
}

try {
    $conn = get_db_connection_php();

    // Detectar columna de contraseña (password_hash o password)
    $col_check = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'password_hash'");
    $pass_col  = ($col_check && $col_check->num_rows > 0) ? 'password_hash' : 'password';

    $stmt = $conn->prepare(
        "SELECT u.id, u.email, u.nombre, u.apellidos, u.rol, u.estado, u.puede_vender,
                u.$pass_col AS stored_hash, p.foto_perfil
         FROM usuarios u
         LEFT JOIN perfiles p ON p.usuario_id = u.id
         WHERE u.email = ? LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Login DB error: ' . $e->getMessage());
    $_SESSION['login_error'] = 'Error de conexión a la base de datos.';
    header('Location: login.php');
    exit;
}

// PHP espera $2y$, Python genera $2b$ — misma implementación, distinto prefijo
$hash = str_replace('$2b$', '$2y$', $user['stored_hash'] ?? '');
if (!$user || !password_verify($password, $hash)) {
    registrar_intento_fallido($email);   // contabiliza intento fallido
    $_SESSION['login_error'] = 'Credenciales inválidas.';
    header('Location: login.php');
    exit;
}

if ($user['estado'] !== 'activo') {
    $_SESSION['login_error'] = 'Tu cuenta está inactiva.';
    header('Location: login.php');
    exit;
}

// Guardar sesión — incluir puede_vender para evitar consulta DB en cada página
$user_data = [
    'id'           => $user['id'],
    'email'        => $user['email'],
    'nombre'       => $user['nombre'],
    'apellidos'    => $user['apellidos'],
    'rol'          => $user['rol'],
    'puede_vender' => (int)($user['puede_vender'] ?? 0),
    'foto_perfil'  => $user['foto_perfil'] ?? null,
];

save_session_user($user_data);
// Token de sesión simple (no JWT) — suficiente para is_logged_in()
save_user_token(bin2hex(random_bytes(32)));

// Login exitoso → limpiar intentos fallidos
limpiar_intentos_login($email);

$_SESSION['last_activity'] = time();
$_SESSION['login_time']    = time();

$redirect = get_redirect_by_role($user['rol']);
if (isset($_SESSION['redirect_after_login'])) {
    $redirect = $_SESSION['redirect_after_login'];
    unset($_SESSION['redirect_after_login']);
}

header('Location: ' . $redirect);
exit;
