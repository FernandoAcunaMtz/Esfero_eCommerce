<?php
session_start();
require_once __DIR__ . '/includes/api_helper.php';

// Bloquear historial de compras para administradores
if (is_admin()) {
    $_SESSION['error_message'] = 'Los administradores no tienen historial de compras.';
    header('Location: admin_dashboard.php');
    exit;
}

require_login();
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
    <title>Mis Compras - Esfero</title>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .compras-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
        }
        
        .filtros-compras {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .filtro-btn {
            padding: 0.75rem 1.5rem;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .filtro-btn:hover, .filtro-btn.active {
            border-color: #0D87A8;
            background: #0D87A8;
            color: white;
        }
        
        .orden-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .orden-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .orden-numero {
            font-weight: bold;
            color: #0D87A8;
        }
        
        .orden-estado {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
        .estado-entregado {
            background: #e8fff6;
            color: #0C9268;
        }
        
        .estado-enviado {
            background: #e8f3ff;
            color: #0D87A8;
        }
        
        .estado-proceso {
            background: #fff8e6;
            color: #F6A623;
        }
        
        .orden-productos {
            display: grid;
            gap: 1rem;
        }
        
        .producto-orden {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .producto-img {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0D87A8, #0C9268);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .orden-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f0f0f0;
        }
        
        .orden-total {
            font-size: 1.5rem;
            font-weight: bold;
            color: #0C9268;
        }
        
        .orden-acciones {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-accion {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-ver {
            background: #0D87A8;
            color: white;
            border: none;
        }
        
        .btn-rastrear {
            background: white;
            color: #0D87A8;
            border: 2px solid #0D87A8;
        }
        
        @media (max-width: 768px) {
            .orden-header, .orden-footer {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .producto-orden {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="compras-container">
        <h1 style="color: #0D87A8; margin-bottom: 2rem; font-size: 2.5rem;">Mis Compras</h1>
        
        <?php
        // Mostrar mensaje de éxito si viene de PayPal
        if (isset($_GET['success']) && $_GET['success'] == '1') {
            $mensaje = $_GET['mensaje'] ?? 'Pago completado exitosamente';
            echo '<div style="background: #e8fff6; border: 2px solid #0C9268; border-radius: 10px; padding: 1rem; margin-bottom: 2rem; color: #0C9268; font-weight: 600;">';
            echo '<i class="fas fa-check-circle"></i> ' . htmlspecialchars($mensaje);
            echo '</div>';
        }
        
        // Obtener órdenes reales del usuario
        require_once __DIR__ . '/includes/db_connection.php';
        $user = get_session_user();
        $usuario_id = $user['id'] ?? null;
        
        $ordenes = [];
        if ($usuario_id) {
            $stmt = $pdo->prepare("
                SELECT o.*, 
                       GROUP_CONCAT(CONCAT(p.titulo, '|', oi.cantidad, '|', oi.precio_unitario, '|', COALESCE(ip.url_imagen, '')) SEPARATOR '||') as items_data
                FROM ordenes o
                LEFT JOIN orden_items oi ON o.id = oi.orden_id
                LEFT JOIN productos p ON oi.producto_id = p.id
                LEFT JOIN imagenes_productos ip ON p.id = ip.producto_id AND ip.es_principal = 1
                WHERE o.comprador_id = ?
                GROUP BY o.id
                ORDER BY o.fecha_creacion DESC
            ");
            $stmt->execute([$usuario_id]);
            $ordenes = $stmt->fetchAll();
        }
        ?>
        
        <div class="filtros-compras">
            <button class="filtro-btn active" onclick="filtrarOrdenes('todas')">Todas</button>
            <button class="filtro-btn" onclick="filtrarOrdenes('proceso')">En Proceso</button>
            <button class="filtro-btn" onclick="filtrarOrdenes('enviado')">Enviadas</button>
            <button class="filtro-btn" onclick="filtrarOrdenes('entregado')">Entregadas</button>
            <button class="filtro-btn" onclick="filtrarOrdenes('cancelado')">Canceladas</button>
        </div>
        
        <?php if (empty($ordenes)): ?>
            <div style="text-align: center; padding: 4rem; background: white; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                <i class="fas fa-shopping-bag" style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h2 style="color: #666; margin-bottom: 1rem;">No tienes compras aún</h2>
                <p style="color: #999; margin-bottom: 2rem;">Explora nuestros productos y realiza tu primera compra</p>
                <a href="catalogo.php" class="cta-button" style="display: inline-block;">Ver Catálogo</a>
            </div>
        <?php else: ?>
            <?php foreach ($ordenes as $orden): 
                // Mapear estados de la base de datos a estados de visualización
                $estado_db = strtolower($orden['estado'] ?? 'pendiente');
                $estado_pago = strtolower($orden['estado_pago'] ?? 'pendiente');
                
                // Determinar estado visual basado en estado y estado_pago
                if ($estado_pago === 'completado' && in_array($estado_db, ['pago_confirmado', 'proceso', 'en_proceso'])) {
                    $estado_visual = 'proceso';
                } elseif (in_array($estado_db, ['enviado', 'en_camino'])) {
                    $estado_visual = 'enviado';
                } elseif (in_array($estado_db, ['entregado', 'completada'])) {
                    $estado_visual = 'entregado';
                } elseif (in_array($estado_db, ['cancelado', 'cancelada'])) {
                    $estado_visual = 'cancelado';
                } else {
                    $estado_visual = 'proceso';
                }
                
                $estado_clase = 'estado-' . $estado_visual;
                $estado_icono = [
                    'proceso' => 'fa-clock',
                    'enviado' => 'fa-shipping-fast',
                    'entregado' => 'fa-check-circle',
                    'cancelado' => 'fa-times-circle'
                ];
                $icono = $estado_icono[$estado_visual] ?? 'fa-clock';
                $estado_texto = ucfirst($estado_visual);
                
                $items = [];
                if ($orden['items_data']) {
                    $items_raw = explode('||', $orden['items_data']);
                    foreach ($items_raw as $item_raw) {
                        $parts = explode('|', $item_raw);
                        if (count($parts) >= 3) {
                            $items[] = [
                                'titulo' => $parts[0],
                                'cantidad' => $parts[1],
                                'precio' => $parts[2],
                                'imagen' => $parts[3] ?? ''
                            ];
                        }
                    }
                }
            ?>
            <div class="orden-card" data-estado="<?php echo $estado_visual; ?>">
                <div class="orden-header">
                    <div>
                        <div class="orden-numero">Orden #ESF-<?php echo $orden['id']; ?></div>
                        <div style="color: #666; font-size: 0.9rem; margin-top: 0.25rem;">
                            Realizada el <?php echo date('d/m/Y', strtotime($orden['fecha_creacion'])); ?>
                        </div>
                    </div>
                    <span class="orden-estado <?php echo $estado_clase; ?>">
                        <i class="fas <?php echo $icono; ?>"></i> <?php echo $estado_texto; ?>
                    </span>
                </div>
                
                <div class="orden-productos">
                    <?php foreach ($items as $item): 
                        $iconos = ['fa-mobile-alt', 'fa-laptop', 'fa-gamepad', 'fa-headphones', 'fa-camera', 'fa-tshirt'];
                        $icono_item = $iconos[array_rand($iconos)];
                    ?>
                    <div class="producto-orden">
                        <div class="producto-img" style="<?php echo $item['imagen'] ? 'background-image: url(' . htmlspecialchars($item['imagen']) . '); background-size: cover;' : ''; ?>">
                            <?php if (!$item['imagen']): ?>
                            <i class="fas <?php echo $icono_item; ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1;">
                            <strong style="color: #0D87A8;"><?php echo htmlspecialchars($item['titulo']); ?></strong>
                            <div style="color: #666; font-size: 0.9rem;">Cantidad: <?php echo (int)$item['cantidad']; ?></div>
                        </div>
                        <div>
                            <strong style="color: #0C9268;"><?php echo formatearPrecio($item['precio']); ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="orden-footer">
                    <div>
                        <div style="color: #666; font-size: 0.9rem;">Total</div>
                        <div class="orden-total"><?php echo formatearPrecio($orden['total']); ?></div>
                    </div>
                    <div class="orden-acciones">
                        <?php if (strtolower($orden['estado']) === 'enviado'): ?>
                        <button class="btn-accion btn-rastrear" onclick="window.location.href='orden.php?id=<?php echo $orden['id']; ?>'">
                            <i class="fas fa-map-marker-alt"></i> Rastrear
                        </button>
                        <?php endif; ?>
                        <button class="btn-accion btn-ver" onclick="window.location.href='orden.php?id=<?php echo $orden['id']; ?>'">
                            <i class="fas fa-eye"></i> Ver Detalles
                        </button>
                        <?php if ($estado_visual === 'entregado'): ?>
                        <a href="calificar.php?orden_id=<?php echo (int)$orden['id']; ?>" class="btn-accion btn-ver">
                            <i class="fas fa-star"></i> Calificar
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php include 'components/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script>
        function filtrarOrdenes(estado) {
            const ordenes = document.querySelectorAll('.orden-card');
            const botones = document.querySelectorAll('.filtro-btn');
            
            botones.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            ordenes.forEach(orden => {
                if (estado === 'todas' || orden.dataset.estado === estado) {
                    orden.style.display = 'block';
                } else {
                    orden.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>

