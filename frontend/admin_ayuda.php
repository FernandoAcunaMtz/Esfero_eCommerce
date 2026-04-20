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

// Filtros
$filtro_estado = sanitize_input($_GET['estado'] ?? 'todos');
$filtro_categoria = sanitize_input($_GET['categoria'] ?? 'todos');
$filtro_prioridad = sanitize_input($_GET['prioridad'] ?? 'todos');

// Construir query con filtros
$query = "SELECT s.*, u.nombre as usuario_nombre, u.email as usuario_email,
          (SELECT COUNT(*) FROM ayuda_respuestas WHERE solicitud_id = s.id) as total_respuestas
          FROM ayuda_solicitudes s
          LEFT JOIN usuarios u ON s.usuario_id = u.id
          WHERE 1=1";

$params = [];

if ($filtro_estado !== 'todos') {
    $query .= " AND s.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_categoria !== 'todos') {
    $query .= " AND s.categoria = ?";
    $params[] = $filtro_categoria;
}

if ($filtro_prioridad !== 'todos') {
    $query .= " AND s.prioridad = ?";
    $params[] = $filtro_prioridad;
}

$query .= " ORDER BY 
    CASE s.prioridad 
        WHEN 'urgente' THEN 1 
        WHEN 'alta' THEN 2 
        WHEN 'normal' THEN 3 
        WHEN 'baja' THEN 4 
    END,
    s.fecha_creacion DESC";

// Obtener solicitudes
$solicitudes = [];
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error al obtener solicitudes: " . $e->getMessage());
}

// Estadísticas
$estadisticas = [
    'pendientes' => 0,
    'en_revision' => 0,
    'respondidas' => 0,
    'total' => 0
];

try {
    if (isset($pdo)) {
        $stmt = $pdo->query("
            SELECT estado, COUNT(*) as total 
            FROM ayuda_solicitudes 
            GROUP BY estado
        ");
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($stats as $stat) {
            $estadisticas['total'] += $stat['total'];
            if ($stat['estado'] === 'pendiente') {
                $estadisticas['pendientes'] = $stat['total'];
            } elseif ($stat['estado'] === 'en_revision') {
                $estadisticas['en_revision'] = $stat['total'];
            } elseif ($stat['estado'] === 'respondida') {
                $estadisticas['respondidas'] = $stat['total'];
            }
        }
    }
} catch (Exception $e) {
    error_log("Error al obtener estadísticas: " . $e->getMessage());
}

// Nombres de estados y categorías
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
    <title>Gestión de Solicitudes de Ayuda - Admin</title>
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
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin: 0 0 0.5rem 0;
        }
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #0D87A8;
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
        .solicitudes-table {
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
        .badge-respondida { background: #d1e7dd; color: #0f5132; }
        .badge-cerrada { background: #f8d7da; color: #842029; }
        .badge-resuelta { background: #d1e7dd; color: #0f5132; }
        .badge-urgente { background: #dc3545; color: white; }
        .badge-alta { background: #fd7e14; color: white; }
        .badge-normal { background: #0d6efd; color: white; }
        .badge-baja { background: #6c757d; color: white; }
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
                        <i class="fas fa-headset"></i> Solicitudes de Ayuda
                    </h1>
                </div>
                
                <?php if (isset($_GET['error'])): ?>
                    <div style="padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['success'])): ?>
                    <div style="padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; background: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Estadísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Solicitudes</h3>
                        <div class="stat-value"><?php echo $estadisticas['total']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pendientes</h3>
                        <div class="stat-value"><?php echo $estadisticas['pendientes']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>En Revisión</h3>
                        <div class="stat-value"><?php echo $estadisticas['en_revision']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Respondidas</h3>
                        <div class="stat-value"><?php echo $estadisticas['respondidas']; ?></div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="filtros">
                    <form method="GET" class="filtros-form">
                        <div class="filtro-group">
                            <label>Estado</label>
                            <select name="estado">
                                <option value="todos" <?php echo $filtro_estado === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="en_revision" <?php echo $filtro_estado === 'en_revision' ? 'selected' : ''; ?>>En Revisión</option>
                                <option value="respondida" <?php echo $filtro_estado === 'respondida' ? 'selected' : ''; ?>>Respondida</option>
                                <option value="cerrada" <?php echo $filtro_estado === 'cerrada' ? 'selected' : ''; ?>>Cerrada</option>
                                <option value="resuelta" <?php echo $filtro_estado === 'resuelta' ? 'selected' : ''; ?>>Resuelta</option>
                            </select>
                        </div>
                        <div class="filtro-group">
                            <label>Categoría</label>
                            <select name="categoria">
                                <option value="todos" <?php echo $filtro_categoria === 'todos' ? 'selected' : ''; ?>>Todas</option>
                                <?php foreach ($categoria_nombres as $cat_key => $cat_nombre): ?>
                                    <option value="<?php echo $cat_key; ?>" <?php echo $filtro_categoria === $cat_key ? 'selected' : ''; ?>>
                                        <?php echo $cat_nombre; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filtro-group">
                            <label>Prioridad</label>
                            <select name="prioridad">
                                <option value="todos" <?php echo $filtro_prioridad === 'todos' ? 'selected' : ''; ?>>Todas</option>
                                <?php foreach ($prioridad_nombres as $pri_key => $pri_nombre): ?>
                                    <option value="<?php echo $pri_key; ?>" <?php echo $filtro_prioridad === $pri_key ? 'selected' : ''; ?>>
                                        <?php echo $pri_nombre; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-filtrar">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                    </form>
                </div>
                
                <!-- Tabla de Solicitudes -->
                <div class="solicitudes-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Solicitante</th>
                                <th>Asunto</th>
                                <th>Categoría</th>
                                <th>Prioridad</th>
                                <th>Estado</th>
                                <th>Respuestas</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($solicitudes)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 3rem; color: #666;">
                                        No hay solicitudes que coincidan con los filtros seleccionados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($solicitudes as $solicitud): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($solicitud['numero_ticket']); ?></strong></td>
                                        <td>
                                            <?php if ($solicitud['usuario_nombre']): ?>
                                                <?php echo htmlspecialchars($solicitud['usuario_nombre']); ?><br>
                                                <small style="color: #666;"><?php echo htmlspecialchars($solicitud['usuario_email']); ?></small>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($solicitud['nombre']); ?><br>
                                                <small style="color: #666;"><?php echo htmlspecialchars($solicitud['email']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php 
                                            $asunto = $solicitud['asunto'];
                                            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                                                echo htmlspecialchars(mb_strlen($asunto) > 50 ? mb_substr($asunto, 0, 50) . '...' : $asunto);
                                            } else {
                                                echo htmlspecialchars(strlen($asunto) > 50 ? substr($asunto, 0, 50) . '...' : $asunto);
                                            }
                                        ?></td>
                                        <td><?php echo $categoria_nombres[$solicitud['categoria']] ?? ucfirst($solicitud['categoria']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $solicitud['prioridad']; ?>">
                                                <?php echo $prioridad_nombres[$solicitud['prioridad']] ?? ucfirst($solicitud['prioridad']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo str_replace('_', '-', $solicitud['estado']); ?>">
                                                <?php echo $estado_nombres[$solicitud['estado']] ?? ucfirst($solicitud['estado']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo (int)$solicitud['total_respuestas']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_creacion'])); ?></td>
                                        <td>
                                            <a href="admin_ayuda_detalle.php?id=<?php echo (int)$solicitud['id']; ?>" class="btn-ver">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

