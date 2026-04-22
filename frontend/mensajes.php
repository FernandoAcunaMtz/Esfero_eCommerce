<?php
session_start();
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/csrf.php';

// Bloquear mensajes para administradores
if (is_admin()) {
    $_SESSION['error_message'] = 'Los administradores no tienen mensajes.';
    header('Location: admin_dashboard.php');
    exit;
}

require_login();

$user = get_session_user();
$usuario_id = $user['id'] ?? null;

if (!$usuario_id || !isset($pdo)) {
    $_SESSION['error_message'] = 'Error al cargar datos del usuario.';
    header('Location: index.php');
    exit;
}

// Obtener conversación seleccionada
$conversacion_id = $_GET['conversacion'] ?? null;
$otro_usuario_id = $_GET['usuario'] ?? null;

// Obtener todas las conversaciones del usuario
$conversaciones = [];

try {
    // Obtener conversaciones donde el usuario es remitente o destinatario
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            m.conversacion_id,
            CASE 
                WHEN m.remitente_id = ? THEN m.destinatario_id
                ELSE m.remitente_id
            END as otro_usuario_id,
            CASE 
                WHEN m.remitente_id = ? THEN u_dest.nombre
                ELSE u_rem.nombre
            END as otro_usuario_nombre,
            CASE 
                WHEN m.remitente_id = ? THEN u_dest.email
                ELSE u_rem.email
            END as otro_usuario_email,
            (SELECT mensaje FROM mensajes 
             WHERE conversacion_id = m.conversacion_id 
             ORDER BY fecha_envio DESC LIMIT 1) as ultimo_mensaje,
            (SELECT fecha_envio FROM mensajes 
             WHERE conversacion_id = m.conversacion_id 
             ORDER BY fecha_envio DESC LIMIT 1) as ultima_fecha,
            (SELECT COUNT(*) FROM mensajes 
             WHERE conversacion_id = m.conversacion_id 
             AND destinatario_id = ? 
             AND leido = 0) as no_leidos,
            (SELECT producto_id FROM mensajes 
             WHERE conversacion_id = m.conversacion_id 
             AND producto_id IS NOT NULL 
             LIMIT 1) as producto_id
        FROM mensajes m
        LEFT JOIN usuarios u_rem ON m.remitente_id = u_rem.id
        LEFT JOIN usuarios u_dest ON m.destinatario_id = u_dest.id
        WHERE m.remitente_id = ? OR m.destinatario_id = ?
        GROUP BY m.conversacion_id
        ORDER BY ultima_fecha DESC
    ");
    $stmt->execute([$usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id]);
    $conversaciones = $stmt->fetchAll();
    
    // Obtener información de productos para cada conversación
    foreach ($conversaciones as &$conv) {
        if ($conv['producto_id']) {
            $stmt_prod = $pdo->prepare("
                SELECT p.titulo, p.id,
                       (SELECT url_imagen FROM imagenes_productos 
                        WHERE producto_id = p.id AND es_principal = 1 
                        LIMIT 1) as imagen_principal
                FROM productos p
                WHERE p.id = ?
            ");
            $stmt_prod->execute([$conv['producto_id']]);
            $conv['producto'] = $stmt_prod->fetch();
        }
    }
    unset($conv);
    
} catch (PDOException $e) {
    error_log("Error al obtener conversaciones: " . $e->getMessage());
    $conversaciones = [];
}

// Obtener mensajes de la conversación seleccionada
$mensajes = [];
$conversacion_actual = null;

if ($conversacion_id) {
    try {
        // Validar que el usuario pertenece a esta conversación
        $stmt = $pdo->prepare("
            SELECT * FROM mensajes 
            WHERE conversacion_id = ? 
            AND (remitente_id = ? OR destinatario_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$conversacion_id, $usuario_id, $usuario_id]);
        if ($stmt->fetch()) {
            // Obtener todos los mensajes de la conversación
            $stmt = $pdo->prepare("
                SELECT m.*,
                       u_rem.nombre as remitente_nombre,
                       u_dest.nombre as destinatario_nombre,
                       p.titulo as producto_titulo
                FROM mensajes m
                LEFT JOIN usuarios u_rem ON m.remitente_id = u_rem.id
                LEFT JOIN usuarios u_dest ON m.destinatario_id = u_dest.id
                LEFT JOIN productos p ON m.producto_id = p.id
                WHERE m.conversacion_id = ?
                ORDER BY m.fecha_envio ASC
            ");
            $stmt->execute([$conversacion_id]);
            $mensajes = $stmt->fetchAll();
            
            // Marcar mensajes como leídos
            $stmt = $pdo->prepare("
                UPDATE mensajes 
                SET leido = 1, fecha_leido = NOW()
                WHERE conversacion_id = ? 
                AND destinatario_id = ? 
                AND leido = 0
            ");
            $stmt->execute([$conversacion_id, $usuario_id]);
            
            // Obtener información de la conversación
            $stmt = $pdo->prepare("
                SELECT DISTINCT 
                    CASE 
                        WHEN m.remitente_id = ? THEN m.destinatario_id
                        ELSE m.remitente_id
                    END as otro_usuario_id,
                    CASE 
                        WHEN m.remitente_id = ? THEN u_dest.nombre
                        ELSE u_rem.nombre
                    END as otro_usuario_nombre
                FROM mensajes m
                LEFT JOIN usuarios u_rem ON m.remitente_id = u_rem.id
                LEFT JOIN usuarios u_dest ON m.destinatario_id = u_dest.id
                WHERE m.conversacion_id = ?
                LIMIT 1
            ");
            $stmt->execute([$usuario_id, $usuario_id, $conversacion_id]);
            $conversacion_actual = $stmt->fetch();
        }
    } catch (PDOException $e) {
        error_log("Error al obtener mensajes: " . $e->getMessage());
    }
}

// Contar mensajes no leídos totales
$total_no_leidos = 0;
foreach ($conversaciones as $conv) {
    $total_no_leidos += (int)$conv['no_leidos'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png">
    <script>if('serviceWorker'in navigator)navigator.serviceWorker.register('/sw.js');</script>
    <title>Mensajes - Esfero</title>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .mensajes-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
        }
        
        .mensajes-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            height: calc(100vh - 250px);
            min-height: 600px;
        }
        
        .conversaciones-list {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .conversaciones-header {
            padding: 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            background: linear-gradient(135deg, #0D87A8, #0C9268);
            color: white;
        }
        
        .conversaciones-header h2 {
            margin: 0;
            font-size: 1.3rem;
        }
        
        .conversaciones-body {
            flex: 1;
            overflow-y: auto;
        }
        
        .conversacion-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .conversacion-item:hover {
            background: #f8f9fa;
        }
        
        .conversacion-item.active {
            background: #e8f4f8;
        }
        
        .conversacion-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0D87A8, #0C9268);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .conversacion-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversacion-nombre {
            font-weight: bold;
            color: #0D87A8;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversacion-mensaje {
            font-size: 0.85rem;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversacion-meta {
            text-align: right;
            flex-shrink: 0;
        }
        
        .conversacion-fecha {
            font-size: 0.75rem;
            color: #999;
            margin-bottom: 0.25rem;
        }
        
        .conversacion-badge {
            background: #0C9268;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .chat-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-header {
            padding: 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            background: #f8f9fa;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .mensaje-item {
            display: flex;
            gap: 0.75rem;
            max-width: 70%;
        }
        
        .mensaje-item.propio {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .mensaje-bubble {
            padding: 0.75rem 1rem;
            border-radius: 15px;
            word-wrap: break-word;
        }
        
        .mensaje-item.propio .mensaje-bubble {
            background: linear-gradient(135deg, #0D87A8, #0C9268);
            color: white;
        }
        
        .mensaje-item.otro .mensaje-bubble {
            background: #f0f0f0;
            color: #333;
        }
        
        .mensaje-fecha {
            font-size: 0.7rem;
            color: #999;
            margin-top: 0.25rem;
        }
        
        .chat-input {
            padding: 1.5rem;
            border-top: 1px solid #f0f0f0;
            background: #f8f9fa;
        }
        
        .chat-input-form {
            display: flex;
            gap: 1rem;
        }
        
        .chat-input-form textarea {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 10px;
            resize: none;
            font-family: inherit;
        }
        
        .chat-input-form button {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #0D87A8, #0C9268);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .sin-conversacion {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #666;
            padding: 2rem;
        }
        
        @media (max-width: 968px) {
            #mensajesOuterLayout {
                grid-template-columns: 1fr !important;
            }

            .mensajes-layout {
                grid-template-columns: 1fr;
                height: auto;
            }

            .conversaciones-list {
                max-height: 300px;
            }

            .chat-input-form button span {
                display: none;
            }
        }

        @media (max-width: 640px) {
            .mensajes-container {
                padding: 1rem !important;
                margin-top: 80px !important;
            }

            .mensajes-layout {
                gap: 1rem;
            }

            .conversaciones-list {
                max-height: 250px;
            }

            .mensaje-item {
                max-width: 85% !important;
            }

            .chat-input-form textarea {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <div class="mensajes-container">
        <h1 style="color: #0D87A8; margin-bottom: 2rem; font-size: clamp(1.5rem, 4vw, 2.5rem);">
            <i class="fas fa-comments"></i> Mensajes
            <?php if ($total_no_leidos > 0): ?>
                <span style="background: #dc3545; color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.9rem; margin-left: 1rem;">
                    <?php echo $total_no_leidos; ?> nuevo<?php echo $total_no_leidos > 1 ? 's' : ''; ?>
                </span>
            <?php endif; ?>
        </h1>
        
        <div id="mensajesOuterLayout" style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem; margin-bottom: 2rem;">
            <?php include 'components/sidebar_vendedor.php'; ?>
            <div>
                <div class="mensajes-layout">
            <!-- Lista de Conversaciones -->
            <div class="conversaciones-list">
                <div class="conversaciones-header">
                    <h2><i class="fas fa-inbox"></i> Conversaciones</h2>
                </div>
                <div class="conversaciones-body">
                    <?php if (empty($conversaciones)): ?>
                        <div style="padding: 2rem; text-align: center; color: #666;">
                            <i class="fas fa-comments" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                            <p>No tienes conversaciones aún</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversaciones as $conv): 
                            $es_activa = $conversacion_id === $conv['conversacion_id'];
                            $iniciales = strtoupper(substr($conv['otro_usuario_nombre'], 0, 2));
                            $ultimo_mensaje_texto = substr($conv['ultimo_mensaje'], 0, 50);
                            if (strlen($conv['ultimo_mensaje']) > 50) {
                                $ultimo_mensaje_texto .= '...';
                            }
                            $fecha_ultima = $conv['ultima_fecha'] ? date('d/m/Y', strtotime($conv['ultima_fecha'])) : '';
                        ?>
                        <a href="?conversacion=<?php echo htmlspecialchars($conv['conversacion_id']); ?>" 
                           class="conversacion-item <?php echo $es_activa ? 'active' : ''; ?>" 
                           style="text-decoration: none; color: inherit;">
                            <?php if (!empty($conv['producto']['imagen_principal'])): ?>
                            <div style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; flex-shrink: 0;">
                                <img src="<?php echo htmlspecialchars($conv['producto']['imagen_principal']); ?>" 
                                     alt="<?php echo htmlspecialchars($conv['producto']['titulo'] ?? ''); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <?php else: ?>
                            <div class="conversacion-avatar">
                                <?php echo htmlspecialchars($iniciales); ?>
                            </div>
                            <?php endif; ?>
                            <div class="conversacion-info">
                                <div class="conversacion-nombre">
                                    <?php echo htmlspecialchars($conv['otro_usuario_nombre']); ?>
                                </div>
                                <div class="conversacion-mensaje">
                                    <?php echo htmlspecialchars($ultimo_mensaje_texto); ?>
                                </div>
                            </div>
                            <div class="conversacion-meta">
                                <div class="conversacion-fecha"><?php echo $fecha_ultima; ?></div>
                                <?php if ($conv['no_leidos'] > 0): ?>
                                <span class="conversacion-badge"><?php echo $conv['no_leidos']; ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat -->
            <div class="chat-container">
                <?php if ($conversacion_id && $conversacion_actual): ?>
                    <div class="chat-header">
                        <h3 style="margin: 0; color: #0D87A8;">
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($conversacion_actual['otro_usuario_nombre']); ?>
                        </h3>
                    </div>
                    <div class="chat-messages" id="chatMessages">
                        <?php foreach ($mensajes as $mensaje): 
                            $es_propio = $mensaje['remitente_id'] == $usuario_id;
                            $fecha_mensaje = date('d/m/Y H:i', strtotime($mensaje['fecha_envio']));
                        ?>
                        <div class="mensaje-item <?php echo $es_propio ? 'propio' : 'otro'; ?>">
                            <div>
                                <div class="mensaje-bubble">
                                    <?php echo nl2br(htmlspecialchars($mensaje['mensaje'])); ?>
                                </div>
                                <div class="mensaje-fecha">
                                    <?php echo $fecha_mensaje; ?>
                                    <?php if ($es_propio && $mensaje['leido']): ?>
                                        <i class="fas fa-check-double" style="color: #0C9268; margin-left: 0.5rem;"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="chat-input">
                        <form class="chat-input-form" method="POST" action="process_enviar_mensaje.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="conversacion_id" value="<?php echo htmlspecialchars($conversacion_id); ?>">
                            <input type="hidden" name="destinatario_id" value="<?php echo (int)$conversacion_actual['otro_usuario_id']; ?>">
                            <textarea name="mensaje" placeholder="Escribe un mensaje..." rows="2" required></textarea>
                            <button type="submit">
                                <i class="fas fa-paper-plane"></i> Enviar
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="sin-conversacion">
                        <i class="fas fa-comments" style="font-size: 5rem; color: #ddd; margin-bottom: 1rem;"></i>
                        <h3 style="color: #666; margin-bottom: 0.5rem;">Selecciona una conversación</h3>
                        <p style="color: #999;">Elige una conversación de la lista para ver los mensajes</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
            </div>
        </div>
    </div>
    
    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        // Auto-scroll al final del chat
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    </script>
</body>
</html>

