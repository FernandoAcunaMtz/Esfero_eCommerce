<?php
/**
 * Activar cuenta de vendedor — Onboarding C2C
 *
 * Un usuario con rol 'usuario' puede solicitar activar su cuenta de vendedor.
 * Flujo simplificado (auto-aprobado): no requiere revisión manual.
 * Si en el futuro se desea revisión, cambiar AUTO_APROBAR a false.
 */

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/csrf.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require_login();  // debe estar autenticado

$user = get_session_user();

// Admin no necesita esto
if ($user['rol'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

// Si ya es vendedor o puede_vender, redirigir
if (puede_vender($user['id'])) {
    header('Location: vendedor_dashboard.php?ya_activo=1');
    exit;
}

// ── Mensajes flash ────────────────────────────────────────────────────────────
$flash = get_flash_message();

// ── Verificar si ya tiene solicitud pendiente ─────────────────────────────────
$solicitud_pendiente = false;
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT estado FROM vendedor_solicitudes WHERE usuario_id = ?");
        $stmt->execute([$user['id']]);
        $sol = $stmt->fetch();
        if ($sol && $sol['estado'] === 'pendiente') $solicitud_pendiente = true;
    } catch (Exception $e) { /* tabla puede no existir aún */ }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Activa tu cuenta de vendedor — Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .onboarding-wrap {
            max-width: 760px;
            margin: 0 auto 60px;
            padding: 5rem 1.5rem 2rem;
        }

        /* Stepper visual */
        .steps-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            margin-bottom: 2.5rem;
        }
        .step-dot {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
        }
        .step-dot .circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .step-dot.done   .circle { background: #16a34a; color: white; }
        .step-dot.active .circle { background: #f97316; color: white; }
        .step-dot.next   .circle { background: #e5e7eb; color: #9ca3af; }
        .step-dot .label { font-size: 0.75rem; color: #6b7280; white-space: nowrap; }
        .step-dot.active .label { color: #f97316; font-weight: 600; }
        .step-line { flex: 1; height: 3px; background: #e5e7eb; margin: 0; max-width: 80px; }
        .step-line.done { background: #16a34a; }

        /* Cards de beneficios */
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.875rem;
            margin-bottom: 2rem;
        }
        .benefit-card {
            background: white;
            border: 1.5px solid #C5DEE8;
            border-radius: 14px;
            padding: 1rem 0.75rem;
            text-align: center;
        }
        .benefit-card i {
            font-size: 1.8rem;
            margin-bottom: 0.6rem;
            display: block;
        }
        .benefit-card h4 { font-size: 0.9rem; margin: 0 0 0.3rem; color: #111827; }
        .benefit-card p  { font-size: 0.8rem; color: #6b7280; margin: 0; }

        /* Formulario */
        .form-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(13,135,168,0.10);
            padding: 2rem 2.5rem;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.88rem;
            color: #374151;
            margin-bottom: 0.45rem;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1.5px solid #C5DEE8;
            border-radius: 10px;
            font-size: 0.9rem;
            color: #111827;
            transition: border-color 0.2s;
            box-sizing: border-box;
            background: white;
        }
        .form-group input:focus,
        .form-group textarea:focus { outline: none; border-color: #0D87A8; box-shadow: 0 0 0 3px rgba(13,135,168,0.12); }
        .form-group textarea { min-height: 90px; resize: vertical; }

        /* Términos */
        .terminos-box {
            background: #F2F9FB;
            border: 1px solid #C5DEE8;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            font-size: 0.83rem;
            color: #4b5563;
            max-height: 140px;
            overflow-y: auto;
            margin-bottom: 0.75rem;
            line-height: 1.6;
        }
        .check-row {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.85rem;
            cursor: pointer;
        }
        .check-row input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #0D87A8;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .check-row span { font-size: 0.88rem; color: #374151; line-height: 1.4; }

        /* Phone hint */
        .phone-hint {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 0.7rem 1rem;
            font-size: 0.84rem;
            color: #78350f;
            margin-bottom: 1.25rem;
        }
        .phone-hint i { color: #d97706; }

        .btn-activar {
            width: 100%;
            padding: 0.95rem;
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
        }
        .btn-activar:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(249,115,22,0.35); }
        .btn-activar:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .pendiente-banner {
            background: #fffbeb;
            border: 1.5px solid #fcd34d;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
        }
        .pendiente-banner i { font-size: 2.5rem; color: #d97706; display: block; margin-bottom: 0.75rem; }

        @media (max-width: 600px) {
            .form-card { padding: 1.25rem 1rem; }
            .benefits-grid { grid-template-columns: 1fr 1fr; }
            .benefit-card { padding: 0.875rem 0.5rem; }
            .benefit-card i { font-size: 1.5rem; }
            .onboarding-wrap { padding: 4.5rem 1rem 2rem; }
        }
        @media (max-width: 360px) {
            .benefits-grid { grid-template-columns: 1fr 1fr; gap: 0.5rem; }
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="onboarding-wrap">

        <!-- Header -->
        <div style="text-align:center; margin-bottom:2rem;">
            <div style="width:70px; height:70px; background:linear-gradient(135deg,#f97316,#ea580c); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; font-size:2rem; color:white;">
                <i class="fas fa-store"></i>
            </div>
            <h1 style="font-size:1.8rem; color:#0B2D3C; margin:0 0 0.5rem;">Activa tu cuenta de vendedor</h1>
            <p style="color:#4A7585; font-size:1rem; margin:0;">
                Empieza a vender en Esfero en menos de 2 minutos
            </p>
        </div>

        <!-- Stepper -->
        <div class="steps-row">
            <div class="step-dot done">
                <div class="circle"><i class="fas fa-check" style="font-size:0.85rem;"></i></div>
                <div class="label">Cuenta creada</div>
            </div>
            <div class="step-line done"></div>
            <div class="step-dot active">
                <div class="circle">2</div>
                <div class="label">Activar vendedor</div>
            </div>
            <div class="step-line"></div>
            <div class="step-dot next">
                <div class="circle">3</div>
                <div class="label">Publicar producto</div>
            </div>
        </div>

        <?php if ($flash): ?>
        <div style="background:<?= $flash['type']==='success'?'#d1fae5':'#fee2e2' ?>; color:<?= $flash['type']==='success'?'#065f46':'#991b1b' ?>; border-radius:12px; padding:1rem 1.25rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.75rem;">
            <i class="fas <?= $flash['type']==='success'?'fa-check-circle':'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <?php if ($solicitud_pendiente): ?>
        <!-- Ya tiene solicitud pendiente -->
        <div class="pendiente-banner">
            <i class="fas fa-clock"></i>
            <h3 style="color:#92400e; margin:0 0 0.5rem;">Solicitud en revisión</h3>
            <p style="color:#78350f; margin:0 0 1.25rem;">
                Tu solicitud para activar la cuenta de vendedor está siendo revisada.
                Te notificaremos por email cuando sea aprobada.
            </p>
            <a href="index.php" style="display:inline-block; padding:0.7rem 1.5rem; background:#f97316; color:white; border-radius:10px; text-decoration:none; font-weight:600;">
                Volver al inicio
            </a>
        </div>

        <?php else: ?>

        <!-- Beneficios -->
        <div class="benefits-grid">
            <div class="benefit-card">
                <i class="fas fa-globe" style="color:#3b82f6;"></i>
                <h4>Alcance nacional</h4>
                <p>Llega a compradores en todo México</p>
            </div>
            <div class="benefit-card">
                <i class="fas fa-shield-alt" style="color:#10b981;"></i>
                <h4>Pagos protegidos</h4>
                <p>Cobra seguro mediante PayPal</p>
            </div>
            <div class="benefit-card">
                <i class="fas fa-chart-line" style="color:#f97316;"></i>
                <h4>Dashboard propio</h4>
                <p>Controla tus ventas y estadísticas</p>
            </div>
            <div class="benefit-card">
                <i class="fas fa-comments" style="color:#8b5cf6;"></i>
                <h4>Chat directo</h4>
                <p>Habla con tus compradores al instante</p>
            </div>
        </div>

        <!-- Formulario de activación -->
        <div class="form-card">
            <h2 style="font-size:1.15rem; margin:0 0 1.5rem; color:#0B2D3C;">
                <i class="fas fa-user-check" style="color:#f97316;"></i>
                Completa tu perfil de vendedor
            </h2>

            <?php
            // Mostrar hint si no tiene teléfono
            $user_data = null;
            if (isset($pdo)) {
                try {
                    $stmt = $pdo->prepare("SELECT telefono, nombre, apellidos FROM usuarios WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $user_data = $stmt->fetch();
                } catch (Exception $e) {}
            }
            $tiene_telefono = !empty($user_data['telefono']);
            ?>

            <?php if (!$tiene_telefono): ?>
            <div class="phone-hint">
                <i class="fas fa-exclamation-triangle"></i>
                <span>
                    Recomendamos agregar un <strong>número de teléfono</strong> antes de vender.
                    Los compradores lo verán como señal de confianza.
                    <a href="perfil.php#configuracion" style="color:#92400e; font-weight:600;">Agregar teléfono →</a>
                </span>
            </div>
            <?php endif; ?>

            <form method="POST" action="process_activar_vendedor.php" id="formActivar">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label><i class="fas fa-tag"></i> ¿Qué planeas vender? <span style="color:#6b7280; font-weight:400;">(opcional)</span></label>
                    <input type="text" name="tipo_productos"
                           placeholder="Ej: Ropa usada, electrónica, artículos deportivos..."
                           maxlength="200">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-comment-alt"></i> Cuéntanos un poco sobre ti como vendedor <span style="color:#6b7280; font-weight:400;">(opcional)</span></label>
                    <textarea name="descripcion" maxlength="500"
                              placeholder="¿Tienes experiencia vendiendo? ¿Tienes negocio propio?..."></textarea>
                </div>

                <!-- Términos y condiciones -->
                <div class="terminos-box">
                    <strong>Términos y Condiciones para Vendedores de Esfero</strong><br><br>
                    Al activar tu cuenta de vendedor en Esfero, aceptas que:<br><br>
                    <strong>1. Publicaciones:</strong> Solo puedes publicar artículos que sean de tu propiedad y que no infrijan derechos de terceros. No se permiten artículos ilegales, falsificados ni prohibidos por la ley mexicana.<br><br>
                    <strong>2. Precios y descripciones:</strong> Los precios y descripciones deben ser verídicos. No se permite publicidad engañosa.<br><br>
                    <strong>3. Transacciones:</strong> Debes completar las ventas que hayas confirmado. Cancelaciones repetidas pueden resultar en suspensión de la cuenta.<br><br>
                    <strong>4. Comunicación:</strong> Debes responder mensajes de compradores en un plazo razonable.<br><br>
                    <strong>5. Calificaciones:</strong> Las calificaciones de compradores son permanentes y públicas.<br><br>
                    <strong>6. Comisiones:</strong> Esfero no cobra comisión por ventas en esta etapa Beta.
                </div>

                <label class="check-row">
                    <input type="checkbox" name="acepto_terminos" value="1" required>
                    <span>He leído y acepto los <strong>Términos y Condiciones para Vendedores</strong> de Esfero.</span>
                </label>

                <label class="check-row">
                    <input type="checkbox" name="acepto_compromiso" value="1" required>
                    <span>Me comprometo a responder mensajes, entregar productos en el plazo acordado y mantener la calidad de mis publicaciones.</span>
                </label>

                <button type="submit" class="btn-activar" id="btnActivar">
                    <i class="fas fa-rocket"></i>
                    Activar mi cuenta de vendedor
                </button>
            </form>

            <p style="text-align:center; font-size:0.8rem; color:#9ca3af; margin-top:1rem;">
                Puedes desactivar tu cuenta de vendedor en cualquier momento desde Configuración.
            </p>
        </div>

        <?php endif; ?>
    </div>

    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
    document.getElementById('formActivar')?.addEventListener('submit', function() {
        const btn = document.getElementById('btnActivar');
        btn.disabled = true;
        btn.innerHTML = '<span style="display:inline-block;width:18px;height:18px;border:2.5px solid rgba(255,255,255,0.4);border-top-color:white;border-radius:50%;animation:spin 0.7s linear infinite;"></span> Activando...';
    });
    </script>
</body>
</html>
