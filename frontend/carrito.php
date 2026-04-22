<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';

if (is_admin()) {
    header('Location: admin_dashboard.php');
    exit;
}
require_login();

$user    = get_session_user();
$user_id = (int)($user['id'] ?? 0);

$cart_items = [];
$subtotal   = 0.0;

if ($user_id && isset($pdo)) {
    $stmt = $pdo->prepare("
        SELECT
            c.id            AS carrito_id,
            c.cantidad,
            c.precio_momento,
            p.id            AS producto_id,
            p.titulo,
            p.precio        AS precio_actual,
            p.stock,
            p.activo,
            p.vendido,
            (SELECT url_imagen FROM imagenes_productos WHERE producto_id = p.id AND es_principal = 1 LIMIT 1) AS imagen_principal,
            u.nombre        AS vendedor_nombre
        FROM carrito c
        JOIN productos p ON p.id = c.producto_id
        LEFT JOIN usuarios u ON u.id = p.vendedor_id
        WHERE c.usuario_id = ?
        ORDER BY c.fecha_agregado DESC
    ");
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $disponible = ($row['stock'] >= $row['cantidad'] && $row['activo'] == 1 && $row['vendido'] == 0);
        $row['disponible'] = $disponible;
        if ($disponible) {
            $subtotal += (float)$row['precio_actual'] * (int)$row['cantidad'];
        }
        $cart_items[] = $row;
    }
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
    <title>Carrito de Compras — Esfero</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<?php include 'components/navbar.php'; ?>

<section style="padding: 6rem 0 4rem; background: var(--c-bg, #F2F9FB); min-height: 80vh;">
    <div class="container">
        <h1 style="font-size: clamp(1.5rem, 4vw, 2rem); font-weight: 700; color: var(--c-primary,#0D87A8); margin-bottom: 2rem;">
            <i class="fas fa-shopping-cart"></i> Carrito de Compras
        </h1>

        <?php if (empty($cart_items)): ?>
        <div style="background: white; border-radius: 18px; padding: 4rem 2rem; text-align: center; box-shadow: var(--shadow-sm);">
            <i class="fas fa-cart-shopping" style="font-size: 3.5rem; color: #c9d6df; margin-bottom: 1.25rem; display: block;"></i>
            <h2 style="color: var(--c-text,#0B2D3C); margin-bottom: 0.75rem;">Tu carrito está vacío</h2>
            <p style="color: var(--c-muted,#4A7585); margin-bottom: 2rem;">Explora nuestros productos y encuentra lo que necesitas.</p>
            <a href="productos.php" class="cta-button" style="display: inline-block;">Ver productos</a>
        </div>

        <?php else: ?>
        <div style="display: grid; grid-template-columns: 1fr minmax(0, 360px); gap: 2rem;" id="cartLayout">

            <!-- Items -->
            <div id="cartItems">
                <?php foreach ($cart_items as $item):
                    $img = htmlspecialchars($item['imagen_principal'] ?: 'https://placehold.co/120x120?text=Sin+imagen');
                ?>
                <div class="cart-item" data-carrito-id="<?= (int)$item['carrito_id'] ?>"
                     style="background: white; border-radius: 14px; padding: 1.25rem; margin-bottom: 1rem;
                            display: flex; gap: 1.25rem; align-items: flex-start;
                            box-shadow: var(--shadow-sm); <?= !$item['disponible'] ? 'opacity:.55;' : '' ?>">

                    <img src="<?= $img ?>" loading="lazy" decoding="async"
                         style="width:100px; height:100px; border-radius:10px; object-fit:cover; flex-shrink:0;"
                         alt="<?= htmlspecialchars($item['titulo']) ?>">

                    <div style="flex:1; min-width:0;">
                        <h3 style="font-size:.95rem; font-weight:600; margin-bottom:.3rem; color:var(--c-text,#0B2D3C);">
                            <?= htmlspecialchars($item['titulo']) ?>
                        </h3>
                        <p style="font-size:.82rem; color:var(--c-muted,#4A7585); margin-bottom:.5rem;">
                            Vendedor: <?= htmlspecialchars($item['vendedor_nombre'] ?? '—') ?>
                        </p>
                        <?php if (!$item['disponible']): ?>
                            <p style="color:#dc3545; font-size:.82rem; font-weight:600;">
                                <i class="fas fa-triangle-exclamation"></i> Producto no disponible
                            </p>
                        <?php else: ?>
                        <p style="font-size:1.15rem; font-weight:700; color:var(--c-accent-dark,#0C9268); margin-bottom:.5rem;">
                            $<?= number_format($item['precio_actual'], 2) ?>
                        </p>
                        <div style="display:flex; align-items:center; gap:.5rem;">
                            <button onclick="changeQty(<?= (int)$item['carrito_id'] ?>, <?= (int)$item['cantidad'] - 1 ?>)"
                                    style="width:28px; height:28px; border:1px solid #C5DEE8; border-radius:6px; background:white; cursor:pointer; font-size:1rem; line-height:1;"
                                    <?= $item['cantidad'] <= 1 ? 'disabled' : '' ?>>−</button>
                            <span style="min-width:22px; text-align:center; font-weight:600;"><?= (int)$item['cantidad'] ?></span>
                            <button onclick="changeQty(<?= (int)$item['carrito_id'] ?>, <?= (int)$item['cantidad'] + 1 ?>)"
                                    style="width:28px; height:28px; border:1px solid #C5DEE8; border-radius:6px; background:white; cursor:pointer; font-size:1rem; line-height:1;"
                                    <?= $item['cantidad'] >= $item['stock'] ? 'disabled' : '' ?>>+</button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <button onclick="removeItem(<?= (int)$item['carrito_id'] ?>)"
                            title="Eliminar"
                            style="background:none; border:none; color:#aaa; cursor:pointer; font-size:1.1rem; padding:.25rem; flex-shrink:0; transition:color .15s;"
                            onmouseover="this.style.color='#dc3545'" onmouseout="this.style.color='#aaa'">
                        <i class="fas fa-trash-can"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Resumen -->
            <aside>
                <div style="background:white; border-radius:14px; padding:1.75rem; position:sticky; top:90px; box-shadow:var(--shadow-sm);">
                    <h3 style="font-size:1.1rem; font-weight:700; margin-bottom:1.25rem; color:var(--c-primary,#0D87A8);">Resumen de compra</h3>

                    <div style="display:flex; justify-content:space-between; margin-bottom:.6rem; color:var(--c-muted,#4A7585); font-size:.9rem;">
                        <span>Subtotal (<?= count($cart_items) ?> artículo<?= count($cart_items) != 1 ? 's' : '' ?>)</span>
                        <span>$<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:1rem; color:var(--c-muted,#4A7585); font-size:.9rem;">
                        <span>Envío</span>
                        <span style="color:#0C9268; font-weight:600;">Gratis</span>
                    </div>
                    <div style="border-top:1px solid #edf2f4; padding-top:1rem; display:flex; justify-content:space-between; font-size:1.1rem; font-weight:700; margin-bottom:1.5rem;">
                        <span>Total</span>
                        <span style="color:var(--c-accent-dark,#0C9268);">$<?= number_format($subtotal, 2) ?> MXN</span>
                    </div>

                    <a href="checkout.php" class="cta-button" style="display:block; text-align:center; margin-bottom:.75rem;">
                        <i class="fas fa-lock" style="font-size:.85rem; margin-right:.4rem;"></i> Proceder al pago
                    </a>
                    <a href="productos.php" style="display:block; text-align:center; padding:.75rem; background:#F2F9FB; border-radius:999px; color:var(--c-primary,#0D87A8); font-weight:600; text-decoration:none; font-size:.9rem;">
                        Seguir comprando
                    </a>
                </div>
            </aside>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'components/footer.php'; ?>
<script src="assets/js/main.js"></script>
<style>
@media (max-width: 860px) {
    #cartLayout { grid-template-columns: 1fr !important; }
    #cartLayout aside > div { position: static !important; }
}
</style>
<script>
function cartFetch(body, onSuccess) {
    fetch('process_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { onSuccess(data); }
        else { alert(data.error || 'Error al actualizar el carrito'); }
    })
    .catch(() => alert('Error de conexión. Intenta de nuevo.'));
}

function changeQty(id, qty) {
    if (qty < 1) return;
    cartFetch(`action=update&carrito_id=${id}&cantidad=${qty}`, () => location.reload());
}

function removeItem(id) {
    if (!confirm('¿Eliminar este producto del carrito?')) return;
    cartFetch(`action=remove&carrito_id=${id}`, () => location.reload());
}
</script>
</body>
</html>
