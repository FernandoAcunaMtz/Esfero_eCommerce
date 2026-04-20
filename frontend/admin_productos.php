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

// Función simple para sanitizar HTML
function sanitize_html($value) {
    if ($value === null || $value === '') {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Procesar acciones
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $producto_id = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
    
    if ($_POST['accion'] === 'aprobar' && $producto_id > 0) {
        if (aprobarProducto($producto_id)) {
            $mensaje = 'Producto aprobado exitosamente';
            $tipo_mensaje = 'success';
        } else {
            $mensaje = 'Error al aprobar el producto';
            $tipo_mensaje = 'error';
        }
    } elseif ($_POST['accion'] === 'rechazar' && $producto_id > 0) {
        $razon = sanitize_input($_POST['razon'] ?? '');
        if (rechazarProducto($producto_id, $razon)) {
            $mensaje = 'Producto rechazado exitosamente';
            $tipo_mensaje = 'success';
        } else {
            $mensaje = 'Error al rechazar el producto';
            $tipo_mensaje = 'error';
        }
    }
}

// Obtener filtro
$filtro = sanitize_html($_GET['filtro'] ?? 'pendientes');
if (!in_array($filtro, ['todos', 'pendientes', 'reportados'])) {
    $filtro = 'pendientes';
}

// Obtener productos según filtro
if ($filtro === 'pendientes') {
    $productos = getProductosPendientesModeracion();
    $total = contarProductosPendientes();
} elseif ($filtro === 'reportados') {
    $productos = getProductosReportados();
    $total = contarProductosReportados();
} else {
    // Todos los productos (activos e inactivos)
    global $pdo;
    $stmt = $pdo->query("SELECT p.*, u.nombre as vendedor_nombre, 
                        (SELECT url_imagen FROM imagenes_productos WHERE producto_id = p.id AND es_principal = 1 LIMIT 1) as imagen_principal
                        FROM productos p
                        LEFT JOIN usuarios u ON p.vendedor_id = u.id
                        ORDER BY p.fecha_publicacion DESC
                        LIMIT 50");
    $productos = $stmt->fetchAll();
    $total = $pdo->query("SELECT COUNT(*) as total FROM productos")->fetch()['total'];
}

$total_pendientes = contarProductosPendientes();
$total_reportados = contarProductosReportados();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Moderación de Productos - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    <div style="width: 100%; margin: 100px auto 50px; padding: 2rem;">
        <h1 style="color: #dc3545; margin-bottom: 2rem; font-size: clamp(1.5rem, 4vw, 2.5rem);">
            <i class="fas fa-box"></i> Moderación de Productos
        </h1>
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem;" id="adminProductosLayout">
            <?php include 'components/sidebar_admin.php'; ?>
            <div>
                <?php if ($mensaje): ?>
                    <div style="padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; background: <?php echo $tipo_mensaje === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $tipo_mensaje === 'success' ? '#155724' : '#721c24'; ?>; border: 1px solid <?php echo $tipo_mensaje === 'success' ? '#c3e6cb' : '#f5c6cb'; ?>;">
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>
                
                <div style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
                    <a href="?filtro=todos" class="cta-button" style="background: <?php echo $filtro === 'todos' ? '#0D87A8' : 'white'; ?>; color: <?php echo $filtro === 'todos' ? 'white' : '#0D87A8'; ?>; border: 2px solid #0D87A8; text-decoration: none;">Todos (<?php echo $total; ?>)</a>
                    <a href="?filtro=pendientes" class="cta-button" style="background: <?php echo $filtro === 'pendientes' ? '#F6A623' : 'white'; ?>; color: <?php echo $filtro === 'pendientes' ? 'white' : '#F6A623'; ?>; border: 2px solid #F6A623; text-decoration: none;">Pendientes (<?php echo $total_pendientes; ?>)</a>
                    <a href="?filtro=reportados" class="cta-button" style="background: <?php echo $filtro === 'reportados' ? '#dc3545' : 'white'; ?>; color: <?php echo $filtro === 'reportados' ? 'white' : '#dc3545'; ?>; border: 2px solid #dc3545; text-decoration: none;">Reportados (<?php echo $total_reportados; ?>)</a>
                </div>
                
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                    <?php if (empty($productos)): ?>
                        <div style="text-align: center; padding: 3rem; color: #666;">
                            <i class="fas fa-check-circle" style="font-size: 4rem; color: #0C9268; margin-bottom: 1rem;"></i>
                            <p style="font-size: 1.2rem; margin-bottom: 1rem;">No hay productos <?php echo $filtro === 'pendientes' ? 'pendientes' : ($filtro === 'reportados' ? 'reportados' : ''); ?> en este momento</p>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; gap: 1.5rem;">
                            <?php foreach ($productos as $producto): 
                                $imagen = $producto['imagen_principal'] ?: 'https://placehold.co/400x400?text=Sin+imagen';
                                $precio_formateado = formatearPrecio($producto['precio'], $producto['moneda']);
                                $fecha_publicacion = new DateTime($producto['fecha_publicacion']);
                                $dias_publicado = $fecha_publicacion->diff(new DateTime())->days;
                            ?>
                            <div style="display: grid; grid-template-columns: 120px 1fr auto; gap: 1.5rem; padding: 1.5rem; border: 1px solid #f0f0f0; border-radius: 10px;" class="admin-producto-item">
                                <div style="width: 120px; height: 120px; min-width: 120px; background: linear-gradient(135deg, #0D87A8, #0C9268); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; overflow: hidden;">
                                    <?php if ($imagen && strpos($imagen, 'placeholder') === false): ?>
                                        <img src="<?php echo htmlspecialchars($imagen); ?>" alt="<?php echo htmlspecialchars($producto['titulo']); ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.parentElement.innerHTML='<i class=\'fas fa-box\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-box"></i>
                                    <?php endif; ?>
                                </div>
                                <div style="min-width: 0;">
                                    <strong style="color: #0D87A8; font-size: clamp(0.9rem, 2vw, 1.1rem); display: block; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($producto['titulo']); ?></strong>
                                    <p style="color: #666; margin: 0.5rem 0; font-size: clamp(0.8rem, 2vw, 0.9rem);">Vendedor: <?php echo htmlspecialchars($producto['vendedor_nombre'] ?? 'Desconocido'); ?> • Publicado hace <?php echo $dias_publicado; ?> día<?php echo $dias_publicado != 1 ? 's' : ''; ?></p>
                                    <div style="font-size: clamp(1rem, 2.5vw, 1.3rem); font-weight: bold; color: #0C9268; margin: 0.5rem 0;"><?php echo $precio_formateado; ?></div>
                                    <span style="background: <?php echo $filtro === 'pendientes' ? '#fff8e6' : '#ffe6e6'; ?>; color: <?php echo $filtro === 'pendientes' ? '#F6A623' : '#dc3545'; ?>; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: clamp(0.75rem, 2vw, 0.85rem);">
                                        <?php echo $filtro === 'pendientes' ? 'Pendiente de Revisión' : ($filtro === 'reportados' ? 'Reportado (' . ($producto['total_reportes'] ?? 0) . ')' : 'Producto'); ?>
                                    </span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem; flex-shrink: 0;" class="admin-producto-actions">
                                    <?php if ($filtro === 'pendientes'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Deseas aprobar este producto? Se activará y será visible en el catálogo.');">
                                        <input type="hidden" name="accion" value="aprobar">
                                        <input type="hidden" name="producto_id" value="<?php echo $producto['id']; ?>">
                                        <button type="submit" class="cta-button" style="padding: 0.5rem 1rem; background: #0C9268; font-size: clamp(0.8rem, 2vw, 0.9rem); white-space: nowrap; width: 100%; border: none; color: white; cursor: pointer;">
                                            <i class="fas fa-check"></i> Aprobar
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que deseas rechazar este producto? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="accion" value="rechazar">
                                        <input type="hidden" name="producto_id" value="<?php echo $producto['id']; ?>">
                                        <input type="hidden" name="razon" value="Rechazado por administrador">
                                        <button type="submit" class="cta-button" style="padding: 0.5rem 1rem; background: #dc3545; font-size: clamp(0.8rem, 2vw, 0.9rem); white-space: nowrap; width: 100%; border: none; color: white; cursor: pointer;">
                                            <i class="fas fa-times"></i> Rechazar
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="producto.php?id=<?php echo $producto['id']; ?>" class="cta-button" style="padding: 0.5rem 1rem; background: white; color: #0D87A8; border: 2px solid #0D87A8; font-size: clamp(0.8rem, 2vw, 0.9rem); white-space: nowrap; text-decoration: none; text-align: center; display: block;">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <style>
    /* Responsividad para admin productos */
    @media (max-width: 968px) {
        #adminProductosLayout {
            grid-template-columns: 1fr !important;
        }
        
        .admin-producto-item {
            grid-template-columns: 1fr !important;
            text-align: center;
        }
        
        .admin-producto-item > div:first-child {
            margin: 0 auto;
        }
        
        .admin-producto-actions {
            flex-direction: row !important;
            justify-content: center;
            margin-top: 1rem;
        }
    }
    
    @media (max-width: 640px) {
        div[style*="width: 100%"] {
            padding: 1rem !important;
        }
        
        .admin-producto-actions {
            flex-direction: column !important;
        }
    }
    </style>
</body>
</html>

