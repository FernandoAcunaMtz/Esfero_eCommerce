<?php
session_start();
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/sanitize.php';
require_login();

// Obtener ID del producto desde GET
$producto_id = sanitize_int($_GET['id'] ?? null, 1);
if ($producto_id === false) {
    header('Location: mis_productos.php');
    exit;
}

// Obtener producto real de la base de datos
$producto = getProductoById($producto_id);

if (!$producto) {
    header('Location: mis_productos.php');
    exit;
}

// Verificar que el producto pertenece al usuario actual
if ($producto['vendedor_id'] != $_SESSION['user']['id']) {
    header('Location: mis_productos.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Editar Producto - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    <div style="width: 100%; margin: 100px auto 50px; padding: 2rem;">
        <h1 style="color: #0D87A8; margin-bottom: 2rem; font-size: 2.5rem;">
            <i class="fas fa-edit"></i> Editar Producto
        </h1>
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem;">
            <?php include 'components/sidebar_vendedor.php'; ?>
            <form method="POST" action="process_update_producto.php" style="background: white; padding: 2.5rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);">
                <input type="hidden" name="producto_id" value="<?php echo (int)$producto['id']; ?>">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: #0D87A8; font-weight: 600; margin-bottom: 0.5rem;">Título *</label>
                    <input type="text" name="titulo" value="<?php echo htmlspecialchars($producto['titulo']); ?>" style="width: 100%; padding: 1rem; border: 2px solid #e0e0e0; border-radius: 10px;" required>
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: #0D87A8; font-weight: 600; margin-bottom: 0.5rem;">Descripción *</label>
                    <textarea name="descripcion" rows="6" style="width: 100%; padding: 1rem; border: 2px solid #e0e0e0; border-radius: 10px;" required><?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="display: block; color: #0D87A8; font-weight: 600; margin-bottom: 0.5rem;">Precio *</label>
                        <input type="number" name="precio" value="<?php echo htmlspecialchars($producto['precio']); ?>" step="0.01" style="width: 100%; padding: 1rem; border: 2px solid #e0e0e0; border-radius: 10px;" required>
                    </div>
                    <div>
                        <label style="display: block; color: #0D87A8; font-weight: 600; margin-bottom: 0.5rem;">Stock *</label>
                        <input type="number" name="stock" value="<?php echo htmlspecialchars($producto['stock'] ?? 1); ?>" min="0" style="width: 100%; padding: 1rem; border: 2px solid #e0e0e0; border-radius: 10px;" required>
                    </div>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="cta-button" style="flex: 1;">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    <button type="button" class="cta-button" style="flex: 1; background: white; color: #0D87A8; border: 2px solid #0D87A8;" onclick="window.location.href='mis_productos.php'">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

