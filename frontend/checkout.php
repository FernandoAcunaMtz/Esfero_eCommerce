<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';

if (is_admin()) { header('Location: admin_dashboard.php'); exit; }
require_login();

$user    = get_session_user();
$user_id = (int)($user['id'] ?? 0);

// ── Cargar carrito desde DB ───────────────────────────────────────────────────
$cart_items = [];
$subtotal   = 0.0;

if ($user_id && isset($pdo)) {
    $stmt = $pdo->prepare("
        SELECT c.id AS carrito_id, c.cantidad,
               p.id AS producto_id, p.titulo, p.precio AS precio_actual,
               p.stock, p.vendedor_id,
               (SELECT url_imagen FROM imagenes_productos WHERE producto_id = p.id AND es_principal = 1 LIMIT 1) AS imagen_principal,
               u.nombre AS vendedor_nombre
        FROM carrito c
        JOIN productos p ON p.id = c.producto_id
        LEFT JOIN usuarios u ON u.id = p.vendedor_id
        WHERE c.usuario_id = ? AND p.activo = 1 AND p.vendido = 0 AND p.stock > 0
        ORDER BY c.fecha_agregado DESC
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll();
    foreach ($cart_items as $item) {
        $subtotal += (float)$item['precio_actual'] * (int)$item['cantidad'];
    }
}

if (empty($cart_items)) {
    header('Location: carrito.php');
    exit;
}

$paypal_client_id = getenv('PAYPAL_CLIENT_ID') ?: '';
$paypal_currency  = getenv('PAYPAL_CURRENCY') ?: 'MXN';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Finalizar Compra — Esfero</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<?php include 'components/navbar.php'; ?>

<section style="padding: 6rem 0 4rem; background: var(--c-bg,#F2F9FB); min-height: 80vh;">
    <div class="container">
        <h1 style="font-size: clamp(1.4rem,4vw,2rem); font-weight:700; color:var(--c-primary,#0D87A8); margin-bottom:2rem;">
            <i class="fas fa-lock"></i> Finalizar Compra
        </h1>

        <div style="display:grid; grid-template-columns:1fr minmax(0,360px); gap:2rem;" id="checkoutLayout">

            <!-- Formulario de envío -->
            <div>
                <form id="checkoutForm" style="background:white; border-radius:14px; padding:1.75rem; box-shadow:var(--shadow-sm);">
                    <div id="checkoutError" style="display:none; background:#fdecea; color:#b02a37; padding:.9rem 1rem; border-radius:8px; margin-bottom:1.25rem; font-size:.9rem;"></div>

                    <h2 style="font-size:1.1rem; font-weight:700; color:var(--c-primary,#0D87A8); margin-bottom:1.25rem;">
                        <i class="fas fa-truck"></i> Información de envío
                    </h2>

                    <?php
                    $inp = 'width:100%; padding:.8rem 1rem; border:1.5px solid #C5DEE8; border-radius:8px; font-size:.95rem; outline:none; font-family:inherit; transition:border-color .15s;';
                    $lbl = 'display:block; margin-bottom:.35rem; font-weight:600; font-size:.87rem; color:#0B2D3C;';
                    $grp = 'margin-bottom:1.1rem;';
                    ?>
                    <div style="<?= $grp ?>">
                        <label style="<?= $lbl ?>">Nombre completo</label>
                        <input type="text" name="nombre_destinatario" required style="<?= $inp ?>"
                               value="<?= htmlspecialchars(trim(($user['nombre'] ?? '') . ' ' . ($user['apellidos'] ?? ''))) ?>"
                               placeholder="Tu nombre completo">
                    </div>
                    <div style="<?= $grp ?>">
                        <label style="<?= $lbl ?>">Dirección</label>
                        <input type="text" name="direccion_envio" required style="<?= $inp ?>" placeholder="Calle, número, colonia">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:.9rem; <?= $grp ?>">
                        <div>
                            <label style="<?= $lbl ?>">Ciudad</label>
                            <input type="text" name="ciudad_envio" required style="<?= $inp ?>" placeholder="Ciudad">
                        </div>
                        <div>
                            <label style="<?= $lbl ?>">Estado</label>
                            <input type="text" name="estado_envio" required style="<?= $inp ?>" placeholder="Estado">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:.9rem; <?= $grp ?>">
                        <div>
                            <label style="<?= $lbl ?>">Código postal</label>
                            <input type="text" name="codigo_postal_envio" required style="<?= $inp ?>" placeholder="00000" maxlength="6">
                        </div>
                        <div>
                            <label style="<?= $lbl ?>">Teléfono</label>
                            <input type="tel" name="telefono_envio" required style="<?= $inp ?>" placeholder="+52 55 0000 0000"
                                   value="<?= htmlspecialchars($user['telefono'] ?? '') ?>">
                        </div>
                    </div>
                    <div style="<?= $grp ?>">
                        <label style="<?= $lbl ?>">Notas para el vendedor <span style="font-weight:400; color:#4A7585;">(opcional)</span></label>
                        <textarea name="notas_comprador" rows="2" style="<?= $inp ?> resize:vertical;" placeholder="Instrucciones especiales..."></textarea>
                    </div>

                    <!-- Botón PayPal (se reemplaza con el widget) -->
                    <div id="paypal-button-container" style="display:none; margin-top:.5rem;"></div>
                    <button type="submit" id="continueBtn"
                            style="width:100%; padding:1rem; background:linear-gradient(135deg,#0D87A8,#0C9268); color:white; border:none; border-radius:999px; font-size:1rem; font-weight:600; cursor:pointer; margin-top:.5rem;">
                        Continuar al pago <i class="fab fa-paypal" style="margin-left:.4rem;"></i>
                    </button>
                </form>
            </div>

            <!-- Resumen -->
            <aside>
                <div style="background:white; border-radius:14px; padding:1.5rem; position:sticky; top:90px; box-shadow:var(--shadow-sm);">
                    <h3 style="font-size:1rem; font-weight:700; color:var(--c-primary,#0D87A8); margin-bottom:1rem;">Tu pedido</h3>

                    <div style="max-height:260px; overflow-y:auto; margin-bottom:1rem;">
                    <?php foreach ($cart_items as $item):
                        $img = htmlspecialchars($item['imagen_principal'] ?: 'https://placehold.co/60x60?text=?');
                    ?>
                        <div style="display:flex; gap:.75rem; margin-bottom:.9rem; padding-bottom:.9rem; border-bottom:1px solid #edf2f4;">
                            <img src="<?= $img ?>" style="width:52px; height:52px; border-radius:8px; object-fit:cover; flex-shrink:0;" loading="lazy">
                            <div style="flex:1; min-width:0;">
                                <p style="font-size:.85rem; font-weight:600; margin-bottom:.15rem; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;">
                                    <?= htmlspecialchars($item['titulo']) ?>
                                </p>
                                <p style="font-size:.8rem; color:#4A7585;">Cant: <?= (int)$item['cantidad'] ?></p>
                                <p style="font-size:.88rem; font-weight:700; color:#0C9268;">
                                    $<?= number_format((float)$item['precio_actual'] * (int)$item['cantidad'], 2) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>

                    <div style="border-top:1px solid #edf2f4; padding-top:1rem;">
                        <div style="display:flex; justify-content:space-between; font-size:.88rem; color:#4A7585; margin-bottom:.45rem;">
                            <span>Subtotal</span><span>$<?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:.88rem; color:#4A7585; margin-bottom:.75rem;">
                            <span>Envío</span><span style="color:#0C9268; font-weight:600;">Gratis</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:1.1rem; font-weight:700;">
                            <span>Total</span>
                            <span style="color:#0C9268;">$<?= number_format($subtotal, 2) ?> <?= htmlspecialchars($paypal_currency) ?></span>
                        </div>
                    </div>

                    <div style="margin-top:1rem; padding:.75rem; background:#f0f9f6; border-radius:8px; text-align:center; font-size:.8rem; color:#4A7585;">
                        <i class="fas fa-shield-halved" style="color:#0C9268;"></i>
                        Pago 100% seguro con PayPal
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php include 'components/footer.php'; ?>
<script src="assets/js/main.js"></script>
<style>
@media (max-width: 860px) {
    #checkoutLayout { grid-template-columns: 1fr !important; }
    #checkoutLayout aside > div { position: static !important; }
    #checkoutLayout form div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
}
input:focus, textarea:focus { border-color: #0C9268 !important; box-shadow: 0 0 0 3px rgba(0,166,118,.12); }
</style>

<script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($paypal_client_id) ?>&currency=<?= htmlspecialchars($paypal_currency) ?>"></script>
<script>
let shippingData = null;

function showError(msg) {
    const el = document.getElementById('checkoutError');
    el.textContent = msg;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function validateForm() {
    const f = document.getElementById('checkoutForm');
    const fields = [
        { name: 'nombre_destinatario', label: 'Nombre completo', min: 3 },
        { name: 'direccion_envio',     label: 'Dirección', min: 5 },
        { name: 'ciudad_envio',        label: 'Ciudad', min: 2 },
        { name: 'estado_envio',        label: 'Estado', min: 2 },
        { name: 'codigo_postal_envio', label: 'Código postal', pattern: /^\d{4,6}$/, patternMsg: 'Código postal: 4-6 dígitos' },
        { name: 'telefono_envio',      label: 'Teléfono', pattern: /^\+?[\d\s\-]{7,15}$/, patternMsg: 'Teléfono inválido' },
    ];
    for (const r of fields) {
        const el = f.querySelector(`[name="${r.name}"]`);
        const v  = el ? el.value.trim() : '';
        if (!v)                           { showError(r.label + ' es requerido.'); el?.focus(); return false; }
        if (r.min && v.length < r.min)    { showError(r.label + ' es demasiado corto.'); el?.focus(); return false; }
        if (r.pattern && !r.pattern.test(v)) { showError(r.patternMsg); el?.focus(); return false; }
    }
    return true;
}

function collectForm() {
    const f  = document.getElementById('checkoutForm');
    const fd = new FormData(f);
    const obj = {};
    fd.forEach((v, k) => obj[k] = v.trim());
    return obj;
}

// Paso 1: validar formulario → mostrar botón PayPal
document.getElementById('continueBtn').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('checkoutError').style.display = 'none';
    if (!validateForm()) return;
    shippingData = collectForm();

    this.style.display = 'none';
    document.getElementById('paypal-button-container').style.display = 'block';
});

// Paso 2: PayPal JS SDK
paypal.Buttons({
    style: { layout: 'vertical', color: 'blue', shape: 'pill', label: 'pay' },

    createOrder: function() {
        return fetch('process_checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(shippingData)
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Error al crear la orden');
            return data.paypal_order_id;
        });
    },

    onApprove: function(data) {
        return fetch('paypal_capture.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ paypal_order_id: data.orderID })
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;';
                overlay.innerHTML = `
                  <div style="background:white;border-radius:20px;padding:2.5rem 2rem;max-width:380px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.2);">
                    <div style="width:72px;height:72px;background:#e6f9f3;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;">
                      <i class="fas fa-check" style="font-size:2rem;color:#0C9268;"></i>
                    </div>
                    <h2 style="font-size:1.35rem;font-weight:700;color:#0D87A8;margin-bottom:.6rem;">¡Compra exitosa!</h2>
                    <p style="color:#4A7585;font-size:.95rem;margin-bottom:1.5rem;">Tu pago fue procesado correctamente. Serás redirigido a tus compras.</p>
                    <div style="width:100%;height:4px;background:#edf2f4;border-radius:2px;overflow:hidden;">
                      <div id="successBar" style="height:100%;width:0%;background:linear-gradient(90deg,#0D87A8,#0C9268);border-radius:2px;transition:width 2.8s linear;"></div>
                    </div>
                  </div>`;
                document.body.appendChild(overlay);
                requestAnimationFrame(() => {
                    document.getElementById('successBar').style.width = '100%';
                });
                setTimeout(() => {
                    window.location.href = 'perfil.php?tab=compras';
                }, 3000);
            } else {
                showError(result.error || 'Error al procesar el pago');
                document.getElementById('continueBtn').style.display = 'block';
                document.getElementById('paypal-button-container').style.display = 'none';
            }
        });
    },

    onError: function(err) {
        console.error('PayPal error:', err);
        showError('Error en el proceso de pago. Intenta de nuevo.');
        document.getElementById('continueBtn').style.display = 'block';
        document.getElementById('paypal-button-container').style.display = 'none';
    },

    onCancel: function() {
        document.getElementById('continueBtn').style.display = 'block';
        document.getElementById('paypal-button-container').style.display = 'none';
    }
}).render('#paypal-button-container');
</script>
</body>
</html>
