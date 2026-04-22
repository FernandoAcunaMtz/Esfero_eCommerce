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

// Procesar acciones (eliminar, activar/desactivar)
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $guia_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($guia_id > 0) {
        try {
            global $pdo;
            if (isset($pdo)) {
                if ($_POST['action'] === 'eliminar') {
                    $stmt = $pdo->prepare("DELETE FROM guias WHERE id = ?");
                    $stmt->execute([$guia_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $mensaje = 'Guía eliminada exitosamente';
                        $tipo_mensaje = 'success';
                    } else {
                        $mensaje = 'No se pudo eliminar la guía';
                        $tipo_mensaje = 'error';
                    }
                } elseif ($_POST['action'] === 'toggle_activo') {
                    // Obtener estado actual
                    $stmt = $pdo->prepare("SELECT activo FROM guias WHERE id = ?");
                    $stmt->execute([$guia_id]);
                    $guia_actual = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($guia_actual) {
                        $nuevo_estado = $guia_actual['activo'] ? 0 : 1;
                        $stmt = $pdo->prepare("UPDATE guias SET activo = ?, fecha_actualizacion = NOW() WHERE id = ?");
                        $stmt->execute([$nuevo_estado, $guia_id]);
                        
                        if ($stmt->rowCount() > 0) {
                            $mensaje = $nuevo_estado ? 'Guía activada exitosamente' : 'Guía desactivada exitosamente';
                            $tipo_mensaje = 'success';
                        } else {
                            $mensaje = 'No se pudo cambiar el estado de la guía';
                            $tipo_mensaje = 'error';
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error al procesar acción: " . $e->getMessage());
            $mensaje = 'Error al procesar la acción: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}

// Obtener todas las guías con información del autor (activas e inactivas)
$guias = [];
$total_guias = 0;
$guias_activas = 0;
$guias_inactivas = 0;

try {
    global $pdo;
    if (!isset($pdo)) {
        $mensaje = 'Error: No se pudo conectar a la base de datos';
        $tipo_mensaje = 'error';
    } else {
        // Obtener todas las guías sin filtro de activo
        $sql = "
            SELECT g.*, 
                   u.nombre as autor_nombre, 
                   u.email as autor_email,
                   COALESCE(g.activo, 0) as activo,
                   COALESCE(g.destacado, 0) as destacado,
                   0 as vistas
            FROM guias g
            LEFT JOIN usuarios u ON g.autor_id = u.id
            ORDER BY g.fecha_publicacion DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $guias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Verificar que fetchAll devolvió un array
        if (!is_array($guias)) {
            $guias = [];
        } else {
            // Forzar que sea un array indexado numéricamente (reindexar)
            $guias = array_values($guias);
        }
        
        // Contar estadísticas
        $total_guias = is_array($guias) ? count($guias) : 0;
        foreach ($guias as $guia) {
            // Manejar activo como booleano o entero
            $activo = (int)$guia['activo'];
            if ($activo == 1) {
                $guias_activas++;
            } else {
                $guias_inactivas++;
            }
        }
        
    }
} catch (PDOException $e) {
    error_log("Error PDO al obtener guías: " . $e->getMessage());
    error_log("SQL: " . ($sql ?? 'N/A'));
    $mensaje = 'Error al cargar las guías: ' . $e->getMessage();
    $tipo_mensaje = 'error';
    $guias = [];
} catch (Exception $e) {
    error_log("Error general al obtener guías: " . $e->getMessage());
    $mensaje = 'Error al cargar las guías: ' . $e->getMessage();
    $tipo_mensaje = 'error';
    $guias = [];
}

// Mensajes de éxito o error desde GET
if (isset($_GET['success'])) {
    $mensaje = 'Guía ' . htmlspecialchars($_GET['success']) . ' exitosamente';
    $tipo_mensaje = 'success';
}

if (isset($_GET['error'])) {
    $mensaje = 'Error: ' . htmlspecialchars($_GET['error']);
    $tipo_mensaje = 'error';
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
    <title>Gestión de Guías - Admin</title>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .admin-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .btn-primary {
            background: #0C9268;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn-primary:hover {
            background: #008a5e;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-edit {
            background: #0D87A8;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }
        .btn-edit:hover {
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
        .guias-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow-x: auto;
            overflow-y: visible;
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
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .badge-destacado {
            background: #fff3cd;
            color: #856404;
        }
        .acciones {
            display: flex;
            gap: 0.5rem;
        }
        .btn-success {
            background: #0C9268;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-success:hover {
            background: #008a5e;
        }
        .btn-warning {
            background: #F6A623;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-warning:hover {
            background: #f37d00;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <div class="admin-container">
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem;">
            <?php include 'components/sidebar_admin.php'; ?>
            
            <div>
                <div class="admin-header">
                    <h1 style="color: #dc3545; font-size: clamp(1.5rem, 4vw, 2.5rem);">
                        <i class="fas fa-book"></i> Gestión de Guías
                    </h1>
                    <a href="admin_guias_nueva.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Nueva Guía
                    </a>
                </div>
                
                <?php if ($mensaje): ?>
                    <div class="mensaje <?php echo $tipo_mensaje; ?>" style="margin-bottom: 1.5rem;">
                        <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Estadísticas -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: #0D87A8; margin-bottom: 0.5rem;"><?php echo $total_guias; ?></div>
                        <div style="color: #666; font-size: 0.9rem;">Total de Guías</div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: #0C9268; margin-bottom: 0.5rem;"><?php echo $guias_activas; ?></div>
                        <div style="color: #666; font-size: 0.9rem;">Guías Activas</div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: #dc3545; margin-bottom: 0.5rem;"><?php echo $guias_inactivas; ?></div>
                        <div style="color: #666; font-size: 0.9rem;">Guías Inactivas</div>
                    </div>
                </div>
                
                
                <div class="guias-table" style="min-height: 200px;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Categoría</th>
                                <th>Autor</th>
                                <th>Estado</th>
                                <th>Destacado</th>
                                <th>Vistas</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // IMPORTANTE: Guardar el array antes de cualquier manipulación
                            $guias_para_render = is_array($guias) ? $guias : [];
                            $total_para_render = count($guias_para_render);
                            
                            // Definir nombres de categorías
                            $categoria_nombres = [
                                'comprar' => 'Comprar',
                                'vender' => 'Vender',
                                'envios' => 'Envíos',
                                'seguridad' => 'Seguridad',
                                'pagos' => 'Pagos',
                                'general' => 'General'
                            ];
                            
                            // Verificar si hay guías
                            if ($total_para_render === 0): 
                            ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 3rem; color: #666;">
                                        No hay guías registradas. <a href="admin_guias_nueva.php" style="color: #0C9268; text-decoration: underline;">Crear primera guía</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                // Iterar sobre todas las guías
                                $contador_guias = 0;
                                
                                for ($i = 0; $i < $total_para_render; $i++):
                                    if (!isset($guias_para_render[$i])) {
                                        continue;
                                    }
                                    
                                    $guia = $guias_para_render[$i];
                                    $contador_guias++;
                                    
                                    // Verificar que $guia sea un array válido
                                    if (!is_array($guia) || !isset($guia['id'])) {
                                        continue;
                                    }
                                    
                                    // Extraer valores de forma segura
                                    $guia_id = (int)$guia['id'];
                                    if ($guia_id <= 0) {
                                        continue;
                                    }
                                    
                                    $guia_titulo = isset($guia['titulo']) ? htmlspecialchars($guia['titulo'], ENT_QUOTES, 'UTF-8') : 'Sin título';
                                    $guia_categoria = isset($guia['categoria']) ? $guia['categoria'] : 'general';
                                    $guia_activo = isset($guia['activo']) ? (int)$guia['activo'] : 0;
                                    $guia_destacado = isset($guia['destacado']) ? (int)$guia['destacado'] : 0;
                                    $guia_vistas = isset($guia['vistas']) ? (int)$guia['vistas'] : 0;
                                    $guia_fecha = isset($guia['fecha_publicacion']) ? $guia['fecha_publicacion'] : date('Y-m-d');
                                    $guia_descripcion = isset($guia['descripcion_corta']) ? $guia['descripcion_corta'] : '';
                                    $autor_nombre = isset($guia['autor_nombre']) ? $guia['autor_nombre'] : '';
                                    $autor_email = isset($guia['autor_email']) ? $guia['autor_email'] : '';
                                ?>
                                    <tr data-guia-id="<?php echo $guia_id; ?>" data-index="<?php echo $contador_guias; ?>">
                                        <td><?php echo $guia_id; ?></td>
                                        <td>
                                            <strong style="color: #0D87A8;"><?php echo $guia_titulo; ?></strong>
                                            <?php if (!empty($guia_descripcion)): ?>
                                                <?php 
                                                // Usar substr normal en lugar de mb_substr para evitar errores
                                                $desc_corta = strlen($guia_descripcion) > 60 ? substr($guia_descripcion, 0, 60) . '...' : $guia_descripcion;
                                                ?>
                                                <br><small style="color: #666; font-size: 0.85rem;"><?php echo htmlspecialchars($desc_corta); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="background: #e8f3ff; color: #0D87A8; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.85rem;">
                                                <?php echo htmlspecialchars($categoria_nombres[$guia_categoria] ?? 'General'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($autor_nombre)): ?>
                                                <div style="font-size: 0.9rem;">
                                                    <strong><?php echo htmlspecialchars($autor_nombre); ?></strong>
                                                    <?php if (!empty($autor_email)): ?>
                                                        <br><small style="color: #666;"><?php echo htmlspecialchars($autor_email); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #999;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $guia_activo == 1 ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php echo $guia_activo == 1 ? 'Activa' : 'Inactiva'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($guia_destacado == 1): ?>
                                                <span class="badge badge-destacado">
                                                    <i class="fas fa-star"></i> Destacado
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <span style="color: #666;">
                                                <i class="fas fa-eye"></i> <?php echo number_format($guia_vistas); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            try {
                                                $fecha_formateada = !empty($guia_fecha) ? date('d/m/Y', strtotime($guia_fecha)) : 'N/A';
                                                echo $fecha_formateada;
                                            } catch (Exception $e) {
                                                echo htmlspecialchars($guia_fecha ?? 'N/A');
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="acciones" style="display: flex; flex-direction: column; gap: 0.5rem;">
                                                <a href="admin_guias_editar.php?id=<?php echo $guia_id; ?>" class="btn-edit" title="Editar guía" style="text-align: center;">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de <?php echo $guia_activo == 1 ? 'desactivar' : 'activar'; ?> esta guía?');">
                                                    <input type="hidden" name="action" value="toggle_activo">
                                                    <input type="hidden" name="id" value="<?php echo $guia_id; ?>">
                                                    <button type="submit" class="<?php echo $guia_activo == 1 ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $guia_activo == 1 ? 'Desactivar' : 'Activar'; ?> guía" style="width: 100%; padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem; <?php echo $guia_activo == 1 ? 'background: #F6A623; color: white;' : 'background: #0C9268; color: white;'; ?>">
                                                        <i class="fas fa-<?php echo $guia_activo == 1 ? 'eye-slash' : 'eye'; ?>"></i> <?php echo $guia_activo == 1 ? 'Desactivar' : 'Activar'; ?>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta guía? Esta acción no se puede deshacer.');">
                                                    <input type="hidden" name="action" value="eliminar">
                                                    <input type="hidden" name="id" value="<?php echo $guia_id; ?>">
                                                    <button type="submit" class="btn-danger" title="Eliminar guía" style="width: 100%;">
                                                        <i class="fas fa-trash"></i> Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                endfor;
                                ?>
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

