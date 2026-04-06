<?php
session_start();
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth_middleware.php'; // Necesario para puede_vender()

// Bloquear perfil personal para administradores
if (is_admin()) {
    $_SESSION['error_message'] = 'Los administradores no tienen perfil de usuario.';
    header('Location: admin_dashboard.php');
    exit;
}

require_login();

$user = get_session_user();
$usuario_id = $user['id'] ?? null;

if (!$usuario_id || !isset($pdo)) {
    $_SESSION['error_message'] = 'Error al cargar datos del usuario.';
    header('Location: index.php');
    exit;
}

// Obtener datos completos del usuario desde la base de datos
$usuario_data = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.nombre, u.apellidos, u.telefono, u.email, u.rol,
               p.foto_perfil, p.ubicacion_ciudad, p.ubicacion_estado, p.codigo_postal
        FROM usuarios u
        LEFT JOIN perfiles p ON p.usuario_id = u.id
        WHERE u.id = ? AND u.estado = 'activo'
    ");
    $stmt->execute([$usuario_id]);
    $usuario_data = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error al obtener datos del usuario: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error al cargar tu perfil.';
    header('Location: index.php');
    exit;
}

if (!$usuario_data) {
    $_SESSION['error_message'] = 'Usuario no encontrado.';
    header('Location: index.php');
    exit;
}

// Normalizar datos para la vista
$nombre           = $usuario_data['nombre'] ?? '';
$apellidos        = $usuario_data['apellidos'] ?? '';
$telefono         = $usuario_data['telefono'] ?? '';
$email            = $usuario_data['email'] ?? '';
$rol              = $usuario_data['rol'] ?? 'usuario';
$foto_perfil      = $usuario_data['foto_perfil'] ?? '';
$ubicacion_ciudad = $usuario_data['ubicacion_ciudad'] ?? '';
$ubicacion_estado = $usuario_data['ubicacion_estado'] ?? '';
$codigo_postal    = $usuario_data['codigo_postal'] ?? '';
$puede_vender     = false;
if (function_exists('puede_vender')) { $puede_vender = puede_vender($usuario_id); }

// Nombre completo para mostrar
$nombre_completo = trim(($nombre ?: '') . ' ' . ($apellidos ?: ''));

// Sanitizar tab
$tab = isset($_GET['tab']) ? htmlspecialchars($_GET['tab'], ENT_QUOTES, 'UTF-8') : 'perfil';
$allowed_tabs = ['perfil', 'compras', 'ventas', 'favoritos', 'mensajes', 'configuracion'];
if (!in_array($tab, $allowed_tabs)) {
    $tab = 'perfil';
}

// Cargar compras reales del usuario cuando se visita la pestaña "compras"
$compras = [];
$compras_error = '';

if ($tab === 'compras' && $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                o.id,
                o.numero_orden,
                o.total,
                o.estado,
                o.estado_pago,
                o.fecha_creacion,
                o.ciudad_envio,
                COUNT(oi.id) AS cantidad_items,
                MIN(oi.producto_titulo) AS producto_principal,
                MIN(oi.producto_imagen) AS imagen_principal
            FROM ordenes o
            LEFT JOIN orden_items oi ON oi.orden_id = o.id
            WHERE o.comprador_id = ?
            GROUP BY
                o.id, o.numero_orden, o.total,
                o.estado, o.estado_pago,
                o.fecha_creacion, o.ciudad_envio
            ORDER BY o.fecha_creacion DESC
        ");
        $stmt->execute([$usuario_id]);
        $compras = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error cargando compras: " . $e->getMessage());
        $compras_error = 'No se pudieron cargar tus compras en este momento.';
    }
}

// Cargar favoritos reales del usuario cuando se visita la pestaña "favoritos"
$favoritos = [];
if ($tab === 'favoritos' && $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                f.producto_id,
                p.titulo,
                p.precio,
                p.activo,
                p.vendido,
                p.ubicacion_ciudad,
                (SELECT url_imagen FROM imagenes_productos WHERE producto_id = p.id AND es_principal = 1 LIMIT 1) AS imagen_principal
            FROM favoritos f
            JOIN productos p ON p.id = f.producto_id
            WHERE f.usuario_id = ?
            ORDER BY f.fecha_agregado DESC
        ");
        $stmt->execute([$usuario_id]);
        $favoritos = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error cargando favoritos: " . $e->getMessage());
    }
}

// Mostrar mensajes de éxito/error
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0D87A8">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Mi Perfil - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <section style="padding: 6rem 0 4rem; background: var(--c-bg, #F2F9FB);">
        <div class="container">
            <div style="display: grid; grid-template-columns: 250px 1fr; gap: 2rem;" id="profileLayout">
                
                <!-- Sidebar -->
                <aside id="profileSidebar" style="background: white; border-radius: 12px; padding: 1.5rem; height: fit-content;">
                    <div style="text-align: center; padding-bottom: 1.5rem; border-bottom: 1px solid #eee; margin-bottom: 1.5rem;">
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #0D87A8, #0C9268); border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; overflow: hidden; color: white; font-size: 2rem;">
                            <?php if (!empty($foto_perfil)): ?>
                                <img src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Avatar" style="width: 80px; height: 80px; object-fit: cover;">
                            <?php else: ?>
                            <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <h3 style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars($nombre_completo ?: 'Mi cuenta'); ?></h3>
                        <p style="font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($email); ?></p>
                        <?php if ($rol): ?>
                            <p style="font-size: 0.8rem; color: #999; text-transform: uppercase; letter-spacing: .08em; margin-top: 0.25rem;">
                                <?php echo htmlspecialchars($rol); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <nav>
                        <a href="?tab=perfil" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; margin-bottom: 0.5rem; border-radius: 8px; text-decoration: none; color: inherit; <?php echo $tab == 'perfil' ? 'background: #f0f9f6; color: #0C9268;' : ''; ?>">
                            <i class="fas fa-user-circle"></i>
                            Mi Perfil
                        </a>
                        <a href="?tab=compras" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; margin-bottom: 0.5rem; border-radius: 8px; text-decoration: none; color: inherit; <?php echo $tab == 'compras' ? 'background: #f0f9f6; color: #0C9268;' : ''; ?>">
                            <i class="fas fa-shopping-bag"></i>
                            Mis Compras
                        </a>
                        <?php 
                        // Mostrar botón de Panel Vendedor si puede vender (usando la misma lógica que mensajes.php)
                        // Verificar usando puede_vender() si está disponible
                        $puede_vender_productos = false;
                        if (function_exists('puede_vender')) {
                            $puede_vender_productos = puede_vender($usuario_id);
                        } else {
                            // Fallback: verificar campo puede_vender y rol admin
                            $puede_vender_productos = ($puede_vender || $rol === 'admin');
                        }
                        
                        if ($puede_vender_productos): 
                        ?>
                        <a href="vendedor_dashboard.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; margin-bottom: 0.5rem; border-radius: 8px; text-decoration: none; color: inherit; background: linear-gradient(135deg, #0D87A8, #0C9268); color: white; font-weight: 600;">
                            <i class="fas fa-store"></i>
                            Panel Vendedor
                        </a>
                        <?php endif; ?>
                        <a href="?tab=favoritos" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; margin-bottom: 0.5rem; border-radius: 8px; text-decoration: none; color: inherit; <?php echo $tab == 'favoritos' ? 'background: #f0f9f6; color: #0C9268;' : ''; ?>">
                            <i class="far fa-heart"></i>
                            Favoritos
                        </a>
                        <a href="mensajes.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; margin-bottom: 0.5rem; border-radius: 8px; text-decoration: none; color: inherit;">
                            <i class="fas fa-comments"></i>
                            Mensajes
                        </a>
                        <a href="?tab=configuracion" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; margin-bottom: 0.5rem; border-radius: 8px; text-decoration: none; color: inherit; <?php echo $tab == 'configuracion' ? 'background: #f0f9f6; color: #0C9268;' : ''; ?>">
                            <i class="fas fa-cog"></i>
                            Configuración
                        </a>
                    </nav>
                </aside>
                
                <!-- Contenido Principal -->
                <main style="background: white; border-radius: 12px; padding: 2rem;">
                    
                    <?php if ($success_message): ?>
                        <div style="padding: 1rem; border-radius: 10px; background: #d4edda; color: #155724; margin-bottom: 1.5rem;">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div style="padding: 1rem; border-radius: 10px; background: #fdecea; color: #b71c1c; margin-bottom: 1.5rem;">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($tab == 'perfil'): ?>
                        <h2 style="margin-bottom: 2rem; color: #0D87A8;">Mi Perfil</h2>
                        <form method="POST" action="process_update_profile.php" enctype="multipart/form-data">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;" class="profile-grid-2">
                                <div>
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Nombre *</label>
                                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; font-size: clamp(0.9rem, 2vw, 1rem);">
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Apellidos</label>
                                    <input type="text" name="apellidos" value="<?php echo htmlspecialchars($apellidos); ?>" style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; font-size: clamp(0.9rem, 2vw, 1rem);">
                                </div>
                            </div>
                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Email *</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px;">
                            </div>
                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Teléfono</label>
                                <input type="tel" name="telefono" value="<?php echo htmlspecialchars($telefono); ?>" style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px;">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;" class="profile-grid-2">
                                <div>
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Ciudad</label>
                                    <input type="text" name="ubicacion_ciudad" value="<?php echo htmlspecialchars($ubicacion_ciudad); ?>" style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; font-size: clamp(0.9rem, 2vw, 1rem);">
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Estado</label>
                                    <input type="text" name="ubicacion_estado" value="<?php echo htmlspecialchars($ubicacion_estado); ?>" style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; font-size: clamp(0.9rem, 2vw, 1rem);">
                                </div>
                            </div>
                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Código Postal</label>
                                <input type="text" name="codigo_postal" value="<?php echo htmlspecialchars($codigo_postal); ?>" style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; max-width: 220px;">
                            </div>
                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Foto de Perfil</label>
                                <div style="display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;">
                                    <div id="avatarPreviewWrap" style="width: 90px; height: 90px; border-radius: 50%; overflow: hidden; background: #edf2f4; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 2px solid #C5DEE8;">
                                        <?php if (!empty($foto_perfil)): ?>
                                            <img id="avatarPreview" src="<?php echo htmlspecialchars($foto_perfil); ?>" style="width:100%; height:100%; object-fit:cover;">
                                        <?php else: ?>
                                            <i id="avatarPlaceholder" class="fas fa-user" style="font-size: 2.2rem; color: #b0bec5;"></i>
                                            <img id="avatarPreview" src="" style="width:100%; height:100%; object-fit:cover; display:none;">
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <label for="foto_file" style="display: inline-block; padding: 0.55rem 1.1rem; background: #f0f9f6; border: 1.5px solid #0C9268; color: #0C9268; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: .9rem;">
                                            <i class="fas fa-upload"></i> Elegir imagen
                                        </label>
                                        <input type="file" id="foto_file" name="foto_file" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewAvatar(this)">
                                        <p style="font-size: 0.78rem; color: #4A7585; margin-top: 0.45rem; margin-bottom: 0;">
                                            JPG, PNG o WebP · Tamaño ideal: <strong>400 × 400 px</strong> · Máx. 2 MB
                                        </p>
                                        <p id="avatarFileName" style="font-size: 0.8rem; color: #0C9268; margin-top: 0.2rem; display: none;"></p>
                                    </div>
                                </div>
                            </div>
                            <script>
                            function previewAvatar(input) {
                                if (!input.files || !input.files[0]) return;
                                const file = input.files[0];
                                if (file.size > 2 * 1024 * 1024) {
                                    alert('La imagen no puede superar 2 MB.');
                                    input.value = '';
                                    return;
                                }
                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    const img = document.getElementById('avatarPreview');
                                    const ph  = document.getElementById('avatarPlaceholder');
                                    img.src = e.target.result;
                                    img.style.display = 'block';
                                    if (ph) ph.style.display = 'none';
                                    const fn = document.getElementById('avatarFileName');
                                    fn.textContent = file.name;
                                    fn.style.display = 'block';
                                };
                                reader.readAsDataURL(file);
                            }
                            </script>
                            <button type="submit" style="padding: 0.75rem 2rem; background: linear-gradient(135deg, #0D87A8, #0C9268); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: transform 0.2s;">
                                <i class="fas fa-save"></i> Guardar cambios
                            </button>
                        </form>
                    
                    <?php elseif ($tab == 'compras'): ?>
                        <h2 style="margin-bottom: 2rem; color: #0D87A8;">Mis Compras</h2>
                        
                        <?php if ($compras_error): ?>
                            <div style="padding: 1rem; border-radius: 8px; background: #fdecea; color: #b71c1c; margin-bottom: 1.5rem;">
                                <?php echo htmlspecialchars($compras_error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($compras) && !$compras_error): ?>
                            <div style="text-align: center; padding: 2rem; color: #666;">
                                <i class="fas fa-shopping-bag" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                                <h3>No has realizado compras todavía</h3>
                                <p style="margin-bottom: 1rem;">Cuando compres productos en Esfero, aparecerán aquí.</p>
                                <a href="catalogo.php" style="display: inline-block; padding: 0.75rem 2rem; background: linear-gradient(135deg, #0D87A8, #0C9268); color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">
                                    Ver productos
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php foreach ($compras as $orden): ?>
                            <div style="border: 1px solid #e0e0e0; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                                <div class="orden-card-flex" style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                                    <div style="width: 100px; height: 100px; border-radius: 8px; overflow: hidden; background: linear-gradient(135deg, #0D87A8, #0C9268); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; flex-shrink: 0;">
                                        <?php if (!empty($orden['imagen_principal'])): ?>
                                            <img src="<?php echo htmlspecialchars($orden['imagen_principal']); ?>" alt="Producto" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="fas fa-box"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1;">
                                        <h3 style="margin-bottom: 0.5rem; color: #0D87A8;">
                                            <?php echo htmlspecialchars($orden['producto_principal'] ?: 'Compra en Esfero'); ?>
                                            <?php if ($orden['cantidad_items'] > 1): ?>
                                                <span style="font-size: 0.85rem; color: #777;">
                                                    (+<?php echo (int)$orden['cantidad_items'] - 1; ?> productos más)
                                                </span>
                                            <?php endif; ?>
                                        </h3>
                                        <p style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">
                                            Pedido: #<?php echo htmlspecialchars($orden['numero_orden']); ?>
                                            |
                                            <?php echo date('d/m/Y H:i', strtotime($orden['fecha_creacion'])); ?>
                                        </p>
                                        <?php if (!empty($orden['ciudad_envio'])): ?>
                                        <p style="color: #777; font-size: 0.85rem; margin-bottom: 0.5rem;">
                                            Envío a: <?php echo htmlspecialchars($orden['ciudad_envio']); ?>
                                        </p>
                                        <?php endif; ?>
                                        <p style="font-size: 1.25rem; font-weight: 700; color: #0C9268;">
                                            <?php echo formatearPrecio($orden['total'], 'MXN'); ?>
                                        </p>
                                    </div>
                                    <div style="text-align: right;">
                                        <span style="display: inline-block; padding: 0.35rem 0.75rem; background: #d4edda; color: #155724; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem;">
                                            <?php echo htmlspecialchars($orden['estado_pago'] ?: $orden['estado']); ?>
                                        </span>
                                        <div>
                                            <a href="orden.php?id=<?php echo (int)$orden['id']; ?>"
                                               style="padding: 0.5rem 1rem; border: 1px solid #0C9268; background: white; color: #0C9268; border-radius: 6px; cursor: pointer; font-size: 0.9rem; text-decoration: none; display: inline-block;">
                                                Ver Detalles
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    
                    <?php elseif ($tab == 'favoritos'): ?>
                        <h2 style="margin-bottom: 2rem; color: #0D87A8;">Mis Favoritos</h2>
                        <?php if (empty($favoritos)): ?>
                            <div style="text-align: center; padding: 2rem; color: #666;">
                                <i class="far fa-heart" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem; display:block;"></i>
                                <h3>No tienes favoritos aún</h3>
                                <p style="margin-bottom: 1rem;">Guarda tus productos favoritos para encontrarlos fácilmente.</p>
                                <a href="catalogo.php" style="display: inline-block; padding: 0.75rem 2rem; background: linear-gradient(135deg, #0D87A8, #0C9268); color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">
                                    Explorar productos
                                </a>
                            </div>
                        <?php else: ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.25rem;">
                            <?php foreach ($favoritos as $fav):
                                $img = htmlspecialchars($fav['imagen_principal'] ?: 'https://placehold.co/300x300?text=Sin+imagen');
                                $no_disponible = ($fav['vendido'] == 1 || $fav['activo'] == 0);
                            ?>
                                <div style="border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); background: white; <?= $no_disponible ? 'opacity:.6;' : '' ?>">
                                    <a href="producto.php?id=<?= (int)$fav['producto_id'] ?>" style="text-decoration:none; color:inherit;">
                                        <img src="<?= $img ?>" style="width:100%; aspect-ratio:1/1; object-fit:cover; display:block;" loading="lazy">
                                        <div style="padding: .75rem 1rem;">
                                            <p style="font-size:.88rem; font-weight:600; margin-bottom:.3rem; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; color:#0B2D3C;">
                                                <?= htmlspecialchars($fav['titulo']) ?>
                                            </p>
                                            <?php if ($no_disponible): ?>
                                                <p style="font-size:.78rem; color:#dc3545; font-weight:600;">No disponible</p>
                                            <?php else: ?>
                                                <p style="font-size:.95rem; font-weight:700; color:#0C9268;">$<?= number_format((float)$fav['precio'], 2) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($fav['ubicacion_ciudad'])): ?>
                                                <p style="font-size:.76rem; color:#4A7585;"><?= htmlspecialchars($fav['ubicacion_ciudad']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                    <div style="padding: 0 1rem .75rem;">
                                        <button onclick="removeFavoritoPerfil(<?= (int)$fav['producto_id'] ?>, this)"
                                                style="width:100%; padding:.45rem; background:#fdecea; color:#dc3545; border:none; border-radius:6px; cursor:pointer; font-size:.82rem; font-weight:600;">
                                            <i class="fas fa-heart-crack"></i> Quitar de favoritos
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                            <script>
                            function removeFavoritoPerfil(productoId, btn) {
                                btn.disabled = true;
                                fetch('process_favoritos.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: 'action=remove&producto_id=' + productoId
                                })
                                .then(r => r.json())
                                .then(data => {
                                    if (data.success) {
                                        btn.closest('div[style*="border-radius: 12px"]').remove();
                                        if (typeof window.updateNavbarCounters === 'function') window.updateNavbarCounters();
                                    } else {
                                        btn.disabled = false;
                                        alert(data.error || 'Error al quitar de favoritos');
                                    }
                                })
                                .catch(() => { btn.disabled = false; });
                            }
                            </script>
                        <?php endif; ?>
                    
                    <?php elseif ($tab == 'configuracion'): ?>
                        <h2 style="margin-bottom: 2rem; color: #0D87A8;">Configuración</h2>
                        <div style="text-align: center; padding: 2rem; color: #666;">
                            <i class="fas fa-cog" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                            <h3>Configuración en desarrollo</h3>
                            <p>Esta sección estará disponible próximamente.</p>
                        </div>
                    
                    <?php else: ?>
                        <h2 style="margin-bottom: 2rem; color: #0D87A8;">Contenido en desarrollo</h2>
                        <p>Esta sección estará disponible próximamente.</p>
                    <?php endif; ?>
                    
                </main>
            </div>
        </div>
    </section>
    
    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <style>
    /* Responsividad para perfil */
    @media (max-width: 968px) {
        #profileLayout {
            grid-template-columns: 1fr !important;
        }
        
        #profileSidebar {
            position: relative !important;
            margin-bottom: 2rem;
        }
        
        .profile-grid-2 {
            grid-template-columns: 1fr !important;
        }
        
        #profileSidebar nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        #profileSidebar nav a {
            flex: 1;
            min-width: 120px;
            justify-content: center;
        }
    }
    
    @media (max-width: 640px) {
        #profileSidebar {
            padding: 1rem !important;
        }

        main {
            padding: 1.5rem !important;
        }

        input, textarea {
            font-size: 16px !important; /* Previene zoom en iOS */
        }

        /* Order cards stack vertically on mobile */
        .orden-card-flex {
            flex-direction: column !important;
        }

        .orden-card-flex > div:first-child {
            width: 100% !important;
            max-width: 200px !important;
            height: 120px !important;
            margin: 0 auto !important;
        }

        .orden-card-flex > div:last-child {
            text-align: left !important;
            margin-top: 0.5rem;
        }
    }
    </style>
</body>
</html>
