<?php
session_start();
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/db_connection.php';

// Bloquear ventas para administradores
if (is_admin()) {
    $_SESSION['error_message'] = 'Los administradores no tienen ventas.';
    header('Location: admin_dashboard.php');
    exit;
}

require_vendedor('activar_vendedor.php');

// Obtener datos del vendedor
$user = get_session_user();
$vendedor_id = $user['id'] ?? null;

if (!$vendedor_id || !isset($pdo)) {
    $_SESSION['error_message'] = 'Error al cargar datos del vendedor.';
    header('Location: index.php');
    exit;
}

// Obtener filtro de estado
$filtro_estado = $_GET['estado'] ?? 'todas';
$estados_validos = ['todas', 'completada', 'pendiente', 'cancelada', 'en_camino', 'entregada'];
if (!in_array($filtro_estado, $estados_validos)) {
    $filtro_estado = 'todas';
}

// Obtener estadísticas reales
$stats = [];

// Ventas del mes
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total), 0) as total_ventas
    FROM ordenes 
    WHERE vendedor_id = ? 
    AND estado_pago = 'completado'
    AND MONTH(fecha_creacion) = MONTH(NOW())
    AND YEAR(fecha_creacion) = YEAR(NOW())
");
$stmt->execute([$vendedor_id]);
$stats['ventas_mes'] = (float)$stmt->fetch()['total_ventas'];

// Total de ventas
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM ordenes 
    WHERE vendedor_id = ? 
    AND estado_pago = 'completado'
");
$stmt->execute([$vendedor_id]);
$stats['total_ventas'] = (int)$stmt->fetch()['total'];

// Ventas pendientes
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM ordenes 
    WHERE vendedor_id = ? 
    AND estado_pago IN ('pendiente', 'procesando')
");
$stmt->execute([$vendedor_id]);
$stats['pendientes'] = (int)$stmt->fetch()['total'];

// Obtener lista de ventas
$sql = "
    SELECT o.*, 
           u.nombre as comprador_nombre,
           u.email as comprador_email
    FROM ordenes o
    LEFT JOIN usuarios u ON o.comprador_id = u.id
    WHERE o.vendedor_id = ?
";

$params = [$vendedor_id];

// Aplicar filtro de estado
if ($filtro_estado !== 'todas') {
    if ($filtro_estado === 'completada') {
        $sql .= " AND o.estado_pago = 'completado'";
    } elseif ($filtro_estado === 'pendiente') {
        $sql .= " AND o.estado_pago IN ('pendiente', 'procesando')";
    } elseif ($filtro_estado === 'cancelada') {
        $sql .= " AND o.estado IN ('cancelada', 'reembolsada')";
    } else {
        $sql .= " AND o.estado = ?";
        $params[] = $filtro_estado;
    }
}

$sql .= " ORDER BY o.fecha_creacion DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ventas = $stmt->fetchAll();
    
    // Obtener items para cada venta
    foreach ($ventas as &$venta) {
        $stmt_items = $pdo->prepare("
            SELECT producto_titulo, cantidad, subtotal, producto_imagen
            FROM orden_items
            WHERE orden_id = ?
            LIMIT 1
        ");
        $stmt_items->execute([$venta['id']]);
        $item = $stmt_items->fetch();
        $venta['primer_item'] = $item;
    }
    unset($venta); // Liberar referencia
} catch (PDOException $e) {
    error_log("Error al obtener ventas: " . $e->getMessage());
    $ventas = [];
}

// Función para obtener el texto del estado de la orden
function getEstadoOrdenTexto($estado_pago, $estado) {
    if ($estado_pago === 'completado') {
        if ($estado === 'entregada' || $estado === 'completada') {
            return 'Completada';
        } elseif ($estado === 'en_camino') {
            return 'En Camino';
        } else {
            return 'Pago Confirmado';
        }
    } elseif ($estado_pago === 'pendiente' || $estado_pago === 'procesando') {
        return 'Pendiente';
    } elseif ($estado_pago === 'fallido') {
        return 'Fallida';
    } elseif ($estado === 'cancelada' || $estado === 'reembolsada') {
        return 'Cancelada';
    }
    return ucfirst(str_replace('_', ' ', $estado));
}

// Función para obtener el color del estado de la orden
function getEstadoOrdenColor($estado_pago, $estado) {
    if ($estado_pago === 'completado') {
        if ($estado === 'entregada' || $estado === 'completada') {
            return ['bg' => '#e8fff6', 'color' => '#0C9268'];
        } elseif ($estado === 'en_camino') {
            return ['bg' => '#fff8e6', 'color' => '#F6A623'];
        } else {
            return ['bg' => '#e8f4f8', 'color' => '#0D87A8'];
        }
    } elseif ($estado_pago === 'pendiente' || $estado_pago === 'procesando') {
        return ['bg' => '#fff8e6', 'color' => '#F6A623'];
    } elseif ($estado_pago === 'fallido' || $estado === 'cancelada' || $estado === 'reembolsada') {
        return ['bg' => '#ffe6e6', 'color' => '#dc3545'];
    }
    return ['bg' => '#f0f0f0', 'color' => '#666'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0D87A8">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Mis Ventas - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    <div style="width: 100%; margin: 100px auto 50px; padding: 2rem;">
        <h1 style="color: #0D87A8; margin-bottom: 2rem; font-size: clamp(1.5rem, 4vw, 2.5rem);">
            <i class="fas fa-chart-line"></i> Mis Ventas
        </h1>
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem;" id="ventasLayout">
            <?php include 'components/sidebar_vendedor.php'; ?>
            <div>
                <!-- Stats -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                        <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Ventas del Mes</div>
                        <div style="font-size: 2rem; font-weight: bold; color: #0C9268;"><?php echo formatearPrecio($stats['ventas_mes']); ?></div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                        <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Total Ventas</div>
                        <div style="font-size: 2rem; font-weight: bold; color: #0D87A8;"><?php echo number_format($stats['total_ventas']); ?></div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                        <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Pendientes</div>
                        <div style="font-size: 2rem; font-weight: bold; color: #F6A623;"><?php echo number_format($stats['pendientes']); ?></div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); margin-bottom: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="?estado=todas" class="cta-button" style="background: <?php echo $filtro_estado === 'todas' ? '#0D87A8' : 'white'; ?>; color: <?php echo $filtro_estado === 'todas' ? 'white' : '#0D87A8'; ?>; border: 2px solid #0D87A8; text-decoration: none;">Todas</a>
                    <a href="?estado=completada" class="cta-button" style="background: <?php echo $filtro_estado === 'completada' ? '#0D87A8' : 'white'; ?>; color: <?php echo $filtro_estado === 'completada' ? 'white' : '#0D87A8'; ?>; border: 2px solid #0D87A8; text-decoration: none;">Completadas</a>
                    <a href="?estado=pendiente" class="cta-button" style="background: <?php echo $filtro_estado === 'pendiente' ? '#0D87A8' : 'white'; ?>; color: <?php echo $filtro_estado === 'pendiente' ? 'white' : '#0D87A8'; ?>; border: 2px solid #0D87A8; text-decoration: none;">Pendientes</a>
                    <a href="?estado=cancelada" class="cta-button" style="background: <?php echo $filtro_estado === 'cancelada' ? '#0D87A8' : 'white'; ?>; color: <?php echo $filtro_estado === 'cancelada' ? 'white' : '#0D87A8'; ?>; border: 2px solid #0D87A8; text-decoration: none;">Canceladas</a>
                </div>
                
                <!-- Lista de Ventas -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                    <h2 style="color: #0D87A8; margin-bottom: 1.5rem;">Historial de Ventas</h2>
                    
                    <?php if (empty($ventas)): ?>
                        <div style="text-align: center; padding: 4rem; color: #666;">
                            <i class="fas fa-shopping-bag" style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;"></i>
                            <h3 style="color: #666; margin-bottom: 1rem;">No tienes ventas aún</h3>
                            <p style="color: #999; margin-bottom: 2rem;">Cuando vendas productos, aparecerán aquí</p>
                            <a href="publicar_producto.php" class="cta-button" style="display: inline-block;">Publicar mi primer producto</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($ventas as $venta): 
                            $estado_info = getEstadoOrdenColor($venta['estado_pago'], $venta['estado']);
                            $estado_texto = getEstadoOrdenTexto($venta['estado_pago'], $venta['estado']);
                            
                            // Obtener primera imagen del primer item
                            $imagen_venta = '';
                            if (!empty($venta['primer_item']['producto_imagen'])) {
                                $imagen_venta = $venta['primer_item']['producto_imagen'];
                            }
                            
                            $comprador_nombre = $venta['comprador_nombre'] ?: 'Usuario #' . $venta['comprador_id'];
                            $fecha_venta = date('d/m/Y', strtotime($venta['fecha_creacion']));
                            
                            // Obtener cantidad de items
                            $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM orden_items WHERE orden_id = ?");
                            $stmt_count->execute([$venta['id']]);
                            $items_count = (int)$stmt_count->fetch()['total'];
                        ?>
                        <div style="padding: 1.5rem 0; border-bottom: 1px solid #f0f0f0; display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap;" class="venta-item">
                            <div style="width: 80px; height: 80px; min-width: 80px; background: linear-gradient(135deg, #0D87A8, #0C9268); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; overflow: hidden;">
                                <?php if (!empty($imagen_venta)): ?>
                                    <img src="<?php echo htmlspecialchars($imagen_venta); ?>" alt="<?php echo htmlspecialchars($venta['primer_item']['producto_titulo'] ?? 'Producto'); ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <i class="fas fa-box" style="display: none;"></i>
                                <?php else: ?>
                                    <i class="fas fa-box"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <strong style="color: #0D87A8; font-size: clamp(0.9rem, 2vw, 1rem);">Venta #ESF-<?php echo htmlspecialchars($venta['numero_orden'] ?: str_pad($venta['id'], 6, '0', STR_PAD_LEFT)); ?></strong>
                                <p style="color: #666; margin: 0.25rem 0; font-size: clamp(0.8rem, 2vw, 0.9rem);">
                                    Comprador: <?php echo htmlspecialchars($comprador_nombre); ?> • <?php echo $fecha_venta; ?>
                                </p>
                                <?php if ($items_count > 0): ?>
                                <p style="color: #999; margin: 0.25rem 0; font-size: clamp(0.75rem, 2vw, 0.85rem);">
                                    <?php echo $items_count; ?> producto<?php echo $items_count > 1 ? 's' : ''; ?>
                                </p>
                                <?php endif; ?>
                                <span style="background: <?php echo $estado_info['bg']; ?>; color: <?php echo $estado_info['color']; ?>; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: clamp(0.75rem, 2vw, 0.85rem); display: inline-block; margin-top: 0.5rem;">
                                    <?php echo htmlspecialchars($estado_texto); ?>
                                </span>
                            </div>
                            <div style="text-align: right; width: 100%; margin-top: 1rem;" class="venta-actions">
                                <div style="font-size: clamp(1.25rem, 3vw, 1.5rem); font-weight: bold; color: #0C9268; margin-bottom: 0.5rem;">
                                    <?php echo formatearPrecio($venta['total'], $venta['moneda']); ?>
                                </div>
                                <a href="venta_detalle.php?id=<?php echo (int)$venta['id']; ?>" class="cta-button" style="padding: 0.5rem 1rem; font-size: clamp(0.85rem, 2vw, 1rem); text-decoration: none; display: inline-block;">
                                    <i class="fas fa-eye"></i> Ver Detalles
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <style>
    /* Responsividad para ventas */
    @media (max-width: 968px) {
        #ventasLayout {
            grid-template-columns: 1fr !important;
        }
        
        .venta-item {
            flex-direction: column !important;
            text-align: center;
        }
        
        .venta-actions {
            text-align: center !important;
        }
    }
    
    @media (max-width: 640px) {
        div[style*="width: 100%"] {
            padding: 1rem !important;
        }
    }
    </style>
</body>
</html>
