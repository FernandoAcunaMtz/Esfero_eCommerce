<?php
session_start();
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/csrf.php';

if (!is_logged_in() || !isset($pdo)) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mensajes.php');
    exit;
}

csrf_verify();

$user = get_session_user();
$remitente_id = $user['id'] ?? null;

if (!$remitente_id) {
    $_SESSION['error_message'] = 'Error al identificar usuario.';
    header('Location: mensajes.php');
    exit;
}

$conversacion_id = $_POST['conversacion_id'] ?? '';
$destinatario_id = (int)($_POST['destinatario_id'] ?? 0);
$mensaje = trim($_POST['mensaje'] ?? '');

// Validaciones
if (empty($conversacion_id) || !$destinatario_id || empty($mensaje)) {
    $_SESSION['error_message'] = 'Todos los campos son requeridos.';
    header('Location: mensajes.php?conversacion=' . urlencode($conversacion_id));
    exit;
}

if ($destinatario_id === (int)$remitente_id) {
    $_SESSION['error_message'] = 'No puedes enviarte mensajes a ti mismo.';
    header('Location: mensajes.php?conversacion=' . urlencode($conversacion_id));
    exit;
}

if (mb_strlen($mensaje, 'UTF-8') > 2000) {
    $_SESSION['error_message'] = 'El mensaje no puede superar los 2,000 caracteres.';
    header('Location: mensajes.php?conversacion=' . urlencode($conversacion_id));
    exit;
}

// Validar que el remitente pertenece a esta conversación
$stmt = $pdo->prepare("
    SELECT * FROM mensajes 
    WHERE conversacion_id = ? 
    AND (remitente_id = ? OR destinatario_id = ?)
    LIMIT 1
");
$stmt->execute([$conversacion_id, $remitente_id, $remitente_id]);
if (!$stmt->fetch()) {
    $_SESSION['error_message'] = 'No tienes permiso para enviar mensajes en esta conversación.';
    header('Location: mensajes.php');
    exit;
}

try {
    // Insertar mensaje
    $stmt = $pdo->prepare("
        INSERT INTO mensajes (conversacion_id, remitente_id, destinatario_id, mensaje)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$conversacion_id, $remitente_id, $destinatario_id, $mensaje]);

    // ── Notificar al destinatario ─────────────────────────────────────────
    if (function_exists('crear_notificacion')) {
        // Obtener nombre del remitente
        $stmt_rem = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = ?");
        $stmt_rem->execute([$remitente_id]);
        $nom_rem = $stmt_rem->fetchColumn() ?: 'Un usuario';
        $preview = mb_strlen($mensaje, 'UTF-8') > 60
            ? mb_substr($mensaje, 0, 60, 'UTF-8') . '…'
            : $mensaje;
        crear_notificacion(
            $pdo, $destinatario_id, 'mensaje',
            'Nuevo mensaje de ' . $nom_rem,
            $preview,
            'fas fa-envelope',
            'mensajes.php?conversacion=' . urlencode($conversacion_id)
        );
    }

    header('Location: mensajes.php?conversacion=' . urlencode($conversacion_id));
    exit;
    
} catch (PDOException $e) {
    error_log("Error al enviar mensaje: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error al enviar el mensaje. Por favor, intenta de nuevo.';
    header('Location: mensajes.php?conversacion=' . urlencode($conversacion_id));
    exit;
}

