<?php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/sanitize.php';
session_start();

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ayuda.php');
    exit;
}

// Obtener datos del formulario
$usuario_id = isset($_POST['usuario_id']) && $_POST['usuario_id'] ? (int)$_POST['usuario_id'] : null;
$nombre = sanitize_input($_POST['nombre'] ?? '');
$email = sanitize_input($_POST['email'] ?? '');
$telefono = sanitize_input($_POST['telefono'] ?? '');
$asunto = sanitize_input($_POST['asunto'] ?? '');
$mensaje = $_POST['mensaje'] ?? '';
$categoria = sanitize_input($_POST['categoria'] ?? 'general');

// Validaciones básicas
$errores = [];

if (empty($nombre)) {
    $errores[] = 'El nombre es requerido';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El email es inválido';
}

if (empty($asunto)) {
    $errores[] = 'El asunto es requerido';
}

if (empty($mensaje)) {
    $errores[] = 'El mensaje es requerido';
}

// Si hay errores, redirigir de vuelta
if (!empty($errores)) {
    $mensaje_error = implode(', ', $errores);
    header('Location: ayuda.php?error=' . urlencode($mensaje_error));
    exit;
}

// Validar categoría
$categorias_validas = ['general', 'comprar', 'vender', 'envios', 'pagos', 'cuenta', 'seguridad', 'reporte', 'reembolso'];
if (!in_array($categoria, $categorias_validas)) {
    $categoria = 'general';
}

// Si el usuario está logueado, verificar que el usuario_id coincida
if ($usuario_id) {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $usuario_id) {
        $usuario_id = null; // Ignorar usuario_id si no coincide con la sesión
    }
}

// Intentar crear la solicitud usando el stored procedure
try {
    if (!isset($pdo)) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Llamar al stored procedure
    $stmt = $pdo->prepare("CALL crear_solicitud_ayuda(?, ?, ?, ?, ?, ?, ?, @solicitud_id, @numero_ticket)");
    $stmt->execute([
        $usuario_id,
        $nombre,
        $email,
        !empty($telefono) ? $telefono : null,
        $asunto,
        $mensaje,
        $categoria
    ]);
    
    // Obtener los valores de salida
    $result = $pdo->query("SELECT @solicitud_id as solicitud_id, @numero_ticket as numero_ticket");
    $output = $result->fetch(PDO::FETCH_ASSOC);
    
    $solicitud_id = $output['solicitud_id'];
    $numero_ticket = $output['numero_ticket'];
    
    if ($solicitud_id && $numero_ticket) {
        // Éxito - redirigir con mensaje de éxito
        header('Location: ayuda.php?success=1&ticket=' . urlencode($numero_ticket));
        exit;
    } else {
        throw new Exception('Error al crear la solicitud');
    }
    
} catch (PDOException $e) {
    error_log("Error en process_ayuda.php (PDO): " . $e->getMessage());
    
    // Si el stored procedure no existe, intentar inserción directa
    try {
        // Generar número de ticket manualmente
        $numero_ticket = 'TK-' . date('Ymd') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Verificar que no exista
        $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM ayuda_solicitudes WHERE numero_ticket = ?");
        $check_stmt->execute([$numero_ticket]);
        $check = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        while ($check['count'] > 0) {
            $numero_ticket = 'TK-' . date('Ymd') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $check_stmt->execute([$numero_ticket]);
            $check = $check_stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Determinar prioridad
        $prioridad = 'normal';
        if (in_array($categoria, ['reporte', 'reembolso'])) {
            $prioridad = 'alta';
        } elseif ($categoria === 'seguridad') {
            $prioridad = 'urgente';
        }
        
        // Insertar directamente
        $stmt = $pdo->prepare("
            INSERT INTO ayuda_solicitudes (
                usuario_id, nombre, email, telefono, asunto, mensaje, 
                categoria, prioridad, numero_ticket, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
        ");
        
        $stmt->execute([
            $usuario_id,
            $nombre,
            strtolower($email),
            !empty($telefono) ? $telefono : null,
            $asunto,
            $mensaje,
            $categoria,
            $prioridad,
            $numero_ticket
        ]);
        
        header('Location: ayuda.php?success=1&ticket=' . urlencode($numero_ticket));
        exit;
        
    } catch (Exception $e2) {
        error_log("Error en process_ayuda.php (fallback): " . $e2->getMessage());
        header('Location: ayuda.php?error=' . urlencode('Error al procesar tu solicitud. Por favor, intenta nuevamente.'));
        exit;
    }
    
} catch (Exception $e) {
    error_log("Error en process_ayuda.php: " . $e->getMessage());
    header('Location: ayuda.php?error=' . urlencode('Error al procesar tu solicitud. Por favor, intenta nuevamente.'));
    exit;
}
?>

