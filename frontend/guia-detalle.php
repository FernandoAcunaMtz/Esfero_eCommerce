<?php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/sanitize.php';
session_start();

// Obtener ID de la guía
$guia_id = null;
if (isset($_GET['id']) && $_GET['id'] !== '') {
    $guia_id_val = sanitize_int($_GET['id'], 1);
    if ($guia_id_val !== false) {
        $guia_id = $guia_id_val;
    }
}

// Si no hay ID válido, redirigir a guias.php
if (!$guia_id) {
    header('Location: guias.php');
    exit;
}

// Obtener la guía de la base de datos
$guia = null;
try {
    global $pdo;
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM guias WHERE id = ? AND activo = 1");
        $stmt->execute([$guia_id]);
        $guia = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error al obtener guía: " . $e->getMessage());
}

// Si no se encontró la guía, redirigir
if (!$guia || empty($guia)) {
    header('Location: guias.php');
    exit;
}

// Incrementar contador de vistas
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("UPDATE guias SET vistas = vistas + 1 WHERE id = ?");
        $stmt->execute([$guia_id]);
    }
} catch (PDOException $e) {
    error_log("Error al actualizar vistas: " . $e->getMessage());
}

// Preparar datos para mostrar
$titulo = htmlspecialchars($guia['titulo']);
$categoria = strtolower($guia['categoria'] ?? 'general');
$contenido = $guia['contenido'] ?? '';
$descripcion_corta = $guia['descripcion_corta'] ?? '';
$imagen_url = !empty($guia['imagen_url']) ? $guia['imagen_url'] : null;

// Colores de categoría
$categoria_bg = [
    'comprar' => '#e7f3ff',
    'vender' => '#fff3cd',
    'envios' => '#e7f3ff',
    'seguridad' => '#f0f9f6',
    'pagos' => '#fff3cd',
    'general' => '#f0f9f6'
];
$categoria_color = [
    'comprar' => '#0D87A8',
    'vender' => '#856404',
    'envios' => '#0D87A8',
    'seguridad' => '#0C9268',
    'pagos' => '#856404',
    'general' => '#0C9268'
];
$categoria_nombres = [
    'comprar' => 'Comprar',
    'vender' => 'Vender',
    'envios' => 'Envíos',
    'seguridad' => 'Seguridad',
    'pagos' => 'Pagos',
    'general' => 'General'
];

$bg_color = $categoria_bg[$categoria] ?? '#f0f9f6';
$text_color = $categoria_color[$categoria] ?? '#0C9268';
$categoria_nombre = $categoria_nombres[$categoria] ?? 'General';

// Placeholder para imagen
$placeholder_svg = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iODAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjQiIGZpbGw9IiM5OTk5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5HdcOtYTwvdGV4dD48L3N2Zz4=';
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
    <title><?php echo $titulo; ?> - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Hero -->
    <section class="page-hero" style="background: linear-gradient(135deg, #044E65 0%, #0D87A8 50%, #0C9268 100%); padding: 4rem 0 2rem; text-align: center; color: white;">
        <div class="container">
            <a href="guias.php" style="color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; opacity: 0.9;" onmouseover="this.style.opacity='1';" onmouseout="this.style.opacity='0.9';">
                <i class="fas fa-arrow-left"></i> Volver a Guías
            </a>
            <span style="display: inline-block; background: rgba(255,255,255,0.2); color: white; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.9rem; margin-bottom: 1rem; font-weight: 600;">
                <?php echo htmlspecialchars($categoria_nombre); ?>
            </span>
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem; font-weight: 800;"><?php echo $titulo; ?></h1>
            <?php if (!empty($descripcion_corta)): ?>
            <p style="font-size: 1.2rem; opacity: 0.9; max-width: 800px; margin: 0 auto;"><?php echo htmlspecialchars($descripcion_corta); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Contenido de la Guía -->
    <section class="sections" style="padding: 3rem 0;">
        <div class="container" style="max-width: 900px;">
            <?php if ($imagen_url): ?>
            <div style="margin-bottom: 2rem; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <img src="<?php echo htmlspecialchars($imagen_url); ?>" alt="<?php echo $titulo; ?>" style="width: 100%; height: auto; display: block;" onerror="this.onerror=null; this.src='<?php echo $placeholder_svg; ?>';">
            </div>
            <?php endif; ?>
            
            <article style="background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); line-height: 1.8;">
                <div style="color: #333; font-size: 1.1rem;">
                    <?php 
                    // Mostrar el contenido HTML de la guía
                    // El contenido ya viene con HTML desde la base de datos
                    echo $contenido; 
                    ?>
                </div>
            </article>
            
            <!-- Información adicional -->
            <div style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <span style="color: #666; font-size: 0.9rem;">
                        <i class="fas fa-eye" style="color: #0C9268;"></i> <?php echo (int)($guia['vistas'] ?? 0); ?> vistas
                    </span>
                    <?php if (isset($guia['fecha_publicacion'])): ?>
                    <span style="color: #666; font-size: 0.9rem;">
                        <i class="fas fa-calendar" style="color: #0C9268;"></i> 
                        <?php echo date('d/m/Y', strtotime($guia['fecha_publicacion'])); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <a href="guias.php" style="color: #0C9268; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.color='#008a5e';" onmouseout="this.style.color='#0C9268';">
                    <i class="fas fa-arrow-left"></i> Ver todas las guías
                </a>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

