<?php
// Manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar dependencias (sin sanitize.php)
try {
    require_once __DIR__ . '/includes/api_helper.php';
    require_once __DIR__ . '/assets/db_direct.php';
} catch (Exception $e) {
    error_log("Error al cargar dependencias en producto.php: " . $e->getMessage());
    die("Error al cargar el sistema");
}

// Función simple de sanitización
function simple_sanitize_int($value, $min = null) {
    $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    $value = filter_var($value, FILTER_VALIDATE_INT);
    if ($value === false) return false;
    if ($min !== null && $value < $min) return false;
    return $value;
}

// Obtener y validar ID del producto de la URL
$producto_id = simple_sanitize_int($_GET['id'] ?? null, 1);

if ($producto_id === false) {
    header('Location: productos.php');
    exit;
}

// Obtener producto directamente de MySQL
try {
    $conn = get_db_connection_php();
    $stmt = $conn->prepare("
        SELECT p.*, u.nombre as vendedor_nombre, u.email as vendedor_email,
               (SELECT url_imagen FROM imagenes_productos WHERE producto_id = p.id AND es_principal = TRUE LIMIT 1) as imagen_principal
        FROM productos p
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    if (!$producto) {
        $_SESSION['error_message'] = 'El producto solicitado no existe.';
        header('Location: productos.php');
        exit;
    }
    
    // Validar que el producto esté activo y no vendido
    if ($producto['activo'] != 1 || $producto['vendido'] == 1) {
        $_SESSION['error_message'] = 'Este producto no está disponible.';
        header('Location: productos.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Error al cargar producto ID {$producto_id}: " . $e->getMessage());
    $_SESSION['error_message'] = 'No se pudo cargar el producto. Por favor, intenta más tarde.';
    header('Location: productos.php');
    exit;
}

$imagen_principal = $producto['imagen_principal'] ?? 'https://via.placeholder.com/800x600?text=Sin+Imagen';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= htmlspecialchars($producto['titulo']) ?> - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <section style="padding: 6rem 0 4rem; background: var(--c-bg, #F2F9FB);">
        <div class="container">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;" id="productDetailLayout">
                
                <!-- Galería de Imágenes -->
                <div style="order: 1;">
                    <!-- Imagen Principal -->
                    <div style="background: white; border-radius: 16px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <img id="mainImage" src="<?= htmlspecialchars($imagen_principal) ?>" alt="<?= htmlspecialchars($producto['titulo']) ?>" style="width: 100%; border-radius: 12px; aspect-ratio: 1/1; object-fit: cover;">
                    </div>
                </div>
                
                <!-- Información del Producto -->
                <div style="order: 2;">
                    <div style="background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <!-- Título y Precio -->
                        <h1 style="font-size: clamp(1.5rem, 4vw, 2rem); margin-bottom: 1rem; line-height: 1.3;"><?= htmlspecialchars($producto['titulo']) ?></h1>
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
                            <span style="font-size: clamp(1.75rem, 5vw, 2.5rem); font-weight: 700; color: #0C9268;">$<?= number_format($producto['precio'], 2) ?></span>
                            <?php if ($producto['precio_original'] && $producto['precio_original'] > $producto['precio']): ?>
                                <?php $descuento = round((($producto['precio_original'] - $producto['precio']) / $producto['precio_original']) * 100); ?>
                                <span style="background: #f0f9f6; color: #0C9268; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.9rem;">-<?= $descuento ?>% de descuento</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Estado y Ubicación -->
                        <div style="border-top: 1px solid #e0e0e0; border-bottom: 1px solid #e0e0e0; padding: 1.5rem 0; margin-bottom: 1.5rem;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                                <div>
                                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 0.25rem;">Estado</p>
                                    <p style="font-weight: 600;"><?= ucfirst(str_replace('_', ' ', $producto['estado_producto'])) ?></p>
                                </div>
                                <div>
                                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 0.25rem;">Stock</p>
                                    <p style="font-weight: 600;"><?= $producto['stock'] ?> disponible(s)</p>
                                </div>
                                <?php if ($producto['ubicacion_ciudad']): ?>
                                <div>
                                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 0.25rem;">Ubicación</p>
                                    <p style="font-weight: 600;"><i class="fas fa-map-marker-alt" style="color: #0C9268;"></i> <?= htmlspecialchars($producto['ubicacion_ciudad']) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Botones de Acción -->
                        <?php if (!is_admin()): ?>
                        <div style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
                            <button id="addToCartBtn" onclick="addToCart()" style="flex: 1; min-width: 200px; padding: 1rem; background: linear-gradient(135deg, #0D87A8, #0C9268); color: white; border: none; border-radius: 8px; font-size: clamp(0.9rem, 2vw, 1rem); font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                                <i class="fas fa-shopping-cart"></i> Agregar al Carrito
                            </button>
                            <button style="width: 50px; min-width: 50px; padding: 1rem; background: white; color: #ff4d4f; border: 2px solid #ff4d4f; border-radius: 8px; cursor: pointer; font-size: 1.25rem; transition: all 0.3s ease; flex-shrink: 0;">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                        <?php else: ?>
                        <div style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; text-align: center;">
                            <i class="fas fa-info-circle"></i> Como administrador, solo puedes revisar el producto.
                        </div>
                        <?php endif; ?>
                        
                        <!-- Descripción -->
                        <?php if ($producto['descripcion']): ?>
                        <div style="margin-bottom: 2rem;">
                            <h3 style="font-size: 1.25rem; margin-bottom: 1rem;">Descripción</h3>
                            <p style="color: #666; line-height: 1.6;"><?= nl2br(htmlspecialchars($producto['descripcion'])) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Info del Vendedor -->
                        <div style="background: #f8f9fa; border-radius: 12px; padding: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #0D87A8, #0C9268); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <h3 style="margin-bottom: 0.25rem;"><?= htmlspecialchars($producto['vendedor_nombre']) ?></h3>
                                    <p style="color: #666; font-size: 0.9rem;">Vendedor</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <style>
    /* Responsividad para página de producto */
    @media (max-width: 768px) {
        #productDetailLayout {
            grid-template-columns: 1fr !important;
            gap: 1.5rem !important;
        }
        
        #productDetailLayout > div:first-child {
            order: 1 !important;
        }
        
        #productDetailLayout > div:last-child {
            order: 2 !important;
        }
    }
    </style>
    <style>
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
    </style>
    <script>
    const PRODUCTO_ID = <?= $producto_id ?>;
    
    function addToCart() {
        <?php if (!is_logged_in()): ?>
            alert('Debes iniciar sesión para agregar productos al carrito');
            window.location.href = 'login.php';
            return;
        <?php endif; ?>
        
        // Validar stock disponible
        const stock = <?= (int)$producto['stock']; ?>;
        if (stock <= 0) {
            alert('Este producto no está disponible en este momento.');
            return;
        }
        
        const btn = document.getElementById('addToCartBtn');
        const originalText = btn.innerHTML;
        
        if (btn.disabled) return; // Prevenir doble clic
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
        
        // Timeout para la petición
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 segundos
        
        fetch('process_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add&producto_id=${PRODUCTO_ID}&cantidad=1`,
            signal: controller.signal
        })
        .then(async response => {
            clearTimeout(timeoutId);
            
            // Manejar sesión expirada
            if (response.status === 401 || response.status === 403) {
                alert('Tu sesión ha expirado. Por favor, inicia sesión nuevamente.');
                window.location.href = 'login.php';
                return null;
            }
            
            // Intentar parsear JSON incluso si hay error HTTP
            let data;
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                try {
                    data = await response.json();
                } catch (e) {
                    console.error('Error al parsear JSON:', e);
                    throw new Error('Error al procesar la respuesta del servidor');
                }
            } else {
                // Si no es JSON, lanzar error con el status
                throw new Error(`Error del servidor (${response.status}). Por favor, intenta más tarde.`);
            }
            
            // Si hay error en la respuesta JSON, mostrarlo
            if (!response.ok) {
                throw new Error(data.error || `Error del servidor (${response.status})`);
            }
            
            return data;
        })
        .then(data => {
            if (!data) return; // Si fue redirigido por sesión expirada
            
            if (data.success) {
                btn.innerHTML = '<i class="fas fa-check"></i> ¡Agregado!';
                btn.style.background = '#28a745';
                
                // Actualizar contador del navbar
                if (typeof window.updateNavbarCounters === 'function') {
                    window.updateNavbarCounters();
                }
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = 'linear-gradient(135deg, #0D87A8, #0C9268)';
                    btn.disabled = false;
                }, 2000);
                
                // Mostrar notificación
                showNotification('Producto agregado al carrito', 'success');
            } else {
                alert(data.error || 'Error al agregar al carrito');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.error('Error:', error);
            
            let errorMessage = 'Error al procesar la solicitud';
            
            if (error.name === 'AbortError') {
                errorMessage = 'La solicitud tardó demasiado. Por favor, verifica tu conexión e intenta de nuevo.';
            } else if (error.message) {
                errorMessage = error.message;
            } else if (error instanceof TypeError && error.message.includes('fetch')) {
                errorMessage = 'No se pudo conectar con el servidor. Por favor, verifica que el backend esté corriendo e intenta de nuevo.';
            } else {
                errorMessage = 'Error de conexión. Por favor, verifica tu internet e intenta de nuevo.';
            }
            
            alert(errorMessage);
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
    
    // Función para mostrar notificaciones
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#0D87A8'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    </script>
</body>
</html>
