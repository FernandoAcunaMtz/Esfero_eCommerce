<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/csrf.php';

require_login();
$user = get_session_user();
$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0D87A8">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Configuración - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .config-container {
            width: 100%;
            margin: 100px auto 50px;
            padding: 2rem;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
        }
        
        .config-sidebar {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .config-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .config-menu li {
            margin-bottom: 0.5rem;
        }
        
        .config-menu a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            color: #666;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .config-menu a:hover,
        .config-menu a.active {
            background: #f0f8ff;
            color: #0D87A8;
        }
        
        .config-content {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .form-section {
            margin-bottom: 3rem;
            padding-bottom: 3rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            color: #0D87A8;
            font-size: 1.5rem;
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
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #0C9268;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .notif-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        @media (max-width: 968px) {
            .config-grid {
                grid-template-columns: 1fr;
            }
            
            .config-sidebar {
                position: static;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .config-menu {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .config-menu li {
                flex: 1;
                min-width: 150px;
            }
        }
        
        @media (max-width: 640px) {
            .config-container {
                padding: 1rem !important;
                margin-top: 80px !important;
            }
            
            .config-content {
                padding: 1.5rem !important;
            }
            
            input, select, textarea {
                font-size: 16px !important; /* Previene zoom en iOS */
            }
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="config-container">
        <h1 style="color: #0D87A8; margin-bottom: 2rem; font-size: 2.5rem;">Configuración de Cuenta</h1>

        <?php if ($flash): ?>
        <div style="
            background: <?= $flash['type'] === 'success' ? '#d1fae5' : '#fee2e2' ?>;
            color:      <?= $flash['type'] === 'success' ? '#065f46' : '#991b1b' ?>;
            border:     1.5px solid <?= $flash['type'] === 'success' ? '#6ee7b7' : '#fca5a5' ?>;
            border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 0.75rem;">
            <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>
        
        <div class="config-grid">
            <aside class="config-sidebar">
                <ul class="config-menu">
                    <li>
                        <a href="#datos" class="active">
                            <i class="fas fa-user"></i>
                            Datos Personales
                        </a>
                    </li>
                    <li>
                        <a href="#seguridad">
                            <i class="fas fa-lock"></i>
                            Seguridad
                        </a>
                    </li>
                    <li>
                        <a href="#notificaciones">
                            <i class="fas fa-bell"></i>
                            Notificaciones
                        </a>
                    </li>
                    <li>
                        <a href="#privacidad">
                            <i class="fas fa-shield-alt"></i>
                            Privacidad
                        </a>
                    </li>
                    <li>
                        <a href="#pagos">
                            <i class="fas fa-credit-card"></i>
                            Métodos de Pago
                        </a>
                    </li>
                </ul>
            </aside>
            
            <div class="config-content">
                <!-- Datos Personales -->
                <div class="form-section" id="datos">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i> Datos Personales
                    </h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" value="Fernando" required>
                        </div>
                        <div class="form-group">
                            <label>Apellido *</label>
                            <input type="text" value="Acuña" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" value="fernando@email.com" required>
                        </div>
                        <div class="form-group">
                            <label>Teléfono *</label>
                            <input type="tel" value="+52 123 456 7890" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Biografía</label>
                        <textarea rows="4" placeholder="Cuéntanos sobre ti..."></textarea>
                    </div>
                    <button class="cta-button">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
                
                <!-- Seguridad -->
                <div class="form-section" id="seguridad">
                    <h2 class="section-title">
                        <i class="fas fa-lock"></i> Seguridad
                    </h2>

                    <form method="POST" action="process_cambiar_password.php" id="formPassword" autocomplete="off">
                        <?= csrf_field() ?>

                        <div class="form-group" style="position:relative;">
                            <label>Contraseña Actual *</label>
                            <input type="password" name="password_actual" id="pw_actual"
                                   placeholder="••••••••" required autocomplete="current-password">
                            <button type="button" class="toggle-pw" data-target="pw_actual"
                                    style="position:absolute;right:12px;top:38px;background:none;border:none;cursor:pointer;color:#4A7585;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="position:relative;">
                                <label>Nueva Contraseña *</label>
                                <input type="password" name="password_nueva" id="pw_nueva"
                                       placeholder="Mín. 8 caracteres" required minlength="8" autocomplete="new-password">
                                <button type="button" class="toggle-pw" data-target="pw_nueva"
                                        style="position:absolute;right:12px;top:38px;background:none;border:none;cursor:pointer;color:#4A7585;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-group" style="position:relative;">
                                <label>Confirmar Nueva Contraseña *</label>
                                <input type="password" name="password_confirm" id="pw_confirm"
                                       placeholder="Repite la contraseña" required minlength="8" autocomplete="new-password">
                                <button type="button" class="toggle-pw" data-target="pw_confirm"
                                        style="position:absolute;right:12px;top:38px;background:none;border:none;cursor:pointer;color:#4A7585;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Indicador de fortaleza -->
                        <div id="pw-strength-wrap" style="margin:-0.5rem 0 1.25rem; display:none;">
                            <div style="height:4px;background:#e5e7eb;border-radius:4px;overflow:hidden;">
                                <div id="pw-strength-bar" style="height:100%;width:0;transition:width 0.3s,background 0.3s;border-radius:4px;"></div>
                            </div>
                            <span id="pw-strength-label" style="font-size:0.78rem;color:#4A7585;margin-top:4px;display:block;"></span>
                        </div>

                        <button type="submit" class="cta-button" id="btnCambiarPw">
                            <i class="fas fa-key"></i> Cambiar Contraseña
                        </button>
                    </form>
                </div>
                
                <!-- Notificaciones -->
                <div class="form-section" id="notificaciones">
                    <h2 class="section-title">
                        <i class="fas fa-bell"></i> Notificaciones
                    </h2>
                    
                    <div class="notif-item">
                        <div>
                            <strong style="color: #0D87A8;">Notificaciones por Email</strong>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">Recibe actualizaciones por correo</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="notif-item">
                        <div>
                            <strong style="color: #0D87A8;">Nuevas Ofertas</strong>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">Alertas de productos y descuentos</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="notif-item">
                        <div>
                            <strong style="color: #0D87A8;">Estado de Pedidos</strong>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">Actualizaciones de tus compras</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="notif-item">
                        <div>
                            <strong style="color: #0D87A8;">Mensajes de Vendedores</strong>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">Comunicación con vendedores</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                
                <!-- Métodos de Pago -->
                <div class="form-section" id="pagos">
                    <h2 class="section-title">
                        <i class="fas fa-credit-card"></i> Métodos de Pago
                    </h2>
                    
                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <i class="fas fa-credit-card" style="font-size: 2rem; color: #0D87A8;"></i>
                                <div>
                                    <strong style="color: #0D87A8;">Visa •••• 4242</strong>
                                    <p style="margin: 0; color: #666; font-size: 0.85rem;">Vence 12/25</p>
                                </div>
                            </div>
                            <button style="background: none; border: none; color: #F6A623; cursor: pointer;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button class="cta-button" style="background: white; color: #0D87A8; border: 2px solid #0D87A8;">
                        <i class="fas fa-plus"></i> Agregar Método de Pago
                    </button>
                </div>
                
                <!-- Zona Peligrosa -->
                <div class="form-section">
                    <h2 class="section-title" style="color: #dc3545;">
                        <i class="fas fa-exclamation-triangle"></i> Zona Peligrosa
                    </h2>
                    <div style="background: #fff5f5; padding: 1.5rem; border-radius: 10px; border: 2px solid #dc3545;">
                        <h3 style="color: #dc3545; margin-bottom: 0.5rem;">Eliminar Cuenta</h3>
                        <p style="color: #666; margin-bottom: 1rem;">Esta acción es permanente y no se puede deshacer</p>
                        <button style="background: #dc3545; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 10px; cursor: pointer; font-weight: bold;">
                            <i class="fas fa-trash-alt"></i> Eliminar Mi Cuenta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script>
    // ── Mostrar / ocultar contraseña ──────────────────────────────────────────
    document.querySelectorAll('.toggle-pw').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            const icon  = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // ── Medidor de fortaleza de contraseña ────────────────────────────────────
    const pwNueva  = document.getElementById('pw_nueva');
    const bar      = document.getElementById('pw-strength-bar');
    const label    = document.getElementById('pw-strength-label');
    const wrap     = document.getElementById('pw-strength-wrap');

    if (pwNueva) {
        pwNueva.addEventListener('input', () => {
            const val = pwNueva.value;
            if (!val) { wrap.style.display = 'none'; return; }
            wrap.style.display = 'block';

            let score = 0;
            if (val.length >= 8)                    score++;
            if (val.length >= 12)                   score++;
            if (/[A-Z]/.test(val))                  score++;
            if (/[0-9]/.test(val))                  score++;
            if (/[^A-Za-z0-9]/.test(val))           score++;

            const levels = [
                { pct: '20%',  color: '#ef4444', text: 'Muy débil'  },
                { pct: '40%',  color: '#f97316', text: 'Débil'      },
                { pct: '60%',  color: '#eab308', text: 'Regular'    },
                { pct: '80%',  color: '#3BBCD8', text: 'Buena'      },
                { pct: '100%', color: '#0FB882', text: 'Muy segura' },
            ];
            const lvl = levels[Math.min(score, 4)];
            bar.style.width      = lvl.pct;
            bar.style.background = lvl.color;
            label.textContent    = lvl.text;
            label.style.color    = lvl.color;
        });
    }

    // ── Validar coincidencia antes de enviar ──────────────────────────────────
    document.getElementById('formPassword')?.addEventListener('submit', function(e) {
        const nueva   = document.getElementById('pw_nueva').value;
        const confirm = document.getElementById('pw_confirm').value;
        if (nueva !== confirm) {
            e.preventDefault();
            alert('Las contraseñas no coinciden.');
            return;
        }
        const btn = document.getElementById('btnCambiarPw');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    });

    // ── Scroll automático a sección con flash message ─────────────────────────
    <?php if ($flash): ?>
    document.addEventListener('DOMContentLoaded', () => {
        const sec = document.getElementById('seguridad');
        if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    <?php endif; ?>
    </script>
</body>
</html>

