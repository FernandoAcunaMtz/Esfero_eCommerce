<?php
session_start();
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/db_connection.php';

// Bloquear publicar productos para administradores
if (is_admin()) {
    $_SESSION['error_message'] = 'Los administradores no pueden publicar productos.';
    header('Location: admin_dashboard.php');
    exit;
}

require_login();

// Obtener categorías de la base de datos
$categorias = [];
if (function_exists('getCategorias')) {
    $categorias = getCategorias();
}

// Mapeo de estados del formulario a estados de la BD
$estados_map = [
    'Como nuevo' => 'nuevo',
    'Excelente' => 'excelente',
    'Muy bueno' => 'bueno',
    'Bueno' => 'regular',
    'Usado' => 'para_repuesto'
];

// Mostrar mensajes de éxito/error
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
$form_data = $_SESSION['form_data'] ?? [];

unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
unset($_SESSION['form_data']);
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
    <title>Publicar Producto - Esfero</title>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .publicar-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
        }
        
        .publicar-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
        }
        
        .form-publicar {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .form-section {
            margin-bottom: 2.5rem;
            padding-bottom: 2.5rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            color: #0D87A8;
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            color: #0D87A8;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0D87A8;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        /* Responsividad */
        @media (max-width: 968px) {
            .publicar-grid {
                grid-template-columns: 1fr !important;
            }
            
            .form-row {
                grid-template-columns: 1fr !important;
            }
            
            .publicar-container {
                padding: 1rem !important;
                margin-top: 80px !important;
            }
            
            .form-publicar {
                padding: 1.5rem !important;
            }
        }
        
        @media (max-width: 640px) {
            input, select, textarea {
                font-size: 16px !important; /* Previene zoom en iOS */
            }
        }
        
        .upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            border-color: #0D87A8;
            background: #f0f8ff;
        }
        
        .upload-icon {
            font-size: 3rem;
            color: #0D87A8;
            margin-bottom: 1rem;
        }
        
        .imagenes-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .imagen-preview-item {
            position: relative;
            width: 100%;
            height: 120px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .btn-eliminar-img {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 30px;
            height: 30px;
            background: rgba(220, 53, 69, 0.9);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (max-width: 968px) {
            .publicar-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="publicar-container">
        <h1 style="color: #0D87A8; margin-bottom: 2rem; font-size: clamp(1.5rem, 4vw, 2.5rem);">
            <i class="fas fa-plus-circle"></i> Publicar Producto
        </h1>
        
        <div class="publicar-grid">
            <?php include 'components/sidebar_vendedor.php'; ?>
            
            <form class="form-publicar" method="POST" action="process_publicar_producto.php" enctype="multipart/form-data">
                <!-- Información Básica -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i> Información Básica
                    </h2>
                    <?php if ($error_message): ?>
                    <div style="background: #fdecea; color: #b71c1c; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                    <div style="background: #e8fff6; color: #0C9268; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Título del Producto *</label>
                        <input type="text" name="titulo" placeholder="Ej: iPhone 13 128GB Azul Como Nuevo" 
                               value="<?php echo htmlspecialchars($form_data['titulo'] ?? ''); ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Categoría *</label>
                            <select name="categoria_id" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>" 
                                        <?php echo (isset($form_data['categoria_id']) && $form_data['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Estado *</label>
                            <select name="estado_producto" required>
                                <option value="">Selecciona...</option>
                                <option value="nuevo" <?php echo (isset($form_data['estado_producto']) && $form_data['estado_producto'] == 'nuevo') ? 'selected' : ''; ?>>Como nuevo</option>
                                <option value="excelente" <?php echo (isset($form_data['estado_producto']) && $form_data['estado_producto'] == 'excelente') ? 'selected' : ''; ?>>Excelente</option>
                                <option value="bueno" <?php echo (isset($form_data['estado_producto']) && $form_data['estado_producto'] == 'bueno') ? 'selected' : ''; ?>>Muy bueno</option>
                                <option value="regular" <?php echo (isset($form_data['estado_producto']) && $form_data['estado_producto'] == 'regular') ? 'selected' : ''; ?>>Bueno</option>
                                <option value="para_repuesto" <?php echo (isset($form_data['estado_producto']) && $form_data['estado_producto'] == 'para_repuesto') ? 'selected' : ''; ?>>Usado</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descripción *</label>
                        <textarea name="descripcion" rows="6" placeholder="Describe tu producto detalladamente: estado, uso, accesorios incluidos, etc." required><?php echo htmlspecialchars($form_data['descripcion'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Precio y Stock -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-dollar-sign"></i> Precio y Disponibilidad
                    </h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Precio *</label>
                            <input type="number" name="precio" placeholder="0.00" step="0.01" 
                                   value="<?php echo htmlspecialchars($form_data['precio'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Precio Anterior (Opcional)</label>
                            <input type="number" name="precio_original" placeholder="0.00" step="0.01"
                                   value="<?php echo htmlspecialchars($form_data['precio_original'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Cantidad Disponible *</label>
                            <input type="number" name="stock" value="<?php echo htmlspecialchars($form_data['stock'] ?? '1'); ?>" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>SKU (Opcional)</label>
                            <input type="text" name="sku" placeholder="SKU-001"
                                   value="<?php echo htmlspecialchars($form_data['sku'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Imágenes -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-images"></i> Imágenes del Producto
                    </h2>
                    <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <h3 style="color: #0D87A8; margin-bottom: 0.5rem;">Arrastra tus imágenes aquí</h3>
                        <p style="color: #666; margin: 0;">o haz clic para seleccionar archivos</p>
                        <p style="color: #999; font-size: 0.85rem; margin-top: 0.5rem;">Máximo 10 imágenes • JPG, PNG • Máx 5MB cada una</p>
                        <input type="file" id="fileInput" name="imagenes[]" multiple accept="image/*" style="display: none;" onchange="previewImages(event)">
                    </div>
                    <div id="imagenes-preview" class="imagenes-preview"></div>
                </div>
                
                <!-- Ubicación y Envío -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-map-marker-alt"></i> Ubicación y Envío
                    </h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Estado *</label>
                            <select name="ubicacion_estado" required>
                                <option value="">Selecciona...</option>
                                <option value="CDMX" <?php echo (isset($form_data['ubicacion_estado']) && $form_data['ubicacion_estado'] == 'CDMX') ? 'selected' : ''; ?>>CDMX</option>
                                <option value="Estado de México" <?php echo (isset($form_data['ubicacion_estado']) && $form_data['ubicacion_estado'] == 'Estado de México') ? 'selected' : ''; ?>>Estado de México</option>
                                <option value="Jalisco" <?php echo (isset($form_data['ubicacion_estado']) && $form_data['ubicacion_estado'] == 'Jalisco') ? 'selected' : ''; ?>>Jalisco</option>
                                <option value="Nuevo León" <?php echo (isset($form_data['ubicacion_estado']) && $form_data['ubicacion_estado'] == 'Nuevo León') ? 'selected' : ''; ?>>Nuevo León</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ciudad *</label>
                            <input type="text" name="ubicacion_ciudad" placeholder="Ciudad" 
                                   value="<?php echo htmlspecialchars($form_data['ubicacion_ciudad'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; max-width: 100%; white-space: normal; word-break: break-word;">
                            <input type="checkbox" name="envio_disponible" value="1" style="flex-shrink: 0;"
                                   <?php echo (isset($form_data['envio_disponible']) || !isset($form_data)) ? 'checked' : ''; ?>>
                            Ofrecer envío
                        </label>
                    </div>
                    <div class="form-group">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; max-width: 100%; white-space: normal; word-break: break-word;">
                            <input type="checkbox" name="envio_gratis" value="1" style="flex-shrink: 0;"
                                   <?php echo (isset($form_data['envio_gratis'])) ? 'checked' : ''; ?>>
                            Envío gratis
                        </label>
                    </div>
                </div>
                
                <!-- Botones -->
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="cta-button" style="flex: 1;">
                        <i class="fas fa-check"></i> Publicar Producto
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
    <script>
        function previewImages(event) {
            const preview = document.getElementById('imagenes-preview');
            const files = event.target.files;
            
            // Limpiar preview anterior
            preview.innerHTML = '';
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'imagen-preview-item';
                    div.innerHTML = `
                        <img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">
                        <button type="button" class="btn-eliminar-img" onclick="this.parentElement.remove(); updateFileInput();">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    preview.appendChild(div);
                };
                
                reader.readAsDataURL(file);
            }
        }
        
        // Actualizar el input de archivos cuando se elimina una imagen del preview
        function updateFileInput() {
            // Esta función se puede mejorar para sincronizar el input con el preview
            // Por ahora, el formulario enviará todos los archivos seleccionados
        }
    </script>
</body>
</html>

