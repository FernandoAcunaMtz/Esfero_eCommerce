<?php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
session_start();
require_role('admin', 'index.php');

// Obtener estadísticas del dashboard
$stats = getEstadisticasDashboard();
$actividad = getActividadReciente(5);
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
    <title>Dashboard Admin - Esfero</title>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    <div style="width: 100%; margin: 100px auto 50px; padding: 2rem;">
        <h1 style="color: #dc3545; margin-bottom: 2rem; font-size: clamp(1.5rem, 4vw, 2.5rem);">
            <i class="fas fa-shield-alt"></i> Dashboard Administrativo
        </h1>
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem;" id="adminDashboardLayout">
            <?php include 'components/sidebar_admin.php'; ?>
            <div>
                <!-- Stats Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div style="background: linear-gradient(135deg, #0D87A8, #0C9268); padding: 2rem; border-radius: 15px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 2.5rem; font-weight: bold;"><?php echo number_format($stats['usuarios_totales']); ?></div>
                                <div style="opacity: 0.9;">Usuarios Totales</div>
                            </div>
                            <i class="fas fa-users" style="font-size: 3rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, #F6A623, #f37d00); padding: 2rem; border-radius: 15px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 2.5rem; font-weight: bold;"><?php echo number_format($stats['productos_activos']); ?></div>
                                <div style="opacity: 0.9;">Productos Activos</div>
                            </div>
                            <i class="fas fa-box" style="font-size: 3rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, #8E2DE2, #4A00E0); padding: 2rem; border-radius: 15px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 2.5rem; font-weight: bold;"><?php echo formatearPrecio($stats['ventas_mes']); ?></div>
                                <div style="opacity: 0.9;">Ventas del Mes</div>
                            </div>
                            <i class="fas fa-dollar-sign" style="font-size: 3rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, #dc3545, #c82333); padding: 2rem; border-radius: 15px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 2.5rem; font-weight: bold;"><?php echo number_format($stats['reportes_pendientes']); ?></div>
                                <div style="opacity: 0.9;">Reportes Pendientes</div>
                            </div>
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Gráficas y Actividad -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;" id="adminActivityLayout">
                    <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                        <h2 style="color: #0D87A8; margin-bottom: 1.5rem;">Actividad Reciente</h2>
                        <?php if (empty($actividad)): ?>
                            <p style="color: #666; text-align: center; padding: 2rem;">No hay actividad reciente</p>
                        <?php else: ?>
                            <?php foreach ($actividad as $item): 
                                $fecha = new DateTime($item['fecha']);
                                $ahora = new DateTime();
                                $diferencia = $ahora->diff($fecha);
                                $tiempo_texto = '';
                                if ($diferencia->days > 0) {
                                    $tiempo_texto = 'Hace ' . $diferencia->days . ' día' . ($diferencia->days > 1 ? 's' : '');
                                } elseif ($diferencia->h > 0) {
                                    $tiempo_texto = 'Hace ' . $diferencia->h . ' hora' . ($diferencia->h > 1 ? 's' : '');
                                } elseif ($diferencia->i > 0) {
                                    $tiempo_texto = 'Hace ' . $diferencia->i . ' minuto' . ($diferencia->i > 1 ? 's' : '');
                                } else {
                                    $tiempo_texto = 'Hace unos momentos';
                                }
                            ?>
                            <div style="padding: 1rem 0; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 40px; height: 40px; background: #f0f8ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #0D87A8;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div style="flex: 1;">
                                    <strong style="color: #0D87A8;"><?php echo htmlspecialchars($item['descripcion']); ?></strong>
                                    <p style="margin: 0; color: #666; font-size: 0.85rem;"><?php echo htmlspecialchars($item['titulo']); ?> • <?php echo $tiempo_texto; ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                        <h2 style="color: #0D87A8; margin-bottom: 1.5rem;">Acciones Rápidas</h2>
                        <div style="display: grid; gap: 1rem;">
                            <a href="admin_usuarios.php" style="padding: 1rem; background: #f0f8ff; color: #0D87A8; text-decoration: none; border-radius: 10px; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-users"></i> Ver Usuarios
                            </a>
                            <a href="admin_productos.php" style="padding: 1rem; background: #fff8e6; color: #F6A623; text-decoration: none; border-radius: 10px; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-box"></i> Moderar Productos
                            </a>
                            <a href="admin_reportes.php" style="padding: 1rem; background: #ffe5e5; color: #dc3545; text-decoration: none; border-radius: 10px; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-chart-bar"></i> Ver Reportes
                            </a>
                            <a href="admin_guias.php" style="padding: 1rem; background: #e7f3ff; color: #0D87A8; text-decoration: none; border-radius: 10px; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-book"></i> Gestionar Guías
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <style>
    /* Responsividad para admin dashboard */
    @media (max-width: 968px) {
        #adminDashboardLayout {
            grid-template-columns: 1fr !important;
        }
        
        #adminActivityLayout {
            grid-template-columns: 1fr !important;
        }
    }
    
    @media (max-width: 640px) {
        #adminDashboardLayout {
            padding: 0 !important;
        }

        /* Scale stat card values */
        #adminDashboardLayout div[style*="font-size: 2.5rem"] {
            font-size: 1.75rem !important;
        }

        #adminDashboardLayout div[style*="padding: 2rem"] {
            padding: 1.25rem !important;
        }

        #adminDashboardLayout div[style*="font-size: 3rem"] {
            font-size: 2rem !important;
        }
    }
    </style>
</body>
</html>
