<?php
/**
 * Procesa la activación de cuenta de vendedor.
 * Auto-aprueba al usuario: activa puede_vender = 1.
 * El rol siempre permanece 'usuario' (sistema de dos roles: usuario / admin).
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: activar_vendedor.php');
    exit;
}

csrf_verify();

require_login();
$user = get_session_user();

// Admins no necesitan esto
if ($user['rol'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

// Ya es vendedor
if (puede_vender($user['id'])) {
    redirect_with_message('vendedor_dashboard.php', 'Tu cuenta ya tiene permisos de vendedor.', 'info');
    exit;
}

// Verificar checkboxes obligatorios
if (empty($_POST['acepto_terminos']) || empty($_POST['acepto_compromiso'])) {
    redirect_with_message('activar_vendedor.php', 'Debes aceptar los términos y el compromiso para continuar.', 'error');
    exit;
}

if (!isset($pdo)) {
    redirect_with_message('activar_vendedor.php', 'Error de conexión a la base de datos.', 'error');
    exit;
}

$descripcion    = strip_tags(trim($_POST['descripcion']    ?? ''));
$tipo_productos = strip_tags(trim($_POST['tipo_productos'] ?? ''));
$descripcion    = mb_substr($descripcion,    0, 500);
$tipo_productos = mb_substr($tipo_productos, 0, 200);

try {
    $pdo->beginTransaction();

    // 1. Activar puede_vender (el rol permanece 'usuario')
    $stmt = $pdo->prepare(
        "UPDATE usuarios SET puede_vender = 1 WHERE id = ? AND estado = 'activo'"
    );
    $stmt->execute([$user['id']]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        redirect_with_message('activar_vendedor.php', 'No se pudo actualizar tu cuenta. Intenta de nuevo.', 'error');
        exit;
    }

    // 2. Guardar/actualizar descripción en perfiles si se proporcionó
    if (!empty($descripcion) || !empty($tipo_productos)) {
        $desc_completa = $descripcion;
        if (!empty($tipo_productos)) {
            $desc_completa = "Vende: $tipo_productos" . ($descripcion ? "\n$descripcion" : '');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO perfiles (usuario_id, descripcion)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE descripcion = IF(descripcion IS NULL OR descripcion = '', VALUES(descripcion), descripcion)"
        );
        $stmt->execute([$user['id'], $desc_completa]);
    }

    // 3. Registrar solicitud como aprobada (para historial)
    $stmt = $pdo->prepare(
        "INSERT INTO vendedor_solicitudes (usuario_id, descripcion, acepto_terminos, estado, fecha_resolucion)
         VALUES (?, ?, 1, 'aprobada', NOW())
         ON DUPLICATE KEY UPDATE
           estado = 'aprobada',
           descripcion = VALUES(descripcion),
           fecha_resolucion = NOW()"
    );
    $stmt->execute([$user['id'], mb_substr($descripcion, 0, 500)]);

    $pdo->commit();

    // 4. Actualizar datos de sesión para reflejar puede_vender inmediatamente (rol no cambia)
    $_SESSION['user']['puede_vender'] = 1;

    redirect_with_message(
        'vendedor_dashboard.php',
        '¡Felicidades! Tu cuenta de vendedor ha sido activada. Ya puedes publicar productos.',
        'success'
    );

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('process_activar_vendedor error: ' . $e->getMessage());
    redirect_with_message('activar_vendedor.php', 'Ocurrió un error al activar tu cuenta. Intenta más tarde.', 'error');
}
