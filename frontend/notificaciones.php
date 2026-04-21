<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/auth_middleware.php';

// Solo usuarios logueados, no admins
if (is_admin()) {
    header('Location: admin_dashboard.php');
    exit;
}
require_login();

$user       = get_session_user();
$usuario_id = (int)($user['id'] ?? 0);

if (!$usuario_id || !isset($pdo)) {
    header('Location: index.php');
    exit;
}

// ── Filtro activo ─────────────────────────────────────────────────────────────
$filtro = $_GET['tipo'] ?? 'todas';
$tipos_validos = ['todas', 'mensaje', 'orden', 'pago', 'resena', 'sistema'];
if (!in_array($filtro, $tipos_validos)) $filtro = 'todas';

// ── Marcar todas como leídas al abrir la página ───────────────────────────────
try {
    $pdo->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0")
        ->execute([$usuario_id]);
} catch (PDOException $e) {
    error_log('notificaciones mark-read: ' . $e->getMessage());
}

// ── Obtener notificaciones ────────────────────────────────────────────────────
try {
    if ($filtro === 'todas') {
        $stmt = $pdo->prepare("
            SELECT id, tipo, titulo, mensaje, icono, url, leida, fecha_creacion
            FROM notificaciones
            WHERE usuario_id = ?
            ORDER BY fecha_creacion DESC
            LIMIT 100
        ");
        $stmt->execute([$usuario_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, tipo, titulo, mensaje, icono, url, leida, fecha_creacion
            FROM notificaciones
            WHERE usuario_id = ? AND tipo = ?
            ORDER BY fecha_creacion DESC
            LIMIT 100
        ");
        $stmt->execute([$usuario_id, $filtro]);
    }
    $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('notificaciones fetch: ' . $e->getMessage());
    $notificaciones = [];
}

// ── Conteos por tipo para los chips ──────────────────────────────────────────
$conteos = ['todas' => 0, 'mensaje' => 0, 'orden' => 0, 'pago' => 0, 'resena' => 0, 'sistema' => 0];
try {
    $stmt2 = $pdo->prepare("SELECT tipo, COUNT(*) as cnt FROM notificaciones WHERE usuario_id = ? GROUP BY tipo");
    $stmt2->execute([$usuario_id]);
    while ($row = $stmt2->fetch()) {
        $conteos[$row['tipo']] = (int)$row['cnt'];
        $conteos['todas'] += (int)$row['cnt'];
    }
} catch (PDOException $e) {}

// ── Helper: ícono y color por tipo ───────────────────────────────────────────
function tipo_meta(string $tipo): array {
    return match($tipo) {
        'mensaje' => ['color' => '#0D87A8', 'bg' => '#E3F5FA', 'label' => 'Mensaje'],
        'orden'   => ['color' => '#F97316', 'bg' => '#FFF0E6', 'label' => 'Orden'],
        'pago'    => ['color' => '#0FB882', 'bg' => '#E4FAF3', 'label' => 'Pago'],
        'resena'  => ['color' => '#F59E0B', 'bg' => '#FEF9EC', 'label' => 'Reseña'],
        default   => ['color' => '#8BB4C0', 'bg' => '#EEF8FA', 'label' => 'Sistema'],
    };
}

function tiempo_relativo(string $fecha): string {
    $diff = time() - strtotime($fecha);
    if ($diff < 60)     return 'Hace un momento';
    if ($diff < 3600)   return 'Hace ' . floor($diff / 60) . ' min';
    if ($diff < 86400)  return 'Hace ' . floor($diff / 3600) . ' h';
    if ($diff < 604800) return 'Hace ' . floor($diff / 86400) . ' días';
    return date('d M Y', strtotime($fecha));
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
    <title>Notificaciones — Esfero</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* ── Layout ── */
        .notif-page {
            max-width: 720px;
            margin: 100px auto 100px;
            padding: 0 1rem;
        }

        .notif-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .notif-header h1 {
            font-size: clamp(1.4rem, 4vw, 1.9rem);
            font-weight: 700;
            color: var(--c-text, #0B2D3C);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notif-header h1 i {
            color: var(--c-primary, #0D87A8);
        }

        /* ── Chips de filtro ── */
        .notif-filters {
            display: flex;
            gap: 0.45rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .notif-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.85rem;
            border-radius: var(--r-pill, 9999px);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: 1.5px solid var(--c-border, #C5DEE8);
            background: white;
            color: var(--c-muted, #4A7585);
            transition: background 0.15s, color 0.15s, border-color 0.15s;
            white-space: nowrap;
        }

        .notif-chip:hover {
            background: var(--c-primary-pale, #E3F5FA);
            border-color: var(--c-primary, #0D87A8);
            color: var(--c-primary, #0D87A8);
        }

        .notif-chip.active {
            background: var(--c-primary, #0D87A8);
            border-color: var(--c-primary, #0D87A8);
            color: white;
        }

        .notif-chip-count {
            background: rgba(0,0,0,0.12);
            border-radius: 9999px;
            padding: 0 5px;
            font-size: 0.72rem;
            min-width: 18px;
            text-align: center;
            line-height: 1.5;
        }

        .notif-chip.active .notif-chip-count {
            background: rgba(255,255,255,0.28);
        }

        /* ── Lista ── */
        .notif-list {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            background: white;
            border: 1px solid var(--c-border-light, #E0EFF5);
            border-radius: 14px;
            padding: 1rem 1.1rem;
            text-decoration: none;
            color: inherit;
            transition: box-shadow 0.15s, border-color 0.15s, transform 0.15s;
            position: relative;
        }

        .notif-item:hover {
            box-shadow: 0 4px 18px rgba(13,135,168,0.10);
            border-color: var(--c-border, #C5DEE8);
            transform: translateY(-1px);
        }

        /* Ítem no leído — borde izquierdo de acento */
        .notif-item.unread {
            border-left: 3px solid var(--c-primary, #0D87A8);
        }

        .notif-item.unread::before {
            content: '';
            position: absolute;
            top: 14px;
            right: 14px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--c-primary, #0D87A8);
        }

        /* ── Ícono circular ── */
        .notif-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        /* ── Contenido ── */
        .notif-body {
            flex: 1;
            min-width: 0;
        }

        .notif-titulo {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--c-text, #0B2D3C);
            margin: 0 0 0.2rem;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .notif-mensaje {
            font-size: 0.85rem;
            color: var(--c-muted, #4A7585);
            margin: 0 0 0.4rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.45;
        }

        .notif-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: var(--c-subtle, #8BB4C0);
        }

        .notif-tipo-badge {
            display: inline-flex;
            align-items: center;
            padding: 1px 7px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* ── Empty state ── */
        .notif-empty {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--c-muted, #4A7585);
        }

        .notif-empty i {
            font-size: 3.5rem;
            color: var(--c-border, #C5DEE8);
            display: block;
            margin-bottom: 1rem;
        }

        .notif-empty p {
            font-size: 1rem;
            margin: 0;
        }

        /* ── Botón borrar todo ── */
        .btn-clear-all {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.9rem;
            border-radius: var(--r-pill, 9999px);
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--c-error, #EF4444);
            background: transparent;
            border: 1.5px solid #fca5a5;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
            text-decoration: none;
        }

        .btn-clear-all:hover {
            background: #fef2f2;
            border-color: var(--c-error, #EF4444);
        }

        @media (max-width: 480px) {
            .notif-page { margin-top: 80px; }
            .notif-icon { width: 38px; height: 38px; font-size: 1rem; border-radius: 10px; }
        }
    </style>
</head>
<body>
<?php include 'components/navbar.php'; ?>

<main class="notif-page">
    <!-- Cabecera -->
    <div class="notif-header">
        <h1><i class="fas fa-bell"></i> Notificaciones</h1>
        <?php if (!empty($notificaciones)): ?>
        <a href="api_marcar_notificacion.php?action=borrar_todas" class="btn-clear-all"
           onclick="return confirm('¿Eliminar todas las notificaciones?')">
            <i class="fas fa-trash-alt"></i> Borrar todo
        </a>
        <?php endif; ?>
    </div>

    <!-- Chips de filtro -->
    <div class="notif-filters">
        <?php
        $chips = [
            'todas'   => ['label' => 'Todas',     'icon' => 'fas fa-layer-group'],
            'mensaje' => ['label' => 'Mensajes',   'icon' => 'fas fa-envelope'],
            'orden'   => ['label' => 'Órdenes',    'icon' => 'fas fa-box'],
            'pago'    => ['label' => 'Pagos',      'icon' => 'fas fa-credit-card'],
            'resena'  => ['label' => 'Reseñas',    'icon' => 'fas fa-star'],
            'sistema' => ['label' => 'Sistema',    'icon' => 'fas fa-info-circle'],
        ];
        foreach ($chips as $key => $chip):
            $active = $filtro === $key ? 'active' : '';
            $count  = $conteos[$key] ?? 0;
        ?>
        <a href="notificaciones.php?tipo=<?= $key ?>" class="notif-chip <?= $active ?>">
            <i class="<?= $chip['icon'] ?>"></i>
            <?= $chip['label'] ?>
            <?php if ($count > 0): ?>
                <span class="notif-chip-count"><?= $count ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Lista de notificaciones -->
    <?php if (empty($notificaciones)): ?>
    <div class="notif-empty">
        <i class="fas fa-bell-slash"></i>
        <p>No tienes notificaciones<?= $filtro !== 'todas' ? ' de este tipo' : '' ?>.</p>
    </div>
    <?php else: ?>
    <div class="notif-list">
        <?php foreach ($notificaciones as $n):
            $meta   = tipo_meta($n['tipo']);
            $tiempo = tiempo_relativo($n['fecha_creacion']);
            $unread = !$n['leida'] ? 'unread' : '';
            $href   = !empty($n['url']) ? htmlspecialchars($n['url']) : '#';
        ?>
        <a href="<?= $href ?>" class="notif-item <?= $unread ?>">
            <!-- Ícono -->
            <div class="notif-icon"
                 style="background:<?= $meta['bg'] ?>; color:<?= $meta['color'] ?>;">
                <i class="<?= htmlspecialchars($n['icono']) ?>"></i>
            </div>
            <!-- Texto -->
            <div class="notif-body">
                <p class="notif-titulo"><?= htmlspecialchars($n['titulo']) ?></p>
                <p class="notif-mensaje"><?= htmlspecialchars($n['mensaje']) ?></p>
                <div class="notif-meta">
                    <span class="notif-tipo-badge"
                          style="background:<?= $meta['bg'] ?>; color:<?= $meta['color'] ?>;">
                        <?= $meta['label'] ?>
                    </span>
                    <span>·</span>
                    <span><?= $tiempo ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php include 'components/footer.php'; ?>
<script src="assets/js/main.js"></script>
</body>
</html>
