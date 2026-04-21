<?php
// Manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar dependencias (sin sanitize.php)
try {
    require_once __DIR__ . '/includes/api_helper.php';
} catch (Exception $e) {
    error_log("Error al cargar dependencias en orden.php: " . $e->getMessage());
    die("Error al cargar el sistema");
}

// Función simple de sanitización
function simple_sanitize_int($value, $min = null) {
    $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    $value = filter_var($value, FILTER_VALIDATE_INT);
    if ($value === false) return false;
    if ($min !== null && $value < $min) return false;
    return $value;
}

// Bloquear órdenes/compras para administradores
if (is_admin()) {
    $_SESSION['error_message'] = 'Los administradores no pueden realizar compras.';
    header('Location: admin_dashboard.php');
    exit;
}

require_login();

// Obtener y validar ID de orden desde la URL
$orden_id = simple_sanitize_int($_GET['id'] ?? null, 1);

if ($orden_id === false) {
    $_SESSION['error_message'] = 'Orden no especificada.';
    header('Location: perfil.php?tab=compras');
    exit;
}

// Obtener detalles reales de la orden desde la base de datos directamente
$orden_detalle = null;
$orden_error = '';

require_once __DIR__ . '/includes/db_connection.php';
$user = get_session_user();
$usuario_id = $user['id'] ?? null;

if ($usuario_id && isset($pdo)) {
    try {
        // Obtener la orden con sus items
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   GROUP_CONCAT(CONCAT(p.titulo, '|', oi.cantidad, '|', oi.precio_unitario, '|', oi.subtotal, '|', COALESCE(ip.url_imagen, '')) SEPARATOR '||') as items_data
            FROM ordenes o
            LEFT JOIN orden_items oi ON o.id = oi.orden_id
            LEFT JOIN productos p ON oi.producto_id = p.id
            LEFT JOIN imagenes_productos ip ON p.id = ip.producto_id AND ip.es_principal = 1
            WHERE o.id = ? AND o.comprador_id = ?
            GROUP BY o.id
        ");
        $stmt->execute([$orden_id, $usuario_id]);
        $orden_detalle = $stmt->fetch();
        
        if (!$orden_detalle) {
            $orden_error = 'Orden no encontrada o no tienes permisos para verla.';
        } else {
            // Procesar items
            $items = [];
            if (!empty($orden_detalle['items_data'])) {
                $items_raw = explode('||', $orden_detalle['items_data']);
                foreach ($items_raw as $item_raw) {
                    $parts = explode('|', $item_raw);
                    if (count($parts) >= 4) {
                        $items[] = [
                            'titulo' => $parts[0],
                            'cantidad' => (int)$parts[1],
                            'precio_unitario' => (float)$parts[2],
                            'subtotal' => (float)$parts[3],
                            'imagen' => $parts[4] ?? ''
                        ];
                    }
                }
            }
            $orden_detalle['items'] = $items;
        }
    } catch (PDOException $e) {
        error_log("Error al obtener orden: " . $e->getMessage());
        $orden_error = 'Error al cargar los detalles de la orden.';
    }
} else {
    $orden_error = 'No se pudo verificar tu sesión.';
}

// Datos de la orden (con valores por defecto por si algo falta)
$numero_orden      = $orden_detalle['numero_orden']      ?? ('ORD-' . $orden_id);
$total_orden       = isset($orden_detalle['total']) ? (float)$orden_detalle['total'] : 0.0;
$metodo_pago       = $orden_detalle['metodo_pago']       ?? 'paypal';
$estado_pago       = $orden_detalle['estado_pago']       ?? ($orden_detalle['estado'] ?? 'pendiente');
$fecha_creacion    = $orden_detalle['fecha_creacion']    ?? null;
$direccion_envio   = $orden_detalle['direccion_envio']   ?? '';
$ciudad_envio      = $orden_detalle['ciudad_envio']      ?? '';
$estado_envio      = $orden_detalle['estado_envio']      ?? '';
$codigo_postal_envio = $orden_detalle['codigo_postal_envio'] ?? '';
$nombre_destinatario = $orden_detalle['nombre_destinatario'] ?? '';

$session_user = get_session_user();
$email_usuario = $session_user['email'] ?? '';
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
    <title>Orden Confirmada - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .orden-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
            text-align: center;
        }
        
        .orden-card {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .icono-exito {
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, #0C9268, #0D87A8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 3rem;
            animation: scaleIn 0.5s ease;
        }
        
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        
        .orden-numero {
            background: #f0f8ff;
            padding: 1rem;
            border-radius: 10px;
            margin: 2rem 0;
        }
        
        .orden-detalles {
            text-align: left;
            margin: 2rem 0;
        }
        
        .detalle-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .acciones-orden {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn-accion {
            padding: 1rem;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primario {
            background: linear-gradient(45deg, #0D87A8, #0C9268);
            color: white;
            border: none;
        }
        
        .btn-secundario {
            background: white;
            color: #0D87A8;
            border: 2px solid #0D87A8;
        }
        
        @media (max-width: 768px) {
            .acciones-orden {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="orden-container">
        <div class="orden-card">
            <?php if ($orden_error): ?>
                <div style="padding: 1rem; border-radius: 10px; background: #fdecea; color: #b71c1c; margin-bottom: 1.5rem;">
                    <?php echo htmlspecialchars($orden_error); ?>
                </div>
            <?php else: ?>
                <div class="icono-exito">
                    <i class="fas fa-check"></i>
                </div>
                
                <h1 style="color: #0D87A8; margin-bottom: 1rem; font-size: 2.5rem;">¡Orden Confirmada!</h1>
                <p style="color: #666; font-size: 1.1rem;">Gracias por tu compra. Tu pedido ha sido procesado exitosamente.</p>
            
            <div class="orden-numero">
                <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Número de Orden</div>
                <div style="color: #0D87A8; font-size: 1.5rem; font-weight: bold;">
                    <?php echo htmlspecialchars('#' . $numero_orden); ?>
                </div>
            </div>
            
            <div class="orden-detalles">
                <h3 style="color: #0D87A8; margin-bottom: 1rem;">Detalles de la Orden</h3>
                
                <div class="detalle-item">
                    <span style="color: #666;">Fecha de Compra:</span>
                    <strong>
                        <?php 
                            echo $fecha_creacion 
                                ? date('d/m/Y H:i', strtotime($fecha_creacion)) 
                                : date('d/m/Y H:i');
                        ?>
                    </strong>
                </div>
                
                <div class="detalle-item">
                    <span style="color: #666;">Total Pagado:</span>
                    <strong style="color: #0C9268; font-size: 1.3rem;">
                        $<?php echo number_format($total_orden, 2); ?>
                    </strong>
                </div>
                
                <div class="detalle-item">
                    <span style="color: #666;">Método de Pago:</span>
                    <strong>
                        <?php echo htmlspecialchars(strtoupper($metodo_pago)); ?>
                        (<?php echo htmlspecialchars($estado_pago); ?>)
                    </strong>
                </div>
                
                <div class="detalle-item">
                    <span style="color: #666;">Dirección de Envío:</span>
                    <strong>
                        <?php echo htmlspecialchars($direccion_envio); ?>
                        <?php if ($ciudad_envio || $estado_envio || $codigo_postal_envio): ?>
                            , <?php echo htmlspecialchars(trim($ciudad_envio . ' ' . $estado_envio . ' ' . $codigo_postal_envio)); ?>
                        <?php endif; ?>
                    </strong>
                </div>
                
                <div class="detalle-item">
                    <span style="color: #666;">Tiempo Estimado:</span>
                    <strong>3-5 días hábiles</strong>
                </div>
            </div>
            
            <?php if (!empty($orden_detalle['items'])): ?>
            <div style="margin: 2rem 0; padding: 1.5rem; background: #f9f9f9; border-radius: 10px;">
                <h3 style="color: #0D87A8; margin-bottom: 1rem; text-align: left;">Productos en la Orden</h3>
                <div style="display: grid; gap: 1rem;">
                    <?php foreach ($orden_detalle['items'] as $item): ?>
                    <div style="display: flex; gap: 1rem; align-items: center; padding: 1rem; background: white; border-radius: 10px;">
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #0D87A8, #0C9268); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; flex-shrink: 0; <?php echo $item['imagen'] ? 'background-image: url(' . htmlspecialchars($item['imagen']) . '); background-size: cover;' : ''; ?>">
                            <?php if (!$item['imagen']): ?>
                            <i class="fas fa-box"></i>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1; text-align: left;">
                            <strong style="color: #0D87A8; display: block; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($item['titulo']); ?></strong>
                            <div style="color: #666; font-size: 0.9rem;">
                                Cantidad: <?php echo $item['cantidad']; ?> x <?php echo formatearPrecio($item['precio_unitario']); ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <strong style="color: #0C9268; font-size: 1.2rem;">
                                <?php echo formatearPrecio($item['subtotal']); ?>
                            </strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div style="background: #e8fff6; padding: 1.5rem; border-radius: 10px; margin: 2rem 0;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 1rem;">
                    <i class="fas fa-truck" style="font-size: 2rem; color: #0C9268;"></i>
                    <div style="text-align: left;">
                        <strong style="color: #0C9268;">Envío en Proceso</strong>
                        <p style="margin: 0; font-size: 0.85rem; color: #666;">Te notificaremos cuando tu pedido sea enviado</p>
                    </div>
                </div>
            </div>
            
            <div class="acciones-orden">
                <a href="perfil.php?tab=compras" class="btn-accion btn-primario">
                    <i class="fas fa-shopping-bag"></i> Ver Mis Compras
                </a>
                <a href="index.php" class="btn-accion btn-secundario">
                    <i class="fas fa-home"></i> Volver al Inicio
                </a>
            </div>
            
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #f0f0f0;">
                <?php if ($email_usuario): ?>
                    <p style="color: #666; font-size: 0.9rem;">
                        Hemos enviado un correo de confirmación a <strong><?php echo htmlspecialchars($email_usuario); ?></strong>
                    </p>
                <?php endif; ?>
            </div>
            <?php endif; // fin else sin error ?>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
</body>
</html>

