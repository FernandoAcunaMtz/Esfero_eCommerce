<?php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
session_start();
require_role('admin', 'index.php');

// Función simple para sanitizar input
function sanitize_input($value) {
    if ($value === null || $value === '') {
        return '';
    }
    return trim(strip_tags((string)$value));
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'eliminar') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id > 0) {
        try {
            if (isset($pdo)) {
                $stmt = $pdo->prepare("DELETE FROM guias WHERE id = ?");
                $stmt->execute([$id]);
                
                header('Location: admin_guias.php?success=eliminada');
                exit;
            }
        } catch (Exception $e) {
            error_log("Error al eliminar guía: " . $e->getMessage());
            header('Location: admin_guias.php?error=Error al eliminar guía');
            exit;
        }
    } else {
        header('Location: admin_guias.php?error=ID inválido');
        exit;
    }
}

header('Location: admin_guias.php?error=Acción no válida');
exit;
?>

