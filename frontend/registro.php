<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/api_helper.php';

$register_error = $_SESSION['register_error'] ?? null;
unset($_SESSION['register_error']);
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
    <title>Crear Cuenta - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <!-- Botón discreto para volver al inicio -->
    <div class="back-to-home">
        <a href="index.php" title="Volver al inicio">
            <i class="fas fa-arrow-left"></i>
            <span>Inicio</span>
        </a>
    </div>
    
    <section style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #044E65 0%, #0D87A8 50%, #0C9268 100%); padding: 2rem; padding-top: 4rem;">
        <div style="background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 900px; width: 100%; display: grid; grid-template-columns: 1fr 1fr; overflow: hidden;">
            
            <!-- Panel Izquierdo - Información -->
            <div style="background: linear-gradient(135deg, #0D87A8, #0C9268); color: white; padding: 3rem; display: flex; flex-direction: column; justify-content: center;">
                <h2 style="font-size: 2rem; margin-bottom: 1rem;">Únete a Esfero</h2>
                <p style="margin-bottom: 2rem; opacity: 0.9;">Crea tu cuenta y empieza a comprar y vender de forma segura</p>
                
                <ul style="list-style: none; margin-bottom: 2rem;">
                    <li style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                        <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                        <span>Registro rápido y sencillo</span>
                    </li>
                    <li style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                        <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                        <span>Compra y vende con seguridad</span>
                    </li>
                    <li style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                        <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                        <span>Protección al comprador</span>
                    </li>
                    <li style="display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                        <span>Sin comisiones ocultas</span>
                    </li>
                </ul>
                
                <p style="font-size: 0.9rem; opacity: 0.8;">¿Ya tienes cuenta? <a href="login.php" style="color: white; font-weight: 600; text-decoration: underline;">Inicia sesión aquí</a></p>
            </div>
            
            <!-- Panel Derecho - Formulario de Registro -->
            <div style="padding: 3rem;">
                <h2 style="font-size: 2rem; margin-bottom: 0.5rem; color: #0D87A8;">Crear cuenta</h2>
                <p style="color: #666; margin-bottom: 2rem;">Completa tus datos para registrarte</p>
                
                <?php
                if ($register_error) {
                    echo '<div style="background: #fee; border: 1px solid #fcc; color: #c00; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">' . htmlspecialchars($register_error) . '</div>';
                }
                ?>
                
                <form method="POST" action="process_registro.php">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Nombre</label>
                            <input type="text" name="nombre" required style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem;" placeholder="Juan">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Apellidos</label>
                            <input type="text" name="apellidos" required style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem;" placeholder="Pérez">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Correo electrónico</label>
                        <input type="email" name="email" required style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem;" placeholder="tu@email.com">
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Contraseña</label>
                        <input type="password" name="password" required style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem;" placeholder="••••••••">
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Confirmar contraseña</label>
                        <input type="password" name="password_confirm" required style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem;" placeholder="••••••••">
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="acepta_terminos" required style="width: 18px; height: 18px;">
                            <span style="color: #666; font-size: 0.9rem;">Acepto los <a href="#" style="color: #0C9268;">términos y condiciones</a></span>
                        </label>
                    </div>
                    
                    <input type="hidden" name="rol" value="usuario">
                    <button type="submit" style="width: 100%; padding: 1rem; background: linear-gradient(135deg, #0D87A8, #0C9268); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">
                        Crear cuenta
                    </button>
                </form>
            </div>
            
        </div>
    </section>
    
    <style>
        /* Estilos responsivos para botón de inicio */
        .back-to-home {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 10;
        }
        
        .back-to-home a {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            background: rgba(255,255,255,0.95);
            color: #0D87A8;
            text-decoration: none;
            font-size: 0.85rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .back-to-home a:hover {
            background: rgba(255,255,255,1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .back-to-home a:active {
            transform: translateY(0);
        }
        
        /* Responsivo para móviles */
        @media (max-width: 768px) {
            .back-to-home {
                top: 0.75rem;
                left: 0.75rem;
            }
            
            .back-to-home a {
                padding: 0.35rem 0.6rem;
                font-size: 0.8rem;
            }
            
            .back-to-home a span {
                display: none; /* Ocultar texto en móviles muy pequeños */
            }
            
            .back-to-home a i {
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 480px) {
            .back-to-home a span {
                display: none; /* Solo mostrar icono en móviles pequeños */
            }
            
            .back-to-home a {
                padding: 0.4rem;
                width: 36px;
                height: 36px;
                justify-content: center;
                border-radius: 50%;
            }
        }
        
        /* Asegurar que el contenido no se oculte detrás del botón */
        @media (max-width: 768px) {
            section {
                padding-top: 4.5rem !important;
            }
            
            /* Layout responsivo para móviles */
            section > div {
                grid-template-columns: 1fr !important;
                max-width: 100% !important;
            }
            
            section > div > div:first-child {
                display: none; /* Ocultar panel izquierdo en móviles */
            }
        }
        
        @media (max-width: 480px) {
            section {
                padding: 1rem !important;
                padding-top: 4rem !important;
            }
            
            section > div {
                border-radius: 12px !important;
            }
            
            section > div > div:last-child {
                padding: 2rem 1.5rem !important;
            }
            
            section > div > div:last-child h2 {
                font-size: 1.5rem !important;
            }
            
            section > div > div:last-child form > div[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    <script src="assets/js/main.js"></script>
</body>
</html>

