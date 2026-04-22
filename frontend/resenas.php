<?php
session_start();
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/db_connection.php';

// Bloquear reseñas para administradores
if (is_admin()) {
    $_SESSION['error_message'] = 'Los administradores no tienen reseñas.';
    header('Location: admin_dashboard.php');
    exit;
}

require_vendedor('activar_vendedor.php');

$user = get_session_user();
$vendedor_id = $user['id'] ?? null;

if (!$vendedor_id || !isset($pdo)) {
    $_SESSION['error_message'] = 'Error al cargar datos del vendedor.';
    header('Location: index.php');
    exit;
}

// Obtener filtro
$filtro_tipo = $_GET['tipo'] ?? 'todas';
$tipos_validos = ['todas', 'vendedor', 'producto'];
if (!in_array($filtro_tipo, $tipos_validos)) {
    $filtro_tipo = 'todas';
}

// Obtener estadísticas de calificaciones
$stats = [];

// Calificación promedio
$stmt = $pdo->prepare("
    SELECT AVG(calificacion) as promedio, COUNT(*) as total
    FROM calificaciones
    WHERE calificado_id = ? 
    AND tipo = 'vendedor'
    AND visible = 1
");
$stmt->execute([$vendedor_id]);
$calif_data = $stmt->fetch();
$stats['promedio'] = $calif_data['promedio'] ? round((float)$calif_data['promedio'], 1) : 0;
$stats['total'] = (int)$calif_data['total'];

// Distribución de calificaciones
$stmt = $pdo->prepare("
    SELECT calificacion, COUNT(*) as cantidad
    FROM calificaciones
    WHERE calificado_id = ? 
    AND tipo = 'vendedor'
    AND visible = 1
    GROUP BY calificacion
    ORDER BY calificacion DESC
");
$stmt->execute([$vendedor_id]);
$distribucion = [];
while ($row = $stmt->fetch()) {
    $distribucion[$row['calificacion']] = (int)$row['cantidad'];
}

// Obtener reseñas
$sql = "
    SELECT c.*,
           u.nombre as calificador_nombre,
           u.email as calificador_email,
           p.titulo as producto_titulo,
           p.id as producto_id,
           o.numero_orden,
           (SELECT url_imagen FROM imagenes_productos 
            WHERE producto_id = p.id AND es_principal = 1 
            LIMIT 1) as producto_imagen
    FROM calificaciones c
    LEFT JOIN usuarios u ON c.calificador_id = u.id
    LEFT JOIN productos p ON c.producto_id = p.id
    LEFT JOIN ordenes o ON c.orden_id = o.id
    WHERE c.calificado_id = ?
    AND c.visible = 1
";

$params = [$vendedor_id];

if ($filtro_tipo !== 'todas') {
    $sql .= " AND c.tipo = ?";
    $params[] = $filtro_tipo;
}

$sql .= " ORDER BY c.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resenas = $stmt->fetchAll();

// Procesar respuesta a reseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder_resena'])) {
    $resena_id = (int)($_POST['resena_id'] ?? 0);
    $respuesta = trim($_POST['respuesta'] ?? '');
    
    if ($resena_id > 0 && !empty($respuesta)) {
        try {
            // Verificar que la reseña pertenece al vendedor
            $stmt = $pdo->prepare("
                SELECT * FROM calificaciones 
                WHERE id = ? 
                AND calificado_id = ?
            ");
            $stmt->execute([$resena_id, $vendedor_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("
                    UPDATE calificaciones 
                    SET respuesta = ?, fecha_respuesta = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$respuesta, $resena_id]);
                $_SESSION['success_message'] = 'Respuesta enviada exitosamente.';
            }
        } catch (PDOException $e) {
            error_log("Error al responder reseña: " . $e->getMessage());
            $_SESSION['error_message'] = 'Error al enviar la respuesta.';
        }
    }
    header('Location: resenas.php' . ($filtro_tipo !== 'todas' ? '?tipo=' . urlencode($filtro_tipo) : ''));
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
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png">
    <script>if('serviceWorker'in navigator)navigator.serviceWorker.register('/sw.js');</script>
    <title>Reseñas - Esfero</title>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .resenas-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
        }
        
        .resenas-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
        }
        
        .stats-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .calificacion-grande {
            font-size: 4rem;
            font-weight: bold;
            color: #0D87A8;
            margin-bottom: 0.5rem;
        }
        
        .estrellas {
            font-size: 1.5rem;
            color: #F6A623;
            margin-bottom: 1rem;
        }
        
        .distribucion-calificaciones {
            margin-top: 2rem;
        }
        
        .barra-calificacion {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .barra-calificacion-label {
            width: 80px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .barra-calificacion-progreso {
            flex: 1;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .barra-calificacion-fill {
            height: 100%;
            background: linear-gradient(90deg, #F6A623, #f37d00);
            transition: width 0.3s ease;
        }
        
        .barra-calificacion-cantidad {
            width: 40px;
            text-align: right;
            font-size: 0.85rem;
            color: #666;
        }
        
        .filtros {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .resena-item {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .resena-header {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: flex-start;
        }
        
        .resena-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0D87A8, #0C9268);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .resena-info {
            flex: 1;
        }
        
        .resena-nombre {
            font-weight: bold;
            color: #0D87A8;
            margin-bottom: 0.25rem;
        }
        
        .resena-fecha {
            font-size: 0.85rem;
            color: #999;
        }
        
        .resena-estrellas {
            color: #F6A623;
            margin: 0.5rem 0;
        }
        
        .resena-titulo {
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .resena-comentario {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .resena-producto {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .resena-producto-img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: linear-gradient(135deg, #0D87A8, #0C9268);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .resena-respuesta {
            background: #e8f4f8;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
            border-left: 4px solid #0D87A8;
        }
        
        .resena-respuesta-header {
            font-weight: bold;
            color: #0D87A8;
            margin-bottom: 0.5rem;
        }
        
        .form-respuesta {
            margin-top: 1rem;
        }
        
        .form-respuesta textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: vertical;
            font-family: inherit;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 968px) {
            .resenas-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <div class="resenas-container">
        <h1 style="color: #0D87A8; margin-bottom: 2rem; font-size: clamp(1.5rem, 4vw, 2.5rem);">
            <i class="fas fa-star"></i> Reseñas
        </h1>
        
        <div class="resenas-layout">
            <?php include 'components/sidebar_vendedor.php'; ?>
            
            <div>
                <!-- Estadísticas -->
                <div class="stats-card">
                    <div class="calificacion-grande">
                        <?php echo $stats['promedio'] > 0 ? number_format($stats['promedio'], 1) : 'N/A'; ?>
                    </div>
                    <div class="estrellas">
                        <?php 
                        $promedio_redondeado = round($stats['promedio']);
                        for ($i = 1; $i <= 5; $i++):
                        ?>
                            <i class="<?php echo $i <= $promedio_redondeado ? 'fas' : 'far'; ?> fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <p style="color: #666; margin: 0;">
                        Basado en <?php echo $stats['total']; ?> reseña<?php echo $stats['total'] != 1 ? 's' : ''; ?>
                    </p>
                    
                    <?php if ($stats['total'] > 0): ?>
                    <div class="distribucion-calificaciones">
                        <?php for ($i = 5; $i >= 1; $i--): 
                            $cantidad = $distribucion[$i] ?? 0;
                            $porcentaje = $stats['total'] > 0 ? ($cantidad / $stats['total']) * 100 : 0;
                        ?>
                        <div class="barra-calificacion">
                            <div class="barra-calificacion-label">
                                <?php echo $i; ?> <i class="fas fa-star"></i>
                            </div>
                            <div class="barra-calificacion-progreso">
                                <div class="barra-calificacion-fill" style="width: <?php echo $porcentaje; ?>%;"></div>
                            </div>
                            <div class="barra-calificacion-cantidad">
                                <?php echo $cantidad; ?>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Filtros -->
                <div class="filtros">
                    <a href="?tipo=todas" class="cta-button" style="background: <?php echo $filtro_tipo === 'todas' ? '#0D87A8' : 'white'; ?>; color: <?php echo $filtro_tipo === 'todas' ? 'white' : '#0D87A8'; ?>; border: 2px solid #0D87A8; text-decoration: none;">Todas</a>
                    <a href="?tipo=vendedor" class="cta-button" style="background: <?php echo $filtro_tipo === 'vendedor' ? '#0D87A8' : 'white'; ?>; color: <?php echo $filtro_tipo === 'vendedor' ? 'white' : '#0D87A8'; ?>; border: 2px solid #0D87A8; text-decoration: none;">Como Vendedor</a>
                    <a href="?tipo=producto" class="cta-button" style="background: <?php echo $filtro_tipo === 'producto' ? '#0D87A8' : 'white'; ?>; color: <?php echo $filtro_tipo === 'producto' ? 'white' : '#0D87A8'; ?>; border: 2px solid #0D87A8; text-decoration: none;">De Productos</a>
                </div>
                
                <!-- Lista de Reseñas -->
                <?php if (empty($resenas)): ?>
                    <div style="background: white; padding: 4rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); text-align: center; color: #666;">
                        <i class="fas fa-star" style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;"></i>
                        <h3 style="color: #666; margin-bottom: 0.5rem;">No tienes reseñas aún</h3>
                        <p style="color: #999;">Cuando recibas calificaciones, aparecerán aquí</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($resenas as $resena): 
                        $iniciales = strtoupper(substr($resena['calificador_nombre'], 0, 2));
                        $fecha_resena = date('d/m/Y', strtotime($resena['fecha_calificacion']));
                    ?>
                    <div class="resena-item">
                        <div class="resena-header">
                            <div class="resena-avatar">
                                <?php echo htmlspecialchars($iniciales); ?>
                            </div>
                            <div class="resena-info">
                                <div class="resena-nombre">
                                    <?php echo htmlspecialchars($resena['calificador_nombre']); ?>
                                </div>
                                <div class="resena-fecha">
                                    <?php echo $fecha_resena; ?>
                                </div>
                                <div class="resena-estrellas">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="<?php echo $i <= $resena['calificacion'] ? 'fas' : 'far'; ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($resena['titulo'])): ?>
                        <div class="resena-titulo">
                            <?php echo htmlspecialchars($resena['titulo']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($resena['comentario'])): ?>
                        <div class="resena-comentario">
                            <?php echo nl2br(htmlspecialchars($resena['comentario'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($resena['producto_titulo'])): ?>
                        <div class="resena-producto">
                            <div class="resena-producto-img">
                                <?php if (!empty($resena['producto_imagen'])): ?>
                                    <img src="<?php echo htmlspecialchars($resena['producto_imagen']); ?>" 
                                         alt="<?php echo htmlspecialchars($resena['producto_titulo']); ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-box"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($resena['producto_titulo']); ?></strong>
                                <?php if (!empty($resena['numero_orden'])): ?>
                                <p style="margin: 0.25rem 0 0 0; color: #666; font-size: 0.85rem;">
                                    Orden: <?php echo htmlspecialchars($resena['numero_orden']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($resena['respuesta'])): ?>
                        <div class="resena-respuesta">
                            <div class="resena-respuesta-header">
                                <i class="fas fa-reply"></i> Tu respuesta:
                            </div>
                            <div>
                                <?php echo nl2br(htmlspecialchars($resena['respuesta'])); ?>
                            </div>
                            <?php if (!empty($resena['fecha_respuesta'])): ?>
                            <div style="font-size: 0.85rem; color: #999; margin-top: 0.5rem;">
                                <?php echo date('d/m/Y', strtotime($resena['fecha_respuesta'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <form class="form-respuesta" method="POST">
                            <input type="hidden" name="resena_id" value="<?php echo (int)$resena['id']; ?>">
                            <textarea name="respuesta" placeholder="Escribe una respuesta..." rows="3" required></textarea>
                            <button type="submit" name="responder_resena" class="cta-button" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                                <i class="fas fa-reply"></i> Responder
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

