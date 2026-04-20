<?php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
session_start();
require_role('admin', 'index.php');

$admin_id = (int)$_SESSION['user_id'];
$solicitud_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($solicitud_id <= 0) {
    header('Location: admin_ayuda.php?error=ID inválido');
    exit;
}

// Cargar solicitud
$solicitud = null;
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("
            SELECT s.*, u.nombre as usuario_nombre, u.email as usuario_email, u.telefono as usuario_telefono
            FROM ayuda_solicitudes s
            LEFT JOIN usuarios u ON s.usuario_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$solicitud_id]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$solicitud) {
            header('Location: admin_ayuda.php?error=Solicitud no encontrada');
            exit;
        }
    }
} catch (Exception $e) {
    error_log("Error al cargar solicitud: " . $e->getMessage());
    header('Location: admin_ayuda.php?error=Error al cargar solicitud');
    exit;
}

// Cargar respuestas
$respuestas = [];
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("
            SELECT r.*, u.nombre as admin_nombre, u.email as admin_email
            FROM ayuda_respuestas r
            INNER JOIN usuarios u ON r.admin_id = u.id
            WHERE r.solicitud_id = ?
            ORDER BY r.fecha_creacion ASC
        ");
        $stmt->execute([$solicitud_id]);
        $respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error al cargar respuestas: " . $e->getMessage());
}

// Procesar respuesta
$mensaje_error = '';
$mensaje_exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'responder') {
        $mensaje = trim($_POST['mensaje'] ?? '');
        $es_interno = isset($_POST['es_interno']) ? 1 : 0;
        $nuevo_estado = $_POST['nuevo_estado'] ?? null;
        
        if (empty($mensaje)) {
            $mensaje_error = 'El mensaje es requerido';
        } else {
            try {
                // Intentar usar el stored procedure
                $stmt = $pdo->prepare("CALL responder_solicitud_ayuda(?, ?, ?, ?, ?, @respuesta_id)");
                $stmt->execute([
                    $solicitud_id,
                    $admin_id,
                    $mensaje,
                    $es_interno,
                    $nuevo_estado
                ]);
                
                $result = $pdo->query("SELECT @respuesta_id as respuesta_id");
                $output = $result->fetch(PDO::FETCH_ASSOC);
                
                if ($output['respuesta_id']) {
                    $mensaje_exito = 'Respuesta enviada exitosamente';
                    // Recargar respuestas
                    $stmt = $pdo->prepare("
                        SELECT r.*, u.nombre as admin_nombre, u.email as admin_email
                        FROM ayuda_respuestas r
                        INNER JOIN usuarios u ON r.admin_id = u.id
                        WHERE r.solicitud_id = ?
                        ORDER BY r.fecha_creacion ASC
                    ");
                    $stmt->execute([$solicitud_id]);
                    $respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Recargar solicitud para ver cambios de estado
                    $stmt = $pdo->prepare("
                        SELECT s.*, u.nombre as usuario_nombre, u.email as usuario_email, u.telefono as usuario_telefono
                        FROM ayuda_solicitudes s
                        LEFT JOIN usuarios u ON s.usuario_id = u.id
                        WHERE s.id = ?
                    ");
                    $stmt->execute([$solicitud_id]);
                    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                
            } catch (PDOException $e) {
                error_log("Error al responder (SP): " . $e->getMessage());
                
                // Fallback: inserción directa
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO ayuda_respuestas (solicitud_id, admin_id, mensaje, es_interno)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$solicitud_id, $admin_id, $mensaje, $es_interno]);
                    
                    // Actualizar estado si es respuesta pública
                    if (!$es_interno) {
                        $estado_update = $nuevo_estado ?: 'respondida';
                        $stmt = $pdo->prepare("
                            UPDATE ayuda_solicitudes
                            SET estado = ?, fecha_respuesta = NOW(), fecha_actualizacion = NOW()
                            WHERE id = ? AND estado NOT IN ('cerrada', 'resuelta')
                        ");
                        $stmt->execute([$estado_update, $solicitud_id]);
                    }
                    
                    $mensaje_exito = 'Respuesta enviada exitosamente';
                    
                    // Recargar datos
                    $stmt = $pdo->prepare("
                        SELECT r.*, u.nombre as admin_nombre, u.email as admin_email
                        FROM ayuda_respuestas r
                        INNER JOIN usuarios u ON r.admin_id = u.id
                        WHERE r.solicitud_id = ?
                        ORDER BY r.fecha_creacion ASC
                    ");
                    $stmt->execute([$solicitud_id]);
                    $respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("
                        SELECT s.*, u.nombre as usuario_nombre, u.email as usuario_email, u.telefono as usuario_telefono
                        FROM ayuda_solicitudes s
                        LEFT JOIN usuarios u ON s.usuario_id = u.id
                        WHERE s.id = ?
                    ");
                    $stmt->execute([$solicitud_id]);
                    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                } catch (Exception $e2) {
                    error_log("Error al responder (fallback): " . $e2->getMessage());
                    $mensaje_error = 'Error al enviar la respuesta';
                }
            }
        }
    } elseif ($accion === 'cambiar_estado') {
        $nuevo_estado = $_POST['nuevo_estado'] ?? '';
        
        if (!empty($nuevo_estado)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE ayuda_solicitudes
                    SET estado = ?, fecha_actualizacion = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$nuevo_estado, $solicitud_id]);
                
                $mensaje_exito = 'Estado actualizado exitosamente';
                
                // Recargar solicitud
                $stmt = $pdo->prepare("
                    SELECT s.*, u.nombre as usuario_nombre, u.email as usuario_email, u.telefono as usuario_telefono
                    FROM ayuda_solicitudes s
                    LEFT JOIN usuarios u ON s.usuario_id = u.id
                    WHERE s.id = ?
                ");
                $stmt->execute([$solicitud_id]);
                $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                error_log("Error al cambiar estado: " . $e->getMessage());
                $mensaje_error = 'Error al cambiar el estado';
            }
        }
    }
}

// Nombres
$estado_nombres = [
    'pendiente' => 'Pendiente',
    'en_revision' => 'En Revisión',
    'respondida' => 'Respondida',
    'cerrada' => 'Cerrada',
    'resuelta' => 'Resuelta'
];

$categoria_nombres = [
    'general' => 'General',
    'comprar' => 'Comprar',
    'vender' => 'Vender',
    'envios' => 'Envíos',
    'pagos' => 'Pagos',
    'cuenta' => 'Cuenta',
    'seguridad' => 'Seguridad',
    'reporte' => 'Reporte',
    'reembolso' => 'Reembolso'
];

$prioridad_nombres = [
    'baja' => 'Baja',
    'normal' => 'Normal',
    'alta' => 'Alta',
    'urgente' => 'Urgente'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Solicitud <?php echo htmlspecialchars($solicitud['numero_ticket']); ?> - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .admin-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
        }
        .solicitud-header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .solicitud-info {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .solicitud-mensaje {
            background: #f8f9fa;
            border-left: 4px solid #0C9268;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .respuestas {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .respuesta-item {
            background: #f8f9fa;
            border-left: 4px solid #0D87A8;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        .respuesta-item.interna {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        .respuesta-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .respuesta-mensaje {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .form-respuesta {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #0D87A8;
            font-weight: 600;
        }
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            min-height: 150px;
            font-family: inherit;
            resize: vertical;
        }
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-submit {
            background: #0C9268;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-right: 1rem;
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .mensaje-alerta {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .mensaje-alerta.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .mensaje-alerta.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <div class="admin-container">
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem;">
            <?php include 'components/sidebar_admin.php'; ?>
            
            <div>
                <div style="margin-bottom: 2rem;">
                    <a href="admin_ayuda.php" class="btn-cancel">
                        <i class="fas fa-arrow-left"></i> Volver a Solicitudes
                    </a>
                </div>
                
                <?php if ($mensaje_exito): ?>
                    <div class="mensaje-alerta success">
                        <?php echo htmlspecialchars($mensaje_exito); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($mensaje_error): ?>
                    <div class="mensaje-alerta error">
                        <?php echo htmlspecialchars($mensaje_error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Header de Solicitud -->
                <div class="solicitud-header">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div>
                            <h1 style="color: #0D87A8; margin: 0 0 0.5rem 0;">
                                Ticket: <?php echo htmlspecialchars($solicitud['numero_ticket']); ?>
                            </h1>
                            <h2 style="color: #333; margin: 0; font-size: 1.5rem;">
                                <?php echo htmlspecialchars($solicitud['asunto']); ?>
                            </h2>
                        </div>
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="accion" value="cambiar_estado">
                            <select name="nuevo_estado" onchange="this.form.submit()" style="padding: 0.5rem; border-radius: 6px;">
                                <?php foreach ($estado_nombres as $estado_key => $estado_nombre): ?>
                                    <option value="<?php echo $estado_key; ?>" <?php echo $solicitud['estado'] === $estado_key ? 'selected' : ''; ?>>
                                        <?php echo $estado_nombre; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <span class="badge" style="background: #cfe2ff; color: #084298;">
                            <?php echo $categoria_nombres[$solicitud['categoria']] ?? ucfirst($solicitud['categoria']); ?>
                        </span>
                        <span class="badge" style="background: <?php 
                            echo $solicitud['prioridad'] === 'urgente' ? '#dc3545' : 
                                ($solicitud['prioridad'] === 'alta' ? '#fd7e14' : 
                                ($solicitud['prioridad'] === 'normal' ? '#0d6efd' : '#6c757d')); 
                        ?>; color: white;">
                            <?php echo $prioridad_nombres[$solicitud['prioridad']] ?? ucfirst($solicitud['prioridad']); ?>
                        </span>
                        <span class="badge" style="background: #d1e7dd; color: #0f5132;">
                            <?php echo $estado_nombres[$solicitud['estado']] ?? ucfirst($solicitud['estado']); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Información del Solicitante -->
                <div class="solicitud-info">
                    <h3 style="color: #0D87A8; margin-bottom: 1rem;">Información del Solicitante</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div>
                            <strong>Nombre:</strong><br>
                            <?php echo htmlspecialchars($solicitud['usuario_nombre'] ?: $solicitud['nombre']); ?>
                        </div>
                        <div>
                            <strong>Email:</strong><br>
                            <a href="mailto:<?php echo htmlspecialchars($solicitud['usuario_email'] ?: $solicitud['email']); ?>">
                                <?php echo htmlspecialchars($solicitud['usuario_email'] ?: $solicitud['email']); ?>
                            </a>
                        </div>
                        <?php if ($solicitud['telefono'] || $solicitud['usuario_telefono']): ?>
                        <div>
                            <strong>Teléfono:</strong><br>
                            <?php echo htmlspecialchars($solicitud['usuario_telefono'] ?: $solicitud['telefono']); ?>
                        </div>
                        <?php endif; ?>
                        <div>
                            <strong>Fecha:</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_creacion'])); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Mensaje Original -->
                <div class="solicitud-info">
                    <h3 style="color: #0D87A8; margin-bottom: 1rem;">Mensaje</h3>
                    <div class="solicitud-mensaje">
                        <?php echo nl2br(htmlspecialchars($solicitud['mensaje'])); ?>
                    </div>
                </div>
                
                <!-- Respuestas -->
                <?php if (!empty($respuestas)): ?>
                <div class="respuestas">
                    <h3 style="color: #0D87A8; margin-bottom: 1rem;">
                        Respuestas (<?php echo count($respuestas); ?>)
                    </h3>
                    
                    <?php foreach ($respuestas as $respuesta): ?>
                        <div class="respuesta-item <?php echo $respuesta['es_interno'] ? 'interna' : ''; ?>">
                            <div class="respuesta-header">
                                <div>
                                    <strong><?php echo htmlspecialchars($respuesta['admin_nombre']); ?></strong>
                                    <?php if ($respuesta['es_interno']): ?>
                                        <span class="badge" style="background: #ffc107; color: #000; margin-left: 0.5rem;">Nota Interna</span>
                                    <?php endif; ?>
                                </div>
                                <small style="color: #666;">
                                    <?php echo date('d/m/Y H:i', strtotime($respuesta['fecha_creacion'])); ?>
                                </small>
                            </div>
                            <div class="respuesta-mensaje">
                                <?php echo nl2br(htmlspecialchars($respuesta['mensaje'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Formulario de Respuesta -->
                <div class="form-respuesta">
                    <h3 style="color: #0D87A8; margin-bottom: 1rem;">Responder Solicitud</h3>
                    
                    <form method="POST">
                        <input type="hidden" name="accion" value="responder">
                        
                        <div class="form-group">
                            <label for="mensaje">Mensaje *</label>
                            <textarea id="mensaje" name="mensaje" required placeholder="Escribe tu respuesta aquí..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="nuevo_estado">Cambiar Estado (opcional)</label>
                            <select id="nuevo_estado" name="nuevo_estado">
                                <option value="">Mantener estado actual</option>
                                <?php foreach ($estado_nombres as $estado_key => $estado_nombre): ?>
                                    <?php if ($estado_key !== $solicitud['estado']): ?>
                                        <option value="<?php echo $estado_key; ?>">
                                            <?php echo $estado_nombre; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="es_interno" name="es_interno" value="1">
                                <label for="es_interno" style="margin: 0;">Nota interna (solo visible para admins)</label>
                            </div>
                        </div>
                        
                        <div>
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-paper-plane"></i> Enviar Respuesta
                            </button>
                            <a href="admin_ayuda.php" class="btn-cancel">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

