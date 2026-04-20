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

// Procesar acciones de reportes
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $reporte_id = isset($_POST['reporte_id']) ? (int)$_POST['reporte_id'] : 0;
    $admin_id = (int)$_SESSION['user_id'];
    
    if ($_POST['accion'] === 'resolver' && $reporte_id > 0) {
        $estado = sanitize_input($_POST['estado'] ?? '');
        $resolucion = sanitize_input($_POST['resolucion'] ?? '');
        
        if (in_array($estado, ['resuelto', 'rechazado', 'en_revision'])) {
            if (resolverReporte($reporte_id, $admin_id, $estado, $resolucion)) {
                $mensaje = 'Reporte actualizado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al actualizar el reporte';
                $tipo_mensaje = 'error';
            }
        }
    }
}

// Obtener filtros
$filtros = [
    'estado' => sanitize_input($_GET['estado'] ?? 'todos'),
    'tipo_reporte' => sanitize_input($_GET['tipo_reporte'] ?? 'todos'),
    'busqueda' => sanitize_input($_GET['busqueda'] ?? ''),
    'limite' => 50,
    'offset' => 0
];

// Obtener estadísticas generales
$stats = [];
$top_categorias = [];
$top_vendedores = [];
$ventas_mensuales = [];
$stats_reportes = [];
$reportes = [];
$total_reportes = 0;

try {
    $stats = getEstadisticasReportes();
    $top_categorias = getTopCategoriasVentas(5);
    $top_vendedores = getTopVendedores(5);
    $ventas_mensuales = getVentasMensuales();
    
    // Obtener estadísticas de reportes
    $stats_reportes = getEstadisticasReportesAdmin();
    
    // Obtener reportes de usuarios
    $reportes = getReportesAdmin($filtros);
    $total_reportes = contarReportesAdmin($filtros);
} catch (Exception $e) {
    error_log("Error en admin_reportes.php: " . $e->getMessage());
    $mensaje = 'Error al cargar los datos: ' . $e->getMessage();
    $tipo_mensaje = 'error';
    
    // Valores por defecto para evitar errores
    $stats = [
        'ingresos_totales' => 0,
        'transacciones' => 0,
        'usuarios_activos' => 0,
        'productos_activos' => 0
    ];
    $stats_reportes = [
        'pendientes' => 0,
        'en_revision' => 0,
        'resueltos' => 0,
        'rechazados' => 0,
        'total' => 0
    ];
}

// Calcular máximo para barras de progreso
$max_ventas_cat = !empty($top_categorias) && is_array($top_categorias) ? max(array_column($top_categorias, 'total_ventas')) : 1;
$max_ingresos_vend = !empty($top_vendedores) && is_array($top_vendedores) ? max(array_column($top_vendedores, 'ingresos_totales')) : 1;
$max_ventas_mes = !empty($ventas_mensuales) && is_array($ventas_mensuales) ? max(array_column($ventas_mensuales, 'ingresos')) : 1;

// Nombres de estados y tipos
$estado_nombres = [
    'pendiente' => 'Pendiente',
    'en_revision' => 'En Revisión',
    'resuelto' => 'Resuelto',
    'rechazado' => 'Rechazado'
];

$tipo_reporte_nombres = [
    'producto_falso' => 'Producto Falso',
    'usuario_sospechoso' => 'Usuario Sospechoso',
    'fraude' => 'Fraude',
    'contenido_inapropiado' => 'Contenido Inapropiado',
    'spam' => 'Spam',
    'otro' => 'Otro'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Reportes y Estadísticas - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .admin-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-card .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stat-card .stat-label {
            color: #666;
        }
        .filtros {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .filtros-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: end;
        }
        .filtro-group {
            flex: 1;
            min-width: 150px;
        }
        .filtro-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #0D87A8;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .filtro-group input,
        .filtro-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .btn-filtrar {
            background: #0C9268;
            color: white;
            padding: 0.5rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .reportes-table {
            background: white;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            -webkit-overflow-scrolling: touch;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #0D87A8;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-pendiente { background: #fff3cd; color: #856404; }
        .badge-revision { background: #cfe2ff; color: #084298; }
        .badge-resuelto { background: #d1e7dd; color: #0f5132; }
        .badge-rechazado { background: #f8d7da; color: #842029; }
        .btn-ver {
            background: #0D87A8;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }
        .btn-ver:hover {
            background: #044E65;
        }
        .mensaje {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .mensaje.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .chart-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        .chart-bars {
            display: flex;
            align-items: flex-end;
            gap: 0.5rem;
            height: 300px;
            padding: 1rem 0;
        }
        .chart-bar {
            flex: 1;
            background: linear-gradient(180deg, #0D87A8, #0C9268);
            border-radius: 4px 4px 0 0;
            min-height: 20px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            padding: 0.5rem;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .chart-bar:hover {
            opacity: 0.8;
        }
        .chart-labels {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .chart-label {
            flex: 1;
            text-align: center;
            font-size: 0.75rem;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <div class="admin-container">
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem;">
            <?php include 'components/sidebar_admin.php'; ?>
            
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h1 style="color: #dc3545; font-size: clamp(1.5rem, 4vw, 2.5rem);">
                        <i class="fas fa-chart-bar"></i> Reportes y Estadísticas
                    </h1>
                </div>
                
                <?php if ($mensaje): ?>
                    <div class="mensaje <?php echo $tipo_mensaje; ?>">
                        <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Estadísticas Generales -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #0C9268;"><?php echo formatearPrecio($stats['ingresos_totales'] ?? 0); ?></div>
                        <div class="stat-label">Ingresos Totales</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" style="color: #0D87A8;"><?php echo number_format($stats['transacciones'] ?? 0); ?></div>
                        <div class="stat-label">Transacciones</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" style="color: #F6A623;"><?php echo number_format($stats['usuarios_activos'] ?? 0); ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" style="color: #0D87A8;"><?php echo number_format($stats['productos_activos'] ?? 0); ?></div>
                        <div class="stat-label">Productos Activos</div>
                    </div>
                </div>
                
                <!-- Estadísticas de Reportes -->
                <div class="stats-grid" style="margin-bottom: 2rem;">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #dc3545;"><?php echo number_format($stats_reportes['total'] ?? 0); ?></div>
                        <div class="stat-label">Total Reportes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" style="color: #F6A623;"><?php echo number_format($stats_reportes['pendientes'] ?? 0); ?></div>
                        <div class="stat-label">Pendientes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" style="color: #0d6efd;"><?php echo number_format($stats_reportes['en_revision'] ?? 0); ?></div>
                        <div class="stat-label">En Revisión</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" style="color: #0C9268;"><?php echo number_format($stats_reportes['resueltos'] ?? 0); ?></div>
                        <div class="stat-label">Resueltos</div>
                    </div>
                </div>
                
                <!-- Gráfico de Ventas Mensuales -->
                <div class="chart-container">
                    <h2 style="color: #0D87A8; margin-bottom: 1.5rem;">Ventas Mensuales (Últimos 12 meses)</h2>
                    <?php if (empty($ventas_mensuales)): ?>
                        <div style="height: 300px; background: #f8f9fa; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #666;">
                            <div style="text-align: center;">
                                <i class="fas fa-chart-line" style="font-size: 4rem; color: #0C9268; margin-bottom: 1rem;"></i>
                                <p>No hay datos de ventas mensuales aún</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="chart-bars">
                            <?php foreach ($ventas_mensuales as $mes): 
                                $altura = $max_ventas_mes > 0 ? ($mes['ingresos'] / $max_ventas_mes) * 100 : 0;
                            ?>
                                <div class="chart-bar" style="height: <?php echo max($altura, 5); ?>%;" title="<?php echo htmlspecialchars($mes['mes_nombre']); ?>: <?php echo formatearPrecio($mes['ingresos']); ?>">
                                    <div style="margin-bottom: 0.25rem;"><?php echo number_format($mes['total_ventas']); ?></div>
                                    <div style="font-size: 0.6rem;"><?php echo formatearPrecio($mes['ingresos']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="chart-labels">
                            <?php foreach ($ventas_mensuales as $mes): ?>
                                <div class="chart-label"><?php echo htmlspecialchars($mes['mes_nombre']); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Top Categorías y Vendedores -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;" id="adminReportesGrid">
                    <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                        <h2 style="color: #0D87A8; margin-bottom: 1.5rem;">Top Categorías</h2>
                        <?php if (empty($top_categorias)): ?>
                            <p style="color: #666; text-align: center; padding: 2rem;">No hay datos de ventas por categoría aún</p>
                        <?php else: ?>
                            <?php foreach($top_categorias as $cat): 
                                $porcentaje = $max_ventas_cat > 0 ? ($cat['total_ventas'] / $max_ventas_cat) * 100 : 0;
                            ?>
                            <div style="margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span><?php echo htmlspecialchars($cat['nombre']); ?></span>
                                    <strong><?php echo (int)$cat['total_ventas']; ?> ventas</strong>
                                </div>
                                <div style="background: #f0f0f0; height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="background: linear-gradient(90deg, #0D87A8, #0C9268); height: 100%; width: <?php echo $porcentaje; ?>%;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                        <h2 style="color: #0D87A8; margin-bottom: 1.5rem;">Top Vendedores</h2>
                        <?php if (empty($top_vendedores)): ?>
                            <p style="color: #666; text-align: center; padding: 2rem;">No hay datos de vendedores aún</p>
                        <?php else: ?>
                            <?php foreach($top_vendedores as $i => $vendedor): ?>
                            <div style="display: flex; align-items: center; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid #f0f0f0;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #0D87A8, #0C9268); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                    <?php echo $i + 1; ?>
                                </div>
                                <div style="flex: 1;">
                                    <strong style="color: #0D87A8;"><?php echo htmlspecialchars($vendedor['nombre']); ?></strong>
                                    <p style="margin: 0; color: #666; font-size: 0.85rem;"><?php echo (int)$vendedor['total_ventas']; ?> ventas</p>
                                </div>
                                <strong style="color: #0C9268;"><?php echo formatearPrecio($vendedor['ingresos_totales']); ?></strong>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tabla de Reportes de Usuarios -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); margin-bottom: 2rem;">
                    <h2 style="color: #0D87A8; margin-bottom: 1.5rem;">Reportes de Usuarios</h2>
                    
                    <!-- Filtros -->
                    <div class="filtros">
                        <form method="GET" class="filtros-form">
                            <div class="filtro-group">
                                <label>Estado</label>
                                <select name="estado">
                                    <option value="todos" <?php echo $filtros['estado'] === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                    <?php foreach ($estado_nombres as $estado_key => $estado_nombre): ?>
                                        <option value="<?php echo $estado_key; ?>" <?php echo $filtros['estado'] === $estado_key ? 'selected' : ''; ?>>
                                            <?php echo $estado_nombre; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filtro-group">
                                <label>Tipo de Reporte</label>
                                <select name="tipo_reporte">
                                    <option value="todos" <?php echo $filtros['tipo_reporte'] === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                    <?php foreach ($tipo_reporte_nombres as $tipo_key => $tipo_nombre): ?>
                                        <option value="<?php echo $tipo_key; ?>" <?php echo $filtros['tipo_reporte'] === $tipo_key ? 'selected' : ''; ?>>
                                            <?php echo $tipo_nombre; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filtro-group">
                                <label>Buscar</label>
                                <input type="text" name="busqueda" placeholder="Buscar..." value="<?php echo htmlspecialchars($filtros['busqueda']); ?>">
                            </div>
                            <button type="submit" class="btn-filtrar">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                        </form>
                    </div>
                    
                    <div style="margin-bottom: 1rem; color: #666;">
                        Total: <?php echo number_format($total_reportes); ?> reporte<?php echo $total_reportes != 1 ? 's' : ''; ?>
                    </div>
                    
                    <!-- Tabla -->
                    <div class="reportes-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Reportante</th>
                                    <th>Reportado</th>
                                    <th>Tipo</th>
                                    <th>Descripción</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reportes)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 3rem; color: #666;">
                                            No hay reportes que coincidan con los filtros seleccionados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reportes as $reporte): ?>
                                        <tr>
                                            <td><strong><?php echo (int)$reporte['id']; ?></strong></td>
                                            <td>
                                                <?php if (!empty($reporte['reportante_nombre'])): ?>
                                                    <?php echo htmlspecialchars($reporte['reportante_nombre']); ?><br>
                                                    <small style="color: #666;"><?php echo htmlspecialchars($reporte['reportante_email']); ?></small>
                                                <?php else: ?>
                                                    <span style="color: #999;">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($reporte['reportado_nombre'])): ?>
                                                    <?php echo htmlspecialchars($reporte['reportado_nombre']); ?><br>
                                                    <small style="color: #666;"><?php echo htmlspecialchars($reporte['reportado_email']); ?></small>
                                                <?php elseif (!empty($reporte['producto_titulo'])): ?>
                                                    <span style="color: #0D87A8;"><?php echo htmlspecialchars($reporte['producto_titulo']); ?></span>
                                                <?php else: ?>
                                                    <span style="color: #999;">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span style="background: #e8f3ff; color: #0D87A8; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.85rem;">
                                                    <?php echo htmlspecialchars($tipo_reporte_nombres[$reporte['tipo_reporte']] ?? ucfirst($reporte['tipo_reporte'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $desc = strlen($reporte['descripcion']) > 50 ? substr($reporte['descripcion'], 0, 50) . '...' : $reporte['descripcion'];
                                                echo htmlspecialchars($desc);
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo str_replace('_', '-', $reporte['estado']); ?>">
                                                    <?php echo htmlspecialchars($estado_nombres[$reporte['estado']] ?? ucfirst($reporte['estado'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($reporte['fecha_reporte'])); ?></td>
                                            <td>
                                                <button onclick="mostrarModal(<?php echo (int)$reporte['id']; ?>)" class="btn-ver" style="cursor: pointer;">
                                                    <i class="fas fa-eye"></i> Ver
                                                </button>
                                            </td>
                                        </tr>
                                        
                                        <!-- Modal para ver/resolver reporte -->
                                        <div id="modal-<?php echo (int)$reporte['id']; ?>" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                                            <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                                                    <h3 style="color: #0D87A8; margin: 0;">Reporte #<?php echo (int)$reporte['id']; ?></h3>
                                                    <button onclick="cerrarModal(<?php echo (int)$reporte['id']; ?>)" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666;">&times;</button>
                                                </div>
                                                
                                                <div style="margin-bottom: 1rem;">
                                                    <strong>Tipo:</strong> <?php echo htmlspecialchars($tipo_reporte_nombres[$reporte['tipo_reporte']] ?? ucfirst($reporte['tipo_reporte'])); ?><br>
                                                    <strong>Estado:</strong> <?php echo htmlspecialchars($estado_nombres[$reporte['estado']] ?? ucfirst($reporte['estado'])); ?><br>
                                                    <strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($reporte['fecha_reporte'])); ?>
                                                </div>
                                                
                                                <div style="margin-bottom: 1rem;">
                                                    <strong>Reportante:</strong><br>
                                                    <?php echo htmlspecialchars($reporte['reportante_nombre'] ?? 'N/A'); ?><br>
                                                    <small style="color: #666;"><?php echo htmlspecialchars($reporte['reportante_email'] ?? ''); ?></small>
                                                </div>
                                                
                                                <?php if (!empty($reporte['reportado_nombre'])): ?>
                                                <div style="margin-bottom: 1rem;">
                                                    <strong>Reportado:</strong><br>
                                                    <?php echo htmlspecialchars($reporte['reportado_nombre']); ?><br>
                                                    <small style="color: #666;"><?php echo htmlspecialchars($reporte['reportado_email']); ?></small>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($reporte['producto_titulo'])): ?>
                                                <div style="margin-bottom: 1rem;">
                                                    <strong>Producto:</strong> <?php echo htmlspecialchars($reporte['producto_titulo']); ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div style="margin-bottom: 1rem;">
                                                    <strong>Descripción:</strong><br>
                                                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-top: 0.5rem;">
                                                        <?php echo nl2br(htmlspecialchars($reporte['descripcion'])); ?>
                                                    </div>
                                                </div>
                                                
                                                <?php if (!empty($reporte['evidencia_url'])): ?>
                                                <div style="margin-bottom: 1rem;">
                                                    <strong>Evidencia:</strong><br>
                                                    <a href="<?php echo htmlspecialchars($reporte['evidencia_url']); ?>" target="_blank" style="color: #0C9268;">Ver evidencia</a>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($reporte['resolucion'])): ?>
                                                <div style="margin-bottom: 1rem;">
                                                    <strong>Resolución:</strong><br>
                                                    <div style="background: #e7f3ff; padding: 1rem; border-radius: 8px; margin-top: 0.5rem;">
                                                        <?php echo nl2br(htmlspecialchars($reporte['resolucion'])); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($reporte['admin_revisor_nombre'])): ?>
                                                <div style="margin-bottom: 1rem;">
                                                    <strong>Revisado por:</strong> <?php echo htmlspecialchars($reporte['admin_revisor_nombre']); ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($reporte['estado'] !== 'resuelto' && $reporte['estado'] !== 'rechazado'): ?>
                                                <form method="POST" style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #f0f0f0;">
                                                    <input type="hidden" name="accion" value="resolver">
                                                    <input type="hidden" name="reporte_id" value="<?php echo (int)$reporte['id']; ?>">
                                                    
                                                    <div style="margin-bottom: 1rem;">
                                                        <label style="display: block; margin-bottom: 0.5rem; color: #0D87A8; font-weight: 600;">Cambiar Estado:</label>
                                                        <select name="estado" required style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                                                            <option value="en_revision" <?php echo $reporte['estado'] === 'en_revision' ? 'selected' : ''; ?>>En Revisión</option>
                                                            <option value="resuelto">Resuelto</option>
                                                            <option value="rechazado">Rechazado</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div style="margin-bottom: 1rem;">
                                                        <label style="display: block; margin-bottom: 0.5rem; color: #0D87A8; font-weight: 600;">Resolución (opcional):</label>
                                                        <textarea name="resolucion" rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;" placeholder="Escribe la resolución del reporte..."><?php echo htmlspecialchars($reporte['resolucion'] ?? ''); ?></textarea>
                                                    </div>
                                                    
                                                    <div style="display: flex; gap: 1rem;">
                                                        <button type="submit" style="background: #0C9268; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                                            <i class="fas fa-check"></i> Guardar
                                                        </button>
                                                        <button type="button" onclick="cerrarModal(<?php echo (int)$reporte['id']; ?>)" style="background: #6c757d; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; cursor: pointer;">
                                                            Cancelar
                                                        </button>
                                                    </div>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'components/footer.php'; ?>
    <script>
        function mostrarModal(id) {
            document.getElementById('modal-' + id).style.display = 'flex';
        }
        
        function cerrarModal(id) {
            document.getElementById('modal-' + id).style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.style.display = 'none';
            }
        });
    </script>
    <style>
    /* Responsividad para admin reportes */
    @media (max-width: 968px) {
        #adminReportesLayout {
            grid-template-columns: 1fr !important;
        }
        
        #adminReportesGrid {
            grid-template-columns: 1fr !important;
        }
        
        .chart-bars {
            flex-wrap: wrap;
            height: auto;
        }
        
        .chart-bar {
            min-width: 60px;
            height: 100px !important;
        }
    }
    
    @media (max-width: 640px) {
        .admin-container {
            padding: 1rem !important;
        }
        
        .stat-card .stat-value {
            font-size: 1.75rem !important;
        }
        
        table {
            font-size: 0.85rem;
        }
        
        table th,
        table td {
            padding: 0.75rem 0.5rem !important;
        }
    }
    </style>
    <script src="assets/js/main.js"></script>
</body>
</html>
