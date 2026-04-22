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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$guia = null;
$mensaje_error = '';

// Cargar guía existente
if ($id > 0) {
    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT * FROM guias WHERE id = ?");
            $stmt->execute([$id]);
            $guia = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$guia) {
                header('Location: admin_guias.php?error=Guía no encontrada');
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Error al cargar guía: " . $e->getMessage());
        header('Location: admin_guias.php?error=Error al cargar guía');
        exit;
    }
} else {
    header('Location: admin_guias.php?error=ID inválido');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = sanitize_input($_POST['titulo'] ?? '');
    $slug = sanitize_input($_POST['slug'] ?? '');
    $descripcion_corta = sanitize_input($_POST['descripcion_corta'] ?? '');
    $contenido = $_POST['contenido'] ?? '';
    $categoria = sanitize_input($_POST['categoria'] ?? 'general');
    $imagen_url = sanitize_input($_POST['imagen_url'] ?? '');
    $destacado = isset($_POST['destacado']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    if (empty($titulo)) {
        $mensaje_error = 'El título es requerido';
    } elseif (empty($slug)) {
        $mensaje_error = 'El slug es requerido';
    } elseif (empty($contenido)) {
        $mensaje_error = 'El contenido es requerido';
    } else {
        try {
            if (isset($pdo)) {
                // Verificar que el slug no exista en otra guía
                $stmt = $pdo->prepare("SELECT id FROM guias WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $id]);
                if ($stmt->fetch()) {
                    $mensaje_error = 'El slug ya existe en otra guía';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE guias 
                        SET titulo = ?, slug = ?, descripcion_corta = ?, contenido = ?, categoria = ?, 
                            imagen_url = ?, destacado = ?, activo = ?, fecha_actualizacion = NOW()
                        WHERE id = ?
                    ");
                    
                    $stmt->execute([
                        $titulo,
                        $slug,
                        $descripcion_corta,
                        $contenido,
                        $categoria,
                        $imagen_url ?: null,
                        $destacado,
                        $activo,
                        $id
                    ]);
                    
                    header('Location: admin_guias.php?success=actualizada');
                    exit;
                }
            }
        } catch (Exception $e) {
            error_log("Error al actualizar guía: " . $e->getMessage());
            $mensaje_error = 'Error al actualizar la guía: ' . $e->getMessage();
        }
    }
    
    // Actualizar $guia con los datos del POST para mantenerlos en el formulario
    $guia = array_merge($guia, $_POST);
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
    <title>Editar Guía - Admin</title>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .admin-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
        }
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #0D87A8;
            font-weight: 600;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        .form-group textarea[name="contenido"] {
            min-height: 300px;
        }
        .form-group input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
        }
        .btn-submit {
            background: #0C9268;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
        }
        .btn-submit:hover {
            background: #008a5e;
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-left: 1rem;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .checkbox-group {
            display: flex;
            gap: 2rem;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <div class="admin-container">
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem;">
            <?php include 'components/sidebar_admin.php'; ?>
            
            <div>
                <h1 style="color: #0D87A8; margin-bottom: 2rem; font-size: clamp(1.5rem, 4vw, 2.5rem);">
                    <i class="fas fa-edit"></i> Editar Guía
                </h1>
                
                <?php if ($mensaje_error): ?>
                    <div class="mensaje-error">
                        <?php echo htmlspecialchars($mensaje_error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-container">
                    <form method="POST">
                        <div class="form-group">
                            <label for="titulo">Título *</label>
                            <input type="text" id="titulo" name="titulo" required value="<?php echo htmlspecialchars($guia['titulo'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="slug">Slug (URL amigable) *</label>
                            <input type="text" id="slug" name="slug" required value="<?php echo htmlspecialchars($guia['slug'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="descripcion_corta">Descripción Corta</label>
                            <textarea id="descripcion_corta" name="descripcion_corta"><?php echo htmlspecialchars($guia['descripcion_corta'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="contenido">Contenido Completo *</label>
                            <textarea id="contenido" name="contenido" required><?php echo htmlspecialchars($guia['contenido'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="categoria">Categoría *</label>
                            <select id="categoria" name="categoria" required>
                                <option value="general" <?php echo (($guia['categoria'] ?? 'general') === 'general') ? 'selected' : ''; ?>>General</option>
                                <option value="comprar" <?php echo (($guia['categoria'] ?? '') === 'comprar') ? 'selected' : ''; ?>>Comprar</option>
                                <option value="vender" <?php echo (($guia['categoria'] ?? '') === 'vender') ? 'selected' : ''; ?>>Vender</option>
                                <option value="seguridad" <?php echo (($guia['categoria'] ?? '') === 'seguridad') ? 'selected' : ''; ?>>Seguridad</option>
                                <option value="envios" <?php echo (($guia['categoria'] ?? '') === 'envios') ? 'selected' : ''; ?>>Envíos</option>
                                <option value="pagos" <?php echo (($guia['categoria'] ?? '') === 'pagos') ? 'selected' : ''; ?>>Pagos</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="imagen_url">URL de Imagen</label>
                            <input type="url" id="imagen_url" name="imagen_url" value="<?php echo htmlspecialchars($guia['imagen_url'] ?? ''); ?>" placeholder="https://ejemplo.com/imagen.jpg">
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" name="destacado" value="1" <?php echo ($guia['destacado'] ?? 0) ? 'checked' : ''; ?>>
                                    <span>Destacado</span>
                                </label>
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" name="activo" value="1" <?php echo ($guia['activo'] ?? 1) ? 'checked' : ''; ?>>
                                    <span>Activa</span>
                                </label>
                            </div>
                        </div>
                        
                        <div style="margin-top: 2rem;">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                            <a href="admin_guias.php" class="btn-cancel">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

