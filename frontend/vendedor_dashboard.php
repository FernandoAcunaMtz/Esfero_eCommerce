<?php
session_start();
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth_middleware.php'; // Debe cargarse antes que api_helper.php para que puede_vender() esté disponible
require_once __DIR__ . '/includes/api_helper.php';

// Bloquear dashboard vendedor para administradores
if (is_admin()) {
    $_SESSION['error_message'] = 'Los administradores no tienen dashboard de vendedor.';
    header('Location: admin_dashboard.php');
    exit;
}

// Verificar que el usuario pueda vender usando puede_vender() directamente
$user = get_session_user();
if (!$user || !function_exists('puede_vender')) {
    $_SESSION['error_message'] = 'Error al verificar permisos de vendedor.';
    header('Location: index.php');
    exit;
}

if (!puede_vender($user['id'] ?? null)) {
    $_SESSION['error_message'] = 'No tienes permisos para acceder al panel de vendedor.';
    header('Location: perfil.php');
    exit;
}

// Obtener datos del vendedor
$user = get_session_user();
$vendedor_id = $user['id'] ?? null;

if (!$vendedor_id || !isset($pdo)) {
    $_SESSION['error_message'] = 'Error al cargar datos del vendedor.';
    header('Location: index.php');
    exit;
}

// Obtener estadísticas reales del vendedor
$stats = [];

// 1. Productos activos
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM productos WHERE vendedor_id = ? AND activo = 1 AND vendido = 0");
$stmt->execute([$vendedor_id]);
$stats['productos_activos'] = (int)$stmt->fetch()['total'];

// 2. Productos activos del mes anterior (para comparación)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM productos 
    WHERE vendedor_id = ? 
    AND activo = 1 
    AND vendido = 0 
    AND fecha_publicacion < DATE_SUB(NOW(), INTERVAL 1 MONTH)
");
$stmt->execute([$vendedor_id]);
$productos_mes_anterior = (int)$stmt->fetch()['total'];
$stats['productos_cambio'] = $stats['productos_activos'] - $productos_mes_anterior;

// 3. Ventas del mes (total en dinero)
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

// 4. Ventas del mes anterior (para comparación)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total), 0) as total_ventas
    FROM ordenes 
    WHERE vendedor_id = ? 
    AND estado_pago = 'completado'
    AND MONTH(fecha_creacion) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
    AND YEAR(fecha_creacion) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
");
$stmt->execute([$vendedor_id]);
$ventas_mes_anterior = (float)$stmt->fetch()['total_ventas'];
$stats['ventas_cambio_porcentaje'] = $ventas_mes_anterior > 0 
    ? (($stats['ventas_mes'] - $ventas_mes_anterior) / $ventas_mes_anterior) * 100 
    : ($stats['ventas_mes'] > 0 ? 100 : 0);

// 5. Ventas totales (cantidad de órdenes completadas)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM ordenes 
    WHERE vendedor_id = ? 
    AND estado_pago = 'completado'
");
$stmt->execute([$vendedor_id]);
$stats['ventas_totales'] = (int)$stmt->fetch()['total'];

// 6. Ventas de esta semana
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM ordenes 
    WHERE vendedor_id = ? 
    AND estado_pago = 'completado'
    AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$vendedor_id]);
$stats['ventas_semana'] = (int)$stmt->fetch()['total'];

// 7. Calificación promedio
$stmt = $pdo->prepare("
    SELECT AVG(calificacion) as promedio, COUNT(*) as total_resenas
    FROM calificaciones 
    WHERE calificado_id = ? 
    AND tipo = 'vendedor'
    AND visible = 1
");
$stmt->execute([$vendedor_id]);
$calif_data = $stmt->fetch();
$stats['calificacion'] = $calif_data['promedio'] ? round((float)$calif_data['promedio'], 1) : 0;
$stats['total_resenas'] = (int)$calif_data['total_resenas'];

// Obtener actividad reciente
$actividad = [];

// Ventas recientes
$stmt = $pdo->prepare("
    SELECT o.*, oi.producto_id, oi.producto_titulo, oi.cantidad, oi.subtotal,
           (SELECT url_imagen FROM imagenes_productos 
            WHERE producto_id = oi.producto_id AND es_principal = 1 
            LIMIT 1) as imagen_principal
    FROM ordenes o
    INNER JOIN orden_items oi ON o.id = oi.orden_id
    WHERE o.vendedor_id = ? 
    AND o.estado_pago = 'completado'
    ORDER BY o.fecha_creacion DESC
    LIMIT 5
");
$stmt->execute([$vendedor_id]);
$ventas_recientes = $stmt->fetchAll();

foreach ($ventas_recientes as $venta) {
    $actividad[] = [
        'tipo' => 'venta',
        'titulo' => $venta['producto_titulo'],
        'fecha' => $venta['fecha_creacion'],
        'precio' => $venta['subtotal'],
        'imagen' => $venta['imagen_principal']
    ];
}

// Productos publicados recientemente
$stmt = $pdo->prepare("
    SELECT p.*,
           (SELECT url_imagen FROM imagenes_productos 
            WHERE producto_id = p.id AND es_principal = 1 
            LIMIT 1) as imagen_principal
    FROM productos p
    WHERE p.vendedor_id = ? 
    AND p.activo = 1 
    AND p.vendido = 0
    ORDER BY p.fecha_publicacion DESC
    LIMIT 5
");
$stmt->execute([$vendedor_id]);
$productos_recientes = $stmt->fetchAll();

foreach ($productos_recientes as $producto) {
    $actividad[] = [
        'tipo' => 'publicacion',
        'titulo' => $producto['titulo'],
        'fecha' => $producto['fecha_publicacion'],
        'precio' => null,
        'imagen' => $producto['imagen_principal']
    ];
}

// Ordenar actividad por fecha
usort($actividad, function($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

$actividad = array_slice($actividad, 0, 5);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0D87A8">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Dashboard Vendedor - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .dashboard-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, #0D87A8, #0C9268);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #0D87A8;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .stat-change {
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .stat-change.up {
            color: #0C9268;
        }
        
        .stat-change.down {
            color: #dc3545;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .card-title {
            color: #0D87A8;
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .producto-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
        }
        
        .producto-item:last-child {
            border-bottom: none;
        }
        
        .producto-mini-img {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0D87A8, #0C9268);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .producto-mini-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        @media (max-width: 968px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .dashboard-container {
                padding: 1rem !important;
                margin-top: 80px !important;
            }

            .stat-value {
                font-size: 1.75rem;
            }

            .stat-card {
                padding: 1.25rem;
            }

            .card {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="dashboard-container">
        <h1 style="color: #0D87A8; margin-bottom: 2rem; font-size: clamp(1.5rem, 4vw, 2.5rem);">
            <i class="fas fa-store"></i> Dashboard Vendedor
        </h1>
        
        <div class="dashboard-grid">
            <?php include 'components/sidebar_vendedor.php'; ?>
            
            <div>
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo number_format($stats['productos_activos']); ?></div>
                                <div class="stat-label">Productos Activos</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-box"></i>
                            </div>
                        </div>
                        <?php if ($stats['productos_cambio'] != 0): ?>
                        <div class="stat-change <?php echo $stats['productos_cambio'] > 0 ? 'up' : 'down'; ?>">
                            <i class="fas fa-arrow-<?php echo $stats['productos_cambio'] > 0 ? 'up' : 'down'; ?>"></i> 
                            <?php echo $stats['productos_cambio'] > 0 ? '+' : ''; ?><?php echo $stats['productos_cambio']; ?> este mes
                        </div>
                        <?php else: ?>
                        <div class="stat-change" style="color: #666;">
                            Sin cambios este mes
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo formatearPrecio($stats['ventas_mes']); ?></div>
                                <div class="stat-label">Ventas del Mes</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                        <?php if ($stats['ventas_cambio_porcentaje'] != 0): ?>
                        <div class="stat-change <?php echo $stats['ventas_cambio_porcentaje'] > 0 ? 'up' : 'down'; ?>">
                            <i class="fas fa-arrow-<?php echo $stats['ventas_cambio_porcentaje'] > 0 ? 'up' : 'down'; ?>"></i> 
                            <?php echo $stats['ventas_cambio_porcentaje'] > 0 ? '+' : ''; ?><?php echo number_format($stats['ventas_cambio_porcentaje'], 1); ?>%
                        </div>
                        <?php else: ?>
                        <div class="stat-change" style="color: #666;">
                            Sin ventas previas
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo number_format($stats['ventas_totales']); ?></div>
                                <div class="stat-label">Ventas Totales</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                        </div>
                        <?php if ($stats['ventas_semana'] > 0): ?>
                        <div class="stat-change up">
                            <i class="fas fa-arrow-up"></i> +<?php echo $stats['ventas_semana']; ?> esta semana
                        </div>
                        <?php else: ?>
                        <div class="stat-change" style="color: #666;">
                            Sin ventas esta semana
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo $stats['calificacion'] > 0 ? number_format($stats['calificacion'], 1) : 'N/A'; ?></div>
                                <div class="stat-label">Calificación</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                        <?php if ($stats['total_resenas'] > 0): ?>
                        <div class="stat-change up">
                            <?php echo $stats['total_resenas']; ?> reseña<?php echo $stats['total_resenas'] > 1 ? 's' : ''; ?>
                        </div>
                        <?php else: ?>
                        <div class="stat-change" style="color: #666;">
                            Sin reseñas aún
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="content-grid">
                    <!-- Actividad Reciente -->
                    <div class="card">
                        <h2 class="card-title">
                            <i class="fas fa-clock"></i> Actividad Reciente
                        </h2>
                        <?php if (empty($actividad)): ?>
                            <p style="color: #666; text-align: center; padding: 2rem;">No hay actividad reciente</p>
                        <?php else: ?>
                            <?php foreach ($actividad as $item): 
                                $fecha = new DateTime($item['fecha']);
                                $ahora = new DateTime();
                                $diff = $ahora->diff($fecha);
                                
                                $tiempo = '';
                                if ($diff->days > 0) {
                                    $tiempo = 'Hace ' . $diff->days . ' día' . ($diff->days > 1 ? 's' : '');
                                } elseif ($diff->h > 0) {
                                    $tiempo = 'Hace ' . $diff->h . ' hora' . ($diff->h > 1 ? 's' : '');
                                } elseif ($diff->i > 0) {
                                    $tiempo = 'Hace ' . $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '');
                                } else {
                                    $tiempo = 'Hace unos momentos';
                                }
                                
                                $iconos = ['fa-mobile-alt', 'fa-laptop', 'fa-gamepad', 'fa-headphones', 'fa-camera', 'fa-tshirt'];
                                $icono = $iconos[array_rand($iconos)];
                            ?>
                            <div class="producto-item">
                                <div class="producto-mini-img">
                                    <?php if (!empty($item['imagen'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['imagen']); ?>" alt="<?php echo htmlspecialchars($item['titulo']); ?>" onerror="this.parentElement.innerHTML='<i class=\'fas <?php echo $icono; ?>\'></i>'">
                                    <?php else: ?>
                                        <i class="fas <?php echo $icono; ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1;">
                                    <strong style="color: #0D87A8;"><?php echo htmlspecialchars($item['titulo']); ?> <?php echo $item['tipo'] === 'venta' ? 'vendido' : 'publicado'; ?></strong>
                                    <p style="margin: 0; color: #666; font-size: 0.85rem;"><?php echo $tiempo; ?></p>
                                </div>
                                <?php if ($item['tipo'] === 'venta' && $item['precio']): ?>
                                <strong style="color: #0C9268;">+<?php echo formatearPrecio($item['precio']); ?></strong>
                                <?php else: ?>
                                <span style="color: #666; font-size: 0.85rem;">Nuevo</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Acciones Rápidas -->
                    <div class="card">
                        <h2 class="card-title">
                            <i class="fas fa-bolt"></i> Acciones Rápidas
                        </h2>
                        
                        <div style="display: grid; gap: 1rem;">
                            <a href="publicar_producto.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: linear-gradient(45deg, #0D87A8, #0C9268); color: white; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                                <i class="fas fa-plus-circle" style="font-size: 1.5rem;"></i>
                                <div>
                                    <strong>Publicar Producto</strong>
                                    <p style="margin: 0; font-size: 0.85rem; opacity: 0.9;">Añadir nuevo producto</p>
                                </div>
                            </a>
                            
                            <a href="mis_productos.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f0f8ff; color: #0D87A8; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                                <i class="fas fa-box" style="font-size: 1.5rem;"></i>
                                <div>
                                    <strong>Mis Productos</strong>
                                    <p style="margin: 0; font-size: 0.85rem; opacity: 0.7;">Gestionar inventario</p>
                                </div>
                            </a>
                            
                            <?php if ($stats['ventas_totales'] > 0): ?>
                            <a href="ventas.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f0f8ff; color: #0D87A8; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                                <i class="fas fa-chart-line" style="font-size: 1.5rem;"></i>
                                <div>
                                    <strong>Ver Ventas</strong>
                                    <p style="margin: 0; font-size: 0.85rem; opacity: 0.7;">Historial completo</p>
                                </div>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
</body>
</html>
