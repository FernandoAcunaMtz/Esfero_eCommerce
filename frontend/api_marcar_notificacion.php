<?php
/**
 * api_marcar_notificacion.php
 * Endpoints JSON + redirect para gestión de notificaciones.
 *
 * GET  ?action=marcar&id=X           → marca una notif como leída (JSON)
 * GET  ?action=marcar_todas           → marca todas como leídas (JSON)
 * GET  ?action=borrar_todas           → elimina todas (redirect a notificaciones.php)
 * GET  ?action=count                  → devuelve conteo no leídas (JSON)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/auth_middleware.php';

$action = $_GET['action'] ?? '';

// ── Autenticación básica ──────────────────────────────────────────────────────
if (!is_logged_in() || !isset($pdo)) {
    if ($action === 'borrar_todas') {
        header('Location: login.php');
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$user       = get_session_user();
$usuario_id = (int)($user['id'] ?? 0);

// ── Acciones ──────────────────────────────────────────────────────────────────
switch ($action) {

    case 'marcar':
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            exit;
        }
        try {
            $pdo->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?")
                ->execute([$id, $usuario_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log('api_marcar_notif: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error interno']);
        }
        break;

    case 'marcar_todas':
        header('Content-Type: application/json; charset=utf-8');
        try {
            $pdo->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0")
                ->execute([$usuario_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log('api_marcar_notif todas: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error interno']);
        }
        break;

    case 'borrar_todas':
        try {
            $pdo->prepare("DELETE FROM notificaciones WHERE usuario_id = ?")
                ->execute([$usuario_id]);
        } catch (PDOException $e) {
            error_log('api_borrar_notif: ' . $e->getMessage());
        }
        header('Location: notificaciones.php');
        exit;

    case 'count':
        header('Content-Type: application/json; charset=utf-8');
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0");
            $stmt->execute([$usuario_id]);
            echo json_encode(['success' => true, 'count' => (int)$stmt->fetchColumn()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => true, 'count' => 0]);
        }
        break;

    case 'recientes':
        // Devuelve las últimas 5 para el dropdown del navbar
        header('Content-Type: application/json; charset=utf-8');
        try {
            $stmt = $pdo->prepare("
                SELECT id, tipo, titulo, mensaje, icono, url, leida, fecha_creacion
                FROM notificaciones
                WHERE usuario_id = ?
                ORDER BY fecha_creacion DESC
                LIMIT 5
            ");
            $stmt->execute([$usuario_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'items' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['success' => true, 'items' => []]);
        }
        break;

    default:
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
}
