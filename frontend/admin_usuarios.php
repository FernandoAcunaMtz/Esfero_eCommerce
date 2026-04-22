<?php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
session_start();
require_role('admin', 'index.php');

// Función simple para sanitizar input (reemplazo de sanitize_input)
function sanitize_input($value) {
    if ($value === null || $value === '') {
        return '';
    }
    return trim(strip_tags((string)$value));
}

// Procesar acciones
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'cambiar_estado' && isset($_POST['usuario_id']) && isset($_POST['nuevo_estado'])) {
        $usuario_id = (int)$_POST['usuario_id'];
        $nuevo_estado = sanitize_input($_POST['nuevo_estado']);

        if (in_array($nuevo_estado, ['activo', 'inactivo', 'suspendido', 'baneado'])) {
            if (actualizarEstadoUsuario($usuario_id, $nuevo_estado)) {
                $mensaje = 'Estado del usuario actualizado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al actualizar el estado del usuario';
                $tipo_mensaje = 'error';
            }
        }
    }

    if ($_POST['accion'] === 'toggle_vendedor' && isset($_POST['usuario_id'])) {
        $usuario_id = (int)$_POST['usuario_id'];
        global $pdo;
        if (isset($pdo)) {
            try {
                // Solo aplica a usuarios con rol 'usuario', no a admins
                $stmt = $pdo->prepare(
                    "UPDATE usuarios SET puede_vender = IF(puede_vender = 1, 0, 1)
                     WHERE id = ? AND rol = 'usuario'"
                );
                $stmt->execute([$usuario_id]);
                if ($stmt->rowCount() > 0) {
                    $mensaje = 'Permiso de vendedor actualizado.';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'No se pudo actualizar (el usuario puede ser admin).';
                    $tipo_mensaje = 'error';
                }
            } catch (Exception $e) {
                error_log('toggle_vendedor error: ' . $e->getMessage());
                $mensaje = 'Error al actualizar el permiso de vendedor.';
                $tipo_mensaje = 'error';
            }
        }
    }
}

// Obtener filtros
$filtros = [
    'busqueda'      => sanitize_input($_GET['busqueda'] ?? ''),
    'rol'           => sanitize_input($_GET['rol'] ?? 'todos'),
    'estado'        => sanitize_input($_GET['estado'] ?? 'todos'),
    'puede_vender'  => sanitize_input($_GET['puede_vender'] ?? 'todos'),
    'limite'        => 50,
    'offset'        => 0
];

// Obtener usuarios
$usuarios = getUsuariosAdmin($filtros);
$total_usuarios = contarUsuariosAdmin($filtros);

// Debug: verificar si hay errores
if (empty($usuarios) && $total_usuarios == 0) {
    // Verificar conexión a BD
    global $pdo;
    if (!isset($pdo)) {
        error_log("Error: PDO no está disponible en admin_usuarios.php");
    }
}

// Nombres de roles y estados
$rol_nombres = [
    'admin'   => 'Administrador',
    'usuario' => 'Usuario',
];

$estado_nombres = [
    'activo' => 'Activo',
    'inactivo' => 'Inactivo',
    'suspendido' => 'Suspendido',
    'baneado' => 'Baneado'
];
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
    <title>Gestión de Usuarios - Esfero</title>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    <div style="width: 100%; margin: 100px auto 50px; padding: 2rem;">
        <h1 style="color: #dc3545; margin-bottom: 2rem; font-size: clamp(1.5rem, 4vw, 2.5rem);">
            <i class="fas fa-users"></i> Gestión de Usuarios
        </h1>
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem;" id="adminUsuariosLayout">
            <?php include 'components/sidebar_admin.php'; ?>
            <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                <?php if ($mensaje): ?>
                    <div style="padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; background: <?php echo $tipo_mensaje === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $tipo_mensaje === 'success' ? '#155724' : '#721c24'; ?>; border: 1px solid <?php echo $tipo_mensaje === 'success' ? '#c3e6cb' : '#f5c6cb'; ?>;">
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>
                
                <form method="GET" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;" id="adminUsuariosFilters">
                    <input type="text" name="busqueda" placeholder="Buscar usuarios..." value="<?php echo htmlspecialchars($filtros['busqueda']); ?>" style="flex: 1; min-width: 200px; padding: 1rem; border: 2px solid #e0e0e0; border-radius: 10px; font-size: clamp(0.9rem, 2vw, 1rem);">
                    <select name="rol" style="padding: 1rem; border: 2px solid #e0e0e0; border-radius: 10px; font-size: clamp(0.9rem, 2vw, 1rem);">
                        <option value="todos" <?php echo $filtros['rol'] === 'todos' ? 'selected' : ''; ?>>Todos los roles</option>
                        <option value="usuario" <?php echo $filtros['rol'] === 'usuario' ? 'selected' : ''; ?>>Usuario</option>
                        <option value="admin" <?php echo $filtros['rol'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                    </select>
                    <select name="estado" style="padding: 1rem; border: 2px solid #e0e0e0; border-radius: 10px; font-size: clamp(0.9rem, 2vw, 1rem);">
                        <option value="todos" <?php echo $filtros['estado'] === 'todos' ? 'selected' : ''; ?>>Todos los estados</option>
                        <option value="activo" <?php echo $filtros['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $filtros['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                        <option value="suspendido" <?php echo $filtros['estado'] === 'suspendido' ? 'selected' : ''; ?>>Suspendido</option>
                        <option value="baneado" <?php echo $filtros['estado'] === 'baneado' ? 'selected' : ''; ?>>Baneado</option>
                    </select>
                    <select name="puede_vender" style="padding: 1rem; border: 2px solid #e0e0e0; border-radius: 10px; font-size: clamp(0.9rem, 2vw, 1rem);">
                        <option value="todos" <?php echo $filtros['puede_vender'] === 'todos' ? 'selected' : ''; ?>>Todos (vendedor)</option>
                        <option value="1" <?php echo $filtros['puede_vender'] === '1' ? 'selected' : ''; ?>>Con permiso vendedor</option>
                        <option value="0" <?php echo $filtros['puede_vender'] === '0' ? 'selected' : ''; ?>>Sin permiso vendedor</option>
                    </select>
                    <button type="submit" style="padding: 1rem 2rem; background: #0C9268; color: white; border: none; border-radius: 10px; cursor: pointer; font-size: clamp(0.9rem, 2vw, 1rem);">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </form>
                
                <div style="margin-bottom: 1rem; color: #666;">
                    Total: <?php echo number_format($total_usuarios); ?> usuario<?php echo $total_usuarios != 1 ? 's' : ''; ?>
                </div>
                
                <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #e0e0e0;">
                            <th style="padding: 1rem; text-align: left; color: #0D87A8;">Usuario</th>
                            <th style="padding: 1rem; text-align: left; color: #0D87A8;">Email</th>
                            <th style="padding: 1rem; text-align: left; color: #0D87A8;">Rol</th>
                            <th style="padding: 1rem; text-align: left; color: #0D87A8;">Vendedor</th>
                            <th style="padding: 1rem; text-align: left; color: #0D87A8;">Estado</th>
                            <th style="padding: 1rem; text-align: center; color: #0D87A8;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 3rem; color: #666;">
                                    No se encontraron usuarios con los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $usuario): 
                                $nombre_completo = trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''));
                                if (empty($nombre_completo)) {
                                    $nombre_completo = $usuario['email'] ?? 'Usuario';
                                }
                            ?>
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td style="padding: 1rem;">
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #0D87A8, #0C9268); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                            <?php if (!empty($usuario['foto_perfil'])): ?>
                                                <img src="<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($nombre_completo); ?></strong>
                                            <?php if (!empty($usuario['total_productos'])): ?>
                                                <br><small style="color: #666;"><?php echo (int)$usuario['total_productos']; ?> producto<?php echo $usuario['total_productos'] != 1 ? 's' : ''; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 1rem; color: #666;"><?php echo htmlspecialchars($usuario['email'] ?? ''); ?></td>
                                <td style="padding: 1rem;">
                                    <span style="background: #e8f3ff; color: #0D87A8; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($rol_nombres[$usuario['rol']] ?? ucfirst($usuario['rol'] ?? 'Usuario')); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php if ($usuario['rol'] === 'admin'): ?>
                                        <span style="color: #9ca3af; font-size: 0.82rem;">—</span>
                                    <?php elseif (!empty($usuario['puede_vender'])): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Desactivar permiso de vendedor a este usuario?');">
                                            <input type="hidden" name="accion" value="toggle_vendedor">
                                            <input type="hidden" name="usuario_id" value="<?php echo (int)$usuario['id']; ?>">
                                            <button type="submit" style="background:#e8fff6; color:#0C9268; border:none; padding:0.25rem 0.75rem; border-radius:15px; font-size:0.85rem; cursor:pointer;">
                                                <i class="fas fa-store"></i> Activo
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Activar permiso de vendedor a este usuario?');">
                                            <input type="hidden" name="accion" value="toggle_vendedor">
                                            <input type="hidden" name="usuario_id" value="<?php echo (int)$usuario['id']; ?>">
                                            <button type="submit" style="background:#f0f0f0; color:#666; border:none; padding:0.25rem 0.75rem; border-radius:15px; font-size:0.85rem; cursor:pointer;">
                                                <i class="fas fa-store-slash"></i> Inactivo
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php
                                    $estado_color = $usuario['estado'] === 'activo' ? '#e8fff6' : ($usuario['estado'] === 'suspendido' ? '#ffe5e5' : '#f0f0f0');
                                    $estado_texto_color = $usuario['estado'] === 'activo' ? '#0C9268' : ($usuario['estado'] === 'suspendido' ? '#dc3545' : '#666');
                                    ?>
                                    <span style="background: <?php echo $estado_color; ?>; color: <?php echo $estado_texto_color; ?>; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($estado_nombres[$usuario['estado']] ?? ucfirst($usuario['estado'] ?? 'Desconocido')); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <form method="POST" style="display: inline-block; margin-right: 0.5rem;" onsubmit="return confirm('¿Estás seguro de cambiar el estado de este usuario?');">
                                        <input type="hidden" name="accion" value="cambiar_estado">
                                        <input type="hidden" name="usuario_id" value="<?php echo (int)$usuario['id']; ?>">
                                        <select name="nuevo_estado" onchange="this.form.submit()" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px; font-size: 0.85rem;">
                                            <option value="activo" <?php echo ($usuario['estado'] ?? '') === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                            <option value="inactivo" <?php echo ($usuario['estado'] ?? '') === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                            <option value="suspendido" <?php echo ($usuario['estado'] ?? '') === 'suspendido' ? 'selected' : ''; ?>>Suspendido</option>
                                            <option value="baneado" <?php echo ($usuario['estado'] ?? '') === 'baneado' ? 'selected' : ''; ?>>Baneado</option>
                                        </select>
                                    </form>
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
    <style>
    /* Responsividad para admin usuarios */
    @media (max-width: 968px) {
        #adminUsuariosLayout {
            grid-template-columns: 1fr !important;
        }
        
        #adminUsuariosFilters {
            flex-direction: column !important;
        }
        
        #adminUsuariosFilters input,
        #adminUsuariosFilters select {
            width: 100% !important;
            margin-left: 0 !important;
        }
    }
    
    @media (max-width: 640px) {
        div[style*="width: 100%"] {
            padding: 1rem !important;
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
</body>
</html>
