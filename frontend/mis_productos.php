<?php
session_start();
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/db_connection.php';

// Bloquear gestión de productos para administradores
if (is_admin()) {
    $_SESSION['error_message'] = 'Los administradores no pueden gestionar productos como vendedores.';
    header('Location: admin_dashboard.php');
    exit;
}

require_vendedor('activar_vendedor.php');

// Obtener filtro de estado
$filtro_estado = htmlspecialchars(trim($_GET['estado'] ?? 'todos'), ENT_QUOTES, 'UTF-8');
if (!in_array($filtro_estado, ['todos', 'activo', 'pausado', 'vendido'])) {
    $filtro_estado = 'todos';
}

// Obtener productos del vendedor
$user = get_session_user();
$vendedor_id = $user['id'] ?? null;

$productos = [];
if ($vendedor_id) {
    $productos = getProductosVendedor($vendedor_id, $filtro_estado === 'todos' ? null : $filtro_estado);
}

// Contar productos por estado
$total_todos = $vendedor_id ? contarProductosVendedor($vendedor_id, null) : 0;
$total_activos = $vendedor_id ? contarProductosVendedor($vendedor_id, 'activo') : 0;
$total_pausados = $vendedor_id ? contarProductosVendedor($vendedor_id, 'pausado') : 0;
$total_vendidos = $vendedor_id ? contarProductosVendedor($vendedor_id, 'vendido') : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0D87A8">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Mis Productos - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    <div style="width: 100%; margin: 100px auto 50px; padding: 2rem;">
        <h1 style="color: #0D87A8; margin-bottom: 2rem; font-size: clamp(1.5rem, 4vw, 2.5rem);">
            <i class="fas fa-box"></i> Mis Productos
        </h1>
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem;" id="misProductosLayout">
            <?php include 'components/sidebar_vendedor.php'; ?>
            <div style="background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);">
                <?php if (isset($_SESSION['success_message'])): ?>
                <div style="background: #e8fff6; color: #0C9268; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                    <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                    <?php unset($_SESSION['success_message']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                <div style="background: #fdecea; color: #b71c1c; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
                <?php endif; ?>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 style="color: #0D87A8; font-size: clamp(1.25rem, 3vw, 1.5rem);">Productos (<?php echo $total_todos; ?>)</h2>
                    <a href="publicar_producto.php" class="cta-button" style="white-space: nowrap;">
                        <i class="fas fa-plus"></i> Nuevo Producto
                    </a>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
                    <a href="?estado=todos" class="cta-button" style="background: <?php echo $filtro_estado === 'todos' ? '#0D87A8' : 'white'; ?>; color: <?php echo $filtro_estado === 'todos' ? 'white' : '#0D87A8'; ?>; border: 2px solid #0D87A8; font-size: clamp(0.85rem, 2vw, 1rem); text-decoration: none;">Todos (<?php echo $total_todos; ?>)</a>
                    <a href="?estado=activo" class="cta-button" style="background: <?php echo $filtro_estado === 'activo' ? '#0D87A8' : 'white'; ?>; color: <?php echo $filtro_estado === 'activo' ? 'white' : '#0D87A8'; ?>; border: 2px solid #0D87A8; font-size: clamp(0.85rem, 2vw, 1rem); text-decoration: none;">Activos (<?php echo $total_activos; ?>)</a>
                    <a href="?estado=pausado" class="cta-button" style="background: <?php echo $filtro_estado === 'pausado' ? '#0D87A8' : 'white'; ?>; color: <?php echo $filtro_estado === 'pausado' ? 'white' : '#0D87A8'; ?>; border: 2px solid #0D87A8; font-size: clamp(0.85rem, 2vw, 1rem); text-decoration: none;">Pausados (<?php echo $total_pausados; ?>)</a>
                    <?php if ($total_vendidos > 0): ?>
                    <a href="?estado=vendido" class="cta-button" style="background: <?php echo $filtro_estado === 'vendido' ? '#0D87A8' : 'white'; ?>; color: <?php echo $filtro_estado === 'vendido' ? 'white' : '#0D87A8'; ?>; border: 2px solid #0D87A8; font-size: clamp(0.85rem, 2vw, 1rem); text-decoration: none;">Vendidos (<?php echo $total_vendidos; ?>)</a>
                    <?php endif; ?>
                </div>
                
                <div style="display: grid; gap: 1.5rem;">
                    <?php if (empty($productos)): ?>
                        <div style="text-align: center; padding: 3rem; color: #666;">
                            <i class="fas fa-box-open" style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;"></i>
                            <p style="font-size: 1.2rem; margin-bottom: 1rem;">No tienes productos <?php echo $filtro_estado !== 'todos' ? $filtro_estado . 's' : ''; ?> aún</p>
                            <a href="publicar_producto.php" class="cta-button" style="display: inline-block;">Publicar mi primer producto</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($productos as $producto): 
                            $imagen = $producto['imagen_principal'] ?: 'https://placehold.co/400x400?text=Sin+imagen';
                            $precio_formateado = formatearPrecio($producto['precio'], $producto['moneda']);
                            $estado_texto = getEstadoTexto($producto['estado_producto']);
                            $estado_badge = $producto['activo'] == 1 && $producto['vendido'] == 0 ? 'Activo' : ($producto['vendido'] == 1 ? 'Vendido' : 'Pausado');
                            $estado_color = $producto['activo'] == 1 && $producto['vendido'] == 0 ? '#0C9268' : ($producto['vendido'] == 1 ? '#F6A623' : '#dc3545');
                            $estado_bg = $producto['activo'] == 1 && $producto['vendido'] == 0 ? '#e8fff6' : ($producto['vendido'] == 1 ? '#fff8e6' : '#ffe6e6');
                        ?>
                        <div style="display: grid; grid-template-columns: 120px 1fr auto; gap: 1.5rem; padding: 1.5rem; border: 1px solid #f0f0f0; border-radius: 10px;" class="producto-item">
                            <div style="width: 120px; height: 120px; min-width: 120px; background: linear-gradient(135deg, #0D87A8, #0C9268); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; overflow: hidden;">
                                <?php if ($imagen && strpos($imagen, 'placeholder') === false): ?>
                                    <img src="<?php echo htmlspecialchars($imagen); ?>" alt="<?php echo htmlspecialchars($producto['titulo']); ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.innerHTML='<i class=\'fas fa-box\'></i>'">
                                <?php else: ?>
                                    <i class="fas fa-box"></i>
                                <?php endif; ?>
                            </div>
                            <div style="min-width: 0;">
                                <strong style="color: #0D87A8; font-size: clamp(0.9rem, 2vw, 1.1rem); display: block; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($producto['titulo']); ?></strong>
                                <p style="color: #666; margin: 0.5rem 0; font-size: clamp(0.8rem, 2vw, 0.9rem);">Estado: <?php echo htmlspecialchars($estado_texto); ?> • Stock: <?php echo (int)$producto['stock']; ?></p>
                                <div style="font-size: clamp(1rem, 2.5vw, 1.3rem); font-weight: bold; color: #0C9268; margin: 0.5rem 0;"><?php echo $precio_formateado; ?></div>
                                <span style="background: <?php echo $estado_bg; ?>; color: <?php echo $estado_color; ?>; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: clamp(0.75rem, 2vw, 0.85rem);"><?php echo $estado_badge; ?></span>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.5rem; flex-shrink: 0;" class="producto-actions">
                                <button class="cta-button" onclick="window.location.href='editar_producto.php?id=<?php echo $producto['id']; ?>'" style="padding: 0.5rem 1rem; font-size: clamp(0.8rem, 2vw, 0.9rem);">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <?php if ($producto['activo'] == 1 && $producto['vendido'] == 0): ?>
                                <button class="cta-button" onclick="pausarProducto(<?php echo $producto['id']; ?>)" style="background: white; color: #0D87A8; border: 2px solid #0D87A8; padding: 0.5rem 1rem; font-size: clamp(0.8rem, 2vw, 0.9rem);">
                                    <i class="fas fa-pause"></i> Pausar
                                </button>
                                <?php elseif ($producto['activo'] == 0): ?>
                                <button class="cta-button" onclick="activarProducto(<?php echo $producto['id']; ?>)" style="background: #0C9268; padding: 0.5rem 1rem; font-size: clamp(0.8rem, 2vw, 0.9rem);">
                                    <i class="fas fa-play"></i> Activar
                                </button>
                                <?php endif; ?>
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
    <script>
    function pausarProducto(productoId) {
        if (!confirm('¿Deseas pausar este producto? No se mostrará en el catálogo hasta que lo actives nuevamente.')) {
            return;
        }
        // Aquí iría la llamada AJAX para pausar el producto
        alert('Funcionalidad de pausar producto pendiente de implementar');
    }
    
    function activarProducto(productoId) {
        // Aquí iría la llamada AJAX para activar el producto
        alert('Funcionalidad de activar producto pendiente de implementar');
    }
    </script>
    <style>
    /* Responsividad para mis productos */
    @media (max-width: 968px) {
        #misProductosLayout {
            grid-template-columns: 1fr !important;
        }
        
        .producto-item {
            grid-template-columns: 1fr !important;
            text-align: center;
        }
        
        .producto-item > div:first-child {
            margin: 0 auto;
        }
        
        .producto-item > div:last-child {
            display: flex;
            flex-direction: row;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
    }
    
    @media (max-width: 640px) {
        div[style*="width: 100%"] {
            padding: 1rem !important;
        }
        
        .producto-item {
            padding: 1rem !important;
        }
    }
    </style>
</body>
</html>

