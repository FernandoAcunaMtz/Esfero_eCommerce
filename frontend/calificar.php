<?php
/**
 * Calificar vendedor y producto tras completar una compra.
 *
 * URL: calificar.php?orden_id=X
 * Solo el comprador de la orden puede calificar, y solo una vez por orden.
 */

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/csrf.php';
session_start();

require_login();
$user = get_session_user();

// Admins no compran
if ($user['rol'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

$orden_id = (int)($_GET['orden_id'] ?? 0);

if ($orden_id < 1) {
    redirect_with_message('compras.php', 'Orden no válida.', 'error');
    exit;
}

if (!isset($pdo)) {
    redirect_with_message('compras.php', 'Error de conexión.', 'error');
    exit;
}

// ── Cargar la orden ──────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        "SELECT o.*,
                vc.nombre AS vendedor_nombre, vc.apellidos AS vendedor_apellidos,
                pv.foto_perfil AS vendedor_foto, pv.calificacion_promedio AS vendedor_cal
         FROM ordenes o
         JOIN usuarios vc ON vc.id = o.vendedor_id
         LEFT JOIN perfiles pv ON pv.usuario_id = o.vendedor_id
         WHERE o.id = ? AND o.comprador_id = ?"
    );
    $stmt->execute([$orden_id, $user['id']]);
    $orden = $stmt->fetch();
} catch (Exception $e) {
    redirect_with_message('compras.php', 'Error al cargar la orden.', 'error');
    exit;
}

if (!$orden) {
    redirect_with_message('compras.php', 'No tienes acceso a esa orden.', 'error');
    exit;
}

// Solo se puede calificar si el pago fue completado
if ($orden['estado_pago'] !== 'completado') {
    redirect_with_message('compras.php', 'Solo puedes calificar órdenes completadas.', 'error');
    exit;
}

// Verificar si ya calificó esta orden
try {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM calificaciones WHERE orden_id = ? AND calificador_id = ?"
    );
    $stmt->execute([$orden_id, $user['id']]);
    if ((int)$stmt->fetchColumn() > 0) {
        redirect_with_message('compras.php', 'Ya calificaste esta orden.', 'info');
        exit;
    }
} catch (Exception $e) {
    redirect_with_message('compras.php', 'Error al verificar calificación.', 'error');
    exit;
}

// ── Cargar items de la orden ─────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        "SELECT oi.*, p.id AS producto_db_id,
                (SELECT url_imagen FROM imagenes_productos WHERE producto_id = p.id AND es_principal = 1 LIMIT 1) AS imagen
         FROM orden_items oi
         LEFT JOIN productos p ON p.id = oi.producto_id
         WHERE oi.orden_id = ?"
    );
    $stmt->execute([$orden_id]);
    $items = $stmt->fetchAll();
} catch (Exception $e) {
    $items = [];
}

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0D87A8">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Calificar compra #<?= $orden_id ?> — Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .cal-wrap {
            max-width: 680px;
            margin: 120px auto 60px;
            padding: 0 1.25rem;
        }

        /* Tarjeta de vendedor */
        .vendedor-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .vendedor-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: #9ca3af;
            flex-shrink: 0;
            overflow: hidden;
        }
        .vendedor-info h3 { margin: 0 0 0.2rem; font-size: 1rem; color: #111827; }
        .vendedor-info p  { margin: 0; font-size: 0.83rem; color: #6b7280; }

        /* Items de la orden */
        .items-list {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .item-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .item-row:last-child { border-bottom: none; }
        .item-img {
            width: 52px;
            height: 52px;
            border-radius: 10px;
            object-fit: cover;
            background: #f3f4f6;
            flex-shrink: 0;
        }
        .item-name { font-size: 0.9rem; font-weight: 500; color: #1f2937; }
        .item-meta { font-size: 0.8rem; color: #9ca3af; }

        /* Formulario de calificación */
        .rating-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.09);
            padding: 2rem;
        }
        .rating-section {
            margin-bottom: 2rem;
            padding-bottom: 1.75rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .rating-section:last-of-type { border-bottom: none; margin-bottom: 1rem; }

        .rating-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.3rem;
        }
        .rating-subtitle { font-size: 0.82rem; color: #6b7280; margin-bottom: 1rem; }

        /* Estrellas interactivas */
        .star-rating {
            display: flex;
            gap: 0.4rem;
            margin-bottom: 0.75rem;
        }
        .star-rating input[type="radio"] { display: none; }
        .star-rating label {
            font-size: 2rem;
            color: #d1d5db;
            cursor: pointer;
            transition: color 0.15s, transform 0.1s;
            line-height: 1;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label { color: #d1d5db; }
        .star-rating input:checked ~ label { color: #d1d5db; }
        /* Llenado de izquierda a derecha */
        .star-rating:hover label,
        .star-rating label:hover ~ label { color: #d1d5db; }
        .star-rating label:hover,
        .star-rating label:hover ~ .star-fill { color: #fbbf24; }

        /* Texto de estrellas */
        .star-hint {
            font-size: 0.82rem;
            color: #6b7280;
            height: 1.2em;
            margin-bottom: 0.75rem;
        }

        .form-group { margin-bottom: 1.1rem; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.65rem 0.9rem;
            border: 1.5px solid #d1d5db;
            border-radius: 10px;
            font-size: 0.9rem;
            color: #111827;
            box-sizing: border-box;
            transition: border-color 0.2s;
            background: white;
        }
        .form-group input:focus,
        .form-group textarea:focus { outline: none; border-color: #f97316; box-shadow: 0 0 0 3px rgba(249,115,22,0.1); }
        .form-group textarea { min-height: 85px; resize: vertical; }

        .btn-enviar {
            width: 100%;
            padding: 0.9rem;
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
        .btn-enviar:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(249,115,22,0.3); }
        .btn-enviar:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .skip-link {
            display: block;
            text-align: center;
            margin-top: 0.75rem;
            font-size: 0.85rem;
            color: #9ca3af;
            text-decoration: none;
        }
        .skip-link:hover { color: #6b7280; }

        @media (max-width: 600px) {
            .rating-card { padding: 1.5rem; }
            .star-rating label { font-size: 1.7rem; }
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="cal-wrap">

        <!-- Breadcrumb -->
        <div style="margin-bottom:1.5rem; font-size:0.85rem; color:#9ca3af;">
            <a href="compras.php" style="color:#f97316; text-decoration:none;">Mis compras</a>
            <span style="margin:0 0.5rem;">/</span>
            <span>Calificar orden #<?= $orden_id ?></span>
        </div>

        <?php if ($flash): ?>
        <div style="background:<?= $flash['type']==='success'?'#d1fae5':'#fee2e2' ?>; color:<?= $flash['type']==='success'?'#065f46':'#991b1b' ?>; border-radius:12px; padding:0.9rem 1.1rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.6rem; font-size:0.9rem;">
            <i class="fas <?= $flash['type']==='success'?'fa-check-circle':'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <h1 style="font-size:1.5rem; color:#111827; margin:0 0 0.5rem;">
            <i class="fas fa-star" style="color:#fbbf24;"></i>
            Calificar tu compra
        </h1>
        <p style="color:#6b7280; font-size:0.93rem; margin:0 0 1.5rem;">
            Orden <strong>#<?= htmlspecialchars($orden['numero_orden']) ?></strong>
            — completada el <?= date('d/m/Y', strtotime($orden['fecha_pago'] ?: $orden['fecha_creacion'])) ?>
        </p>

        <!-- Vendedor -->
        <div class="vendedor-card">
            <div class="vendedor-avatar">
                <?php if (!empty($orden['vendedor_foto'])): ?>
                    <img src="<?= htmlspecialchars($orden['vendedor_foto']) ?>"
                         alt="Foto" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="vendedor-info">
                <h3><?= htmlspecialchars($orden['vendedor_nombre'] . ' ' . $orden['vendedor_apellidos']) ?></h3>
                <p>Vendedor</p>
                <?php if ($orden['vendedor_cal'] > 0): ?>
                <p style="color:#f59e0b; font-weight:600;">
                    <i class="fas fa-star" style="font-size:0.8rem;"></i>
                    <?= number_format($orden['vendedor_cal'], 1) ?> calificación promedio
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items comprados -->
        <?php if (!empty($items)): ?>
        <div class="items-list">
            <div style="font-size:0.82rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; margin-bottom:0.5rem;">
                Productos comprados
            </div>
            <?php foreach ($items as $item): ?>
            <div class="item-row">
                <?php if (!empty($item['imagen'])): ?>
                <img src="<?= htmlspecialchars($item['imagen']) ?>" class="item-img" alt="">
                <?php else: ?>
                <div class="item-img" style="display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#d1d5db;">
                    <i class="fas fa-box"></i>
                </div>
                <?php endif; ?>
                <div>
                    <div class="item-name"><?= htmlspecialchars($item['producto_titulo']) ?></div>
                    <div class="item-meta">
                        Cantidad: <?= $item['cantidad'] ?> —
                        $<?= number_format($item['precio_unitario'], 2) ?> c/u
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Formulario de calificación -->
        <div class="rating-card">
            <h2 style="font-size:1rem; margin:0 0 1.5rem; color:#111827;">
                Tu opinión ayuda a toda la comunidad
            </h2>

            <form method="POST" action="process_calificacion.php" id="formCalificar">
                <?= csrf_field() ?>
                <input type="hidden" name="orden_id"    value="<?= $orden_id ?>">
                <input type="hidden" name="vendedor_id" value="<?= $orden['vendedor_id'] ?>">

                <!-- ── Calificación del VENDEDOR ── -->
                <div class="rating-section">
                    <div class="rating-title">
                        <i class="fas fa-user-check" style="color:#f97316;"></i>
                        Experiencia con el vendedor
                    </div>
                    <div class="rating-subtitle">
                        ¿Cómo fue la comunicación, la puntualidad y el trato?
                    </div>

                    <div class="star-rating" id="stars-vendedor" data-name="cal_vendedor">
                        <input type="radio" name="cal_vendedor" id="v1" value="1" required>
                        <label for="v5" class="star-lbl" data-val="5" title="Excelente">&#9733;</label>
                        <label for="v4" class="star-lbl" data-val="4" title="Muy bueno">&#9733;</label>
                        <label for="v3" class="star-lbl" data-val="3" title="Bueno">&#9733;</label>
                        <label for="v2" class="star-lbl" data-val="2" title="Regular">&#9733;</label>
                        <label for="v1" class="star-lbl" data-val="1" title="Malo">&#9733;</label>
                        <input type="radio" name="cal_vendedor" id="v2" value="2">
                        <input type="radio" name="cal_vendedor" id="v3" value="3">
                        <input type="radio" name="cal_vendedor" id="v4" value="4">
                        <input type="radio" name="cal_vendedor" id="v5" value="5">
                    </div>
                    <div class="star-hint" id="hint-vendedor">Selecciona una calificación</div>

                    <div class="form-group">
                        <label>Título de tu reseña (opcional)</label>
                        <input type="text" name="titulo_vendedor" maxlength="100"
                               placeholder="Ej: Excelente vendedor, muy puntual">
                    </div>
                    <div class="form-group">
                        <label>Comentario (opcional)</label>
                        <textarea name="comentario_vendedor" maxlength="600"
                                  placeholder="Comparte tu experiencia con otros compradores..."></textarea>
                    </div>
                </div>

                <!-- ── Calificación del PRODUCTO (si hay items) ── -->
                <?php if (!empty($items) && !empty($items[0]['producto_db_id'])): ?>
                <div class="rating-section">
                    <div class="rating-title">
                        <i class="fas fa-box" style="color:#3b82f6;"></i>
                        Calidad del producto
                    </div>
                    <div class="rating-subtitle">
                        ¿El producto coincidió con la descripción? ¿Llegó en buen estado?
                    </div>

                    <input type="hidden" name="producto_id" value="<?= $items[0]['producto_db_id'] ?>">

                    <div class="star-rating" id="stars-producto" data-name="cal_producto">
                        <input type="radio" name="cal_producto" id="p1" value="1">
                        <label for="p5" class="star-lbl" data-val="5" title="Excelente">&#9733;</label>
                        <label for="p4" class="star-lbl" data-val="4" title="Muy bueno">&#9733;</label>
                        <label for="p3" class="star-lbl" data-val="3" title="Bueno">&#9733;</label>
                        <label for="p2" class="star-lbl" data-val="2" title="Regular">&#9733;</label>
                        <label for="p1" class="star-lbl" data-val="1" title="Malo">&#9733;</label>
                        <input type="radio" name="cal_producto" id="p2" value="2">
                        <input type="radio" name="cal_producto" id="p3" value="3">
                        <input type="radio" name="cal_producto" id="p4" value="4">
                        <input type="radio" name="cal_producto" id="p5" value="5">
                    </div>
                    <div class="star-hint" id="hint-producto">Selecciona una calificación (opcional)</div>

                    <div class="form-group">
                        <label>Comentario del producto (opcional)</label>
                        <textarea name="comentario_producto" maxlength="600"
                                  placeholder="¿Qué te pareció el producto?"></textarea>
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn-enviar" id="btnEnviar">
                    <i class="fas fa-paper-plane"></i>
                    Enviar calificación
                </button>
            </form>

            <a href="compras.php" class="skip-link">
                <i class="fas fa-times" style="font-size:0.7rem;"></i>
                Omitir calificación por ahora
            </a>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
    // ── Star rating interactivo (RTL CSS trick invertido con JS) ────────────
    const starHints = {
        1: '⭐ Malo — No recomendaría',
        2: '⭐⭐ Regular — Por debajo de lo esperado',
        3: '⭐⭐⭐ Bueno — Cumplió con lo básico',
        4: '⭐⭐⭐⭐ Muy bueno — Superó expectativas',
        5: '⭐⭐⭐⭐⭐ Excelente — ¡Altamente recomendado!',
    };

    document.querySelectorAll('.star-rating').forEach(container => {
        const name   = container.dataset.name;
        const labels = container.querySelectorAll('.star-lbl');
        const hintId = 'hint-' + name.replace('cal_', '');
        const hint   = document.getElementById(hintId);

        // Reordenar etiquetas (CSS trick RTL → reversamos con JS para UX natural)
        // Los labels están en orden 5→1 en HTML; al hacer hover llenamos izquierda→derecha
        labels.forEach(lbl => {
            const val = parseInt(lbl.dataset.val);

            lbl.addEventListener('mouseenter', () => {
                labels.forEach(l => {
                    l.style.color = parseInt(l.dataset.val) <= val ? '#fbbf24' : '#d1d5db';
                });
                if (hint) hint.textContent = starHints[val] || '';
            });

            lbl.addEventListener('click', () => {
                // Encontrar el radio correspondiente y marcarlo
                const radio = container.querySelector(`input[value="${val}"]`);
                if (radio) {
                    radio.checked = true;
                    // Guardar selección visual
                    container.dataset.selected = val;
                }
                labels.forEach(l => {
                    l.style.color = parseInt(l.dataset.val) <= val ? '#fbbf24' : '#d1d5db';
                });
                if (hint) {
                    hint.textContent = starHints[val] || '';
                    hint.style.color = '#f59e0b';
                    hint.style.fontWeight = '600';
                }
            });
        });

        container.addEventListener('mouseleave', () => {
            const sel = parseInt(container.dataset.selected || 0);
            labels.forEach(l => {
                l.style.color = parseInt(l.dataset.val) <= sel ? '#fbbf24' : '#d1d5db';
            });
            if (hint && sel === 0) hint.textContent = 'Selecciona una calificación';
        });
    });

    // ── Validación antes de enviar ───────────────────────────────────────────
    document.getElementById('formCalificar').addEventListener('submit', function(e) {
        const calVendedor = document.querySelector('input[name="cal_vendedor"]:checked');
        if (!calVendedor) {
            e.preventDefault();
            alert('Por favor califica la experiencia con el vendedor.');
            return;
        }
        const btn = document.getElementById('btnEnviar');
        btn.disabled = true;
        btn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.4);border-top-color:white;border-radius:50%;animation:spin 0.7s linear infinite"></span> Enviando...';
    });
    </script>
</body>
</html>
