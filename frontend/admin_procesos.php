<?php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
session_start();
require_role('admin', 'index.php');

// ── Pre-cargar datos para los formularios ────────────────────────────────────
$usuarios    = [];
$vendedores  = [];
$clientes    = [];
$productos   = [];
$categorias  = [];
$logs_recientes = [];
$logs_error  = null;

if (isset($pdo)) {
    try {
        $usuarios = $pdo->query(
            "SELECT id, nombre, apellidos, email, rol, puede_vender FROM usuarios WHERE estado = 'activo' ORDER BY nombre ASC"
        )->fetchAll();

        // Vendedores = usuarios con puede_vender=1 + admins (pueden publicar productos)
        $vendedores = array_values(array_filter($usuarios, fn($u) => $u['puede_vender'] || $u['rol'] === 'admin'));
        // Compradores = todos los usuarios con rol 'usuario' (todos pueden comprar)
        $clientes   = array_values(array_filter($usuarios, fn($u) => $u['rol'] === 'usuario'));

        $productos = $pdo->query(
            "SELECT p.id, p.titulo, p.precio, p.stock, u.nombre AS vendedor_nombre, u.apellidos AS vendedor_apellidos
             FROM productos p
             JOIN usuarios u ON p.vendedor_id = u.id
             WHERE p.activo = 1 AND p.vendido = 0
             ORDER BY p.fecha_publicacion DESC
             LIMIT 100"
        )->fetchAll();

        $categorias = $pdo->query(
            "SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre ASC"
        )->fetchAll();

        // Verificar si la tabla simulaciones_log ya existe
        $check = $pdo->query("SHOW TABLES LIKE 'simulaciones_log'")->fetch();
        if ($check) {
            $logs_recientes = $pdo->query(
                "SELECT sl.*, u.nombre AS admin_nombre, u.apellidos AS admin_apellidos
                 FROM simulaciones_log sl
                 LEFT JOIN usuarios u ON sl.admin_id = u.id
                 ORDER BY sl.fecha DESC
                 LIMIT 50"
            )->fetchAll();
        } else {
            $logs_error = 'La tabla simulaciones_log no existe. Aplica el patch_007.sql para habilitarla.';
        }
    } catch (PDOException $e) {
        $logs_error = 'Error al cargar datos: ' . $e->getMessage();
    }
}

// ── Etiquetas legibles por tipo ──────────────────────────────────────────────
$tipo_labels = [
    'login'             => ['label' => 'Login',             'icon' => 'fa-sign-in-alt',  'color' => '#3b82f6'],
    'registro'          => ['label' => 'Registro',          'icon' => 'fa-user-plus',     'color' => '#8b5cf6'],
    'publicar_producto' => ['label' => 'Publicar Producto', 'icon' => 'fa-box',           'color' => '#f97316'],
    'compra'            => ['label' => 'Compra',            'icon' => 'fa-shopping-cart', 'color' => '#10b981'],
    'ayuda'             => ['label' => 'Solicitud de Ayuda','icon' => 'fa-headset',       'color' => '#ec4899'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0D87A8">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Simulador de Procesos — Esfero Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* ── Variables ── */
        :root {
            --sim-success: #10b981;
            --sim-danger:  #ef4444;
            --sim-warning: #f59e0b;
            --sim-blue:    #3b82f6;
            --sim-border:  #e5e7eb;
        }

        /* ── Banner de modo simulación ── */
        .sim-banner {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1.5px solid #f59e0b;
            border-radius: 12px;
            padding: 0.9rem 1.25rem;
            margin-bottom: 1.75rem;
            font-size: 0.92rem;
            color: #92400e;
            font-weight: 500;
        }
        .sim-banner i { font-size: 1.2rem; color: #d97706; }

        /* ── Tabs de navegación ── */
        .sim-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 0;
            border-bottom: 2px solid var(--sim-border);
            padding-bottom: 0;
        }
        .sim-tab {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            border-radius: 8px 8px 0 0;
            transition: all 0.2s;
        }
        .sim-tab:hover  { background: #f9fafb; color: #374151; }
        .sim-tab.active { color: #dc3545; border-bottom-color: #dc3545; background: #fff5f5; }
        .sim-tab i      { font-size: 0.95rem; }

        /* ── Paneles de formulario ── */
        .sim-panel {
            display: none;
            padding: 2rem;
            background: white;
            border-radius: 0 0 16px 16px;
            border: 1px solid var(--sim-border);
            border-top: none;
            animation: fadeIn 0.2s ease;
        }
        .sim-panel.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        /* ── Grid de formulario ── */
        .form-grid     { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        .form-grid.col3 { grid-template-columns: 1fr 1fr 1fr; }
        .form-full      { grid-column: 1 / -1; }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.65rem 0.9rem;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #111827;
            transition: border-color 0.2s;
            background: white;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220,53,69,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }

        .btn-simular {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.8rem 2rem;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1.25rem;
        }
        .btn-simular:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(220,53,69,0.35); }
        .btn-simular:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        /* ── Modal de resultados ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-overlay.open { display: flex; }

        .modal-box {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 680px;
            max-height: 88vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            animation: modalIn 0.25s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.93) translateY(20px); }
            to   { opacity: 1; transform: scale(1)    translateY(0); }
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem 1.75rem 1.25rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .modal-icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .modal-icon-wrap.success { background: #d1fae5; color: #059669; }
        .modal-icon-wrap.failure { background: #fee2e2; color: #dc2626; }

        .modal-title    { font-size: 1.15rem; font-weight: 700; color: #111827; }
        .modal-subtitle { font-size: 0.85rem; color: #6b7280; margin-top: 0.2rem; }

        .modal-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 1.4rem;
            color: #9ca3af;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            transition: all 0.15s;
        }
        .modal-close:hover { background: #f3f4f6; color: #374151; }

        .modal-body { padding: 1.5rem 1.75rem; }

        /* ── Pasos de ejecución ── */
        .steps-list { list-style: none; padding: 0; margin: 0 0 1.5rem; }
        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 0.9rem;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            margin-bottom: 0.5rem;
            border-left: 3px solid transparent;
        }
        .step-item.ok   { background: #f0fdf4; border-left-color: #22c55e; }
        .step-item.fail { background: #fef2f2; border-left-color: #ef4444; }

        .step-icon {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .step-icon.ok   { background: #22c55e; color: white; }
        .step-icon.fail { background: #ef4444; color: white; }

        .step-desc   { font-size: 0.9rem; font-weight: 600; color: #1f2937; line-height: 1.3; }
        .step-detail { font-size: 0.82rem; color: #6b7280; margin-top: 0.2rem; line-height: 1.4; }

        /* ── Mensaje final ── */
        .result-message {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            line-height: 1.5;
        }
        .result-message.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .result-message.failure { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .log-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.9rem;
            padding: 0.4rem 0.9rem;
            background: #f3f4f6;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #374151;
            font-weight: 500;
        }
        .log-badge i { color: #6b7280; }

        /* ── Tabla de logs ── */
        .logs-section { margin-top: 2.5rem; }
        .logs-section h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .logs-section h3 i { color: #dc3545; }

        .logs-table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid var(--sim-border); }
        table.logs-table { width: 100%; border-collapse: collapse; font-size: 0.87rem; }
        .logs-table th {
            background: #f9fafb;
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--sim-border);
        }
        .logs-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
            vertical-align: middle;
        }
        .logs-table tr:last-child td { border-bottom: none; }
        .logs-table tr:hover td { background: #fafafa; }

        .badge-tipo {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            color: white;
        }
        .badge-resultado {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.65rem;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .badge-resultado.exitoso { background: #d1fae5; color: #065f46; }
        .badge-resultado.fallido { background: #fee2e2; color: #991b1b; }

        .btn-ver-detalle {
            padding: 0.3rem 0.75rem;
            background: white;
            border: 1.5px solid #d1d5db;
            border-radius: 7px;
            font-size: 0.8rem;
            cursor: pointer;
            color: #374151;
            font-weight: 500;
            transition: all 0.15s;
        }
        .btn-ver-detalle:hover { border-color: #dc3545; color: #dc3545; background: #fff5f5; }

        /* ── Detalle expandible en tabla ── */
        .row-detalle { display: none; background: #f9fafb; }
        .row-detalle td { padding: 0.75rem 1rem 1rem; }
        .row-detalle.open { display: table-row; }
        .pasos-mini { display: flex; flex-direction: column; gap: 0.4rem; }
        .paso-mini {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            font-size: 0.82rem;
            padding: 0.45rem 0.75rem;
            border-radius: 7px;
        }
        .paso-mini.ok   { background: #f0fdf4; color: #065f46; }
        .paso-mini.fail { background: #fef2f2; color: #991b1b; }
        .paso-mini i    { margin-top: 1px; flex-shrink: 0; }

        /* ── Spinner ── */
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2.5px solid rgba(255,255,255,0.4);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Alerta de tabla faltante ── */
        .alert-warning {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            background: #fffbeb;
            border: 1.5px solid #fcd34d;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            font-size: 0.9rem;
            color: #78350f;
        }
        .alert-warning i { color: #d97706; margin-top: 1px; font-size: 1rem; }

        /* ── Select info hint ── */
        .form-hint {
            font-size: 0.78rem;
            color: #9ca3af;
            margin-top: 0.3rem;
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .form-grid, .form-grid.col3 { grid-template-columns: 1fr; }
            .sim-tabs { gap: 0.25rem; }
            .sim-tab  { padding: 0.6rem 0.9rem; font-size: 0.82rem; }
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div style="width:100%; margin:100px auto 50px; padding:2rem;">
        <h1 style="color:#dc3545; margin-bottom:2rem; font-size:clamp(1.5rem,4vw,2.5rem);">
            <i class="fas fa-flask"></i> Simulador de Procesos
        </h1>

        <div style="display:grid; grid-template-columns:280px 1fr; gap:2rem;" id="adminLayout">
            <?php include 'components/sidebar_admin.php'; ?>

            <div>
                <!-- Banner modo simulación -->
                <div class="sim-banner">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>Modo Sandbox activo.</strong>
                        Todos los procesos se ejecutan contra datos reales mediante transacciones que se
                        <strong>revierten automáticamente</strong> — ningún cambio persiste en la base de datos.
                        Cada ejecución queda registrada en el log de procesos.
                    </div>
                </div>

                <!-- Tabs + formularios -->
                <div style="background:white; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.08); overflow:hidden; margin-bottom:2rem;">
                    <div style="padding:1.5rem 1.75rem 0;">
                        <h2 style="font-size:1.05rem; font-weight:700; color:#1f2937; margin:0 0 1.25rem;">
                            <i class="fas fa-play-circle" style="color:#dc3545;"></i>
                            Ejecutar simulación
                        </h2>

                        <!-- Tabs -->
                        <div class="sim-tabs" id="simTabs">
                            <button class="sim-tab active" data-tab="login">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </button>
                            <button class="sim-tab" data-tab="registro">
                                <i class="fas fa-user-plus"></i> Registro
                            </button>
                            <button class="sim-tab" data-tab="publicar_producto">
                                <i class="fas fa-box"></i> Publicar Producto
                            </button>
                            <button class="sim-tab" data-tab="compra">
                                <i class="fas fa-shopping-cart"></i> Compra
                            </button>
                            <button class="sim-tab" data-tab="ayuda">
                                <i class="fas fa-headset"></i> Solicitud de Ayuda
                            </button>
                        </div>
                    </div>

                    <!-- ── Panel: LOGIN ── -->
                    <div class="sim-panel active" id="panel-login">
                        <p style="font-size:0.88rem; color:#6b7280; margin:0 0 1.25rem;">
                            Verifica el flujo de autenticación completo: búsqueda en BD, verificación bcrypt, estado de cuenta y generación de sesión.
                        </p>
                        <form class="sim-form" data-tipo="login">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email</label>
                                    <input type="email" name="email" placeholder="usuario@ejemplo.com" required>
                                    <?php if (!empty($usuarios)): ?>
                                    <div class="form-hint">
                                        Prueba con:
                                        <?php
                                        $sample = array_slice($usuarios, 0, 3);
                                        $emails = array_map(fn($u) => "<span class='email-hint' style='cursor:pointer;color:#dc3545;text-decoration:underline;' onclick=\"this.closest('form').querySelector('[name=email]').value='{$u['email']}'\">{$u['email']}</span>", $sample);
                                        echo implode(', ', $emails);
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-lock"></i> Contraseña</label>
                                    <input type="password" name="password" placeholder="Contraseña del usuario" required>
                                    <div class="form-hint">Contraseña de prueba de seeds: <strong>password123</strong></div>
                                </div>
                            </div>
                            <button type="submit" class="btn-simular">
                                <i class="fas fa-play"></i> Ejecutar simulación
                            </button>
                        </form>
                    </div>

                    <!-- ── Panel: REGISTRO ── -->
                    <div class="sim-panel" id="panel-registro">
                        <p style="font-size:0.88rem; color:#6b7280; margin:0 0 1.25rem;">
                            Simula el registro de un nuevo usuario: validaciones, verificación de email único, hasheo de contraseña e inserción (revertida).
                        </p>
                        <form class="sim-form" data-tipo="registro">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Nombre</label>
                                    <input type="text" name="nombre" placeholder="Ana" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Apellidos</label>
                                    <input type="text" name="apellidos" placeholder="García López">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email</label>
                                    <input type="email" name="email" placeholder="nuevo@ejemplo.com" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-lock"></i> Contraseña</label>
                                    <input type="password" name="password" placeholder="Mínimo 6 caracteres" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-id-badge"></i> Rol asignado</label>
                                    <select name="rol">
                                        <option value="usuario">Usuario</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn-simular">
                                <i class="fas fa-play"></i> Ejecutar simulación
                            </button>
                        </form>
                    </div>

                    <!-- ── Panel: PUBLICAR PRODUCTO ── -->
                    <div class="sim-panel" id="panel-publicar_producto">
                        <p style="font-size:0.88rem; color:#6b7280; margin:0 0 1.25rem;">
                            Simula la publicación de un producto: verificación de permisos del vendedor, validación de categoría e inserción (revertida).
                        </p>
                        <form class="sim-form" data-tipo="publicar_producto">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label><i class="fas fa-store"></i> Vendedor</label>
                                    <select name="vendedor_id" required>
                                        <option value="">— Seleccionar vendedor —</option>
                                        <?php foreach ($vendedores as $v): ?>
                                        <option value="<?= $v['id'] ?>">
                                            <?= htmlspecialchars($v['nombre'] . ' ' . $v['apellidos']) ?>
                                            (<?= $v['rol'] === 'admin' ? 'admin' : 'vendedor' ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-tag"></i> Categoría</label>
                                    <select name="categoria_id">
                                        <option value="0">Sin categoría</option>
                                        <?php foreach ($categorias as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group form-full">
                                    <label><i class="fas fa-heading"></i> Título del producto</label>
                                    <input type="text" name="titulo" placeholder="Ej: Bicicleta de montaña Trek" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-dollar-sign"></i> Precio (MXN)</label>
                                    <input type="number" name="precio" min="1" step="0.01" placeholder="999.00" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-cubes"></i> Stock</label>
                                    <input type="number" name="stock" min="0" value="1" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-star"></i> Estado del producto</label>
                                    <select name="estado_producto">
                                        <option value="nuevo">Nuevo</option>
                                        <option value="excelente">Excelente</option>
                                        <option value="bueno" selected>Bueno</option>
                                        <option value="regular">Regular</option>
                                        <option value="para_repuesto">Para repuesto</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn-simular">
                                <i class="fas fa-play"></i> Ejecutar simulación
                            </button>
                        </form>
                    </div>

                    <!-- ── Panel: COMPRA ── -->
                    <div class="sim-panel" id="panel-compra">
                        <p style="font-size:0.88rem; color:#6b7280; margin:0 0 1.25rem;">
                            Simula el flujo completo de compra: verificación de stock, validación de reglas de negocio, cálculo de totales, creación de orden e ítem, actualización de stock (todo revertido).
                        </p>
                        <form class="sim-form" data-tipo="compra">
                            <div class="form-grid">
                                <div class="form-group form-full">
                                    <label><i class="fas fa-box-open"></i> Producto a comprar</label>
                                    <select name="producto_id" required>
                                        <option value="">— Seleccionar producto —</option>
                                        <?php foreach ($productos as $p): ?>
                                        <option value="<?= $p['id'] ?>">
                                            <?= htmlspecialchars($p['titulo']) ?>
                                            — $<?= number_format($p['precio'], 2) ?>
                                            | Stock: <?= $p['stock'] ?>
                                            | Vendedor: <?= htmlspecialchars($p['vendedor_nombre'] . ' ' . $p['vendedor_apellidos']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-user-circle"></i> Comprador</label>
                                    <select name="comprador_id" required>
                                        <option value="">— Seleccionar comprador —</option>
                                        <?php foreach ($clientes as $u): ?>
                                        <option value="<?= $u['id'] ?>">
                                            <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?>
                                            (usuario)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-sort-numeric-up"></i> Cantidad</label>
                                    <input type="number" name="cantidad" min="1" value="1" required>
                                </div>
                            </div>
                            <button type="submit" class="btn-simular">
                                <i class="fas fa-play"></i> Ejecutar simulación
                            </button>
                        </form>
                    </div>

                    <!-- ── Panel: SOLICITUD DE AYUDA ── -->
                    <div class="sim-panel" id="panel-ayuda">
                        <p style="font-size:0.88rem; color:#6b7280; margin:0 0 1.25rem;">
                            Simula una solicitud de ayuda: validaciones, detección automática de prioridad por palabras clave, generación de número de ticket e inserción (revertida).
                        </p>
                        <form class="sim-form" data-tipo="ayuda">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Nombre</label>
                                    <input type="text" name="nombre" placeholder="Nombre del solicitante" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email</label>
                                    <input type="email" name="email" placeholder="email@ejemplo.com" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-layer-group"></i> Categoría</label>
                                    <select name="categoria">
                                        <option value="general">General</option>
                                        <option value="comprar">Comprar</option>
                                        <option value="vender">Vender</option>
                                        <option value="envios">Envíos</option>
                                        <option value="pagos">Pagos</option>
                                        <option value="cuenta">Cuenta</option>
                                        <option value="seguridad">Seguridad</option>
                                        <option value="reporte">Reporte</option>
                                        <option value="reembolso">Reembolso</option>
                                    </select>
                                </div>
                                <div class="form-group form-full">
                                    <label><i class="fas fa-heading"></i> Asunto</label>
                                    <input type="text" name="asunto" placeholder="Describe brevemente tu problema" required>
                                    <div class="form-hint">Incluir palabras como "urgente", "error" o "fraude" activa prioridad automática alta/urgente.</div>
                                </div>
                                <div class="form-group form-full">
                                    <label><i class="fas fa-comment-dots"></i> Mensaje</label>
                                    <textarea name="mensaje" placeholder="Describe tu problema con detalle..." required></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn-simular">
                                <i class="fas fa-play"></i> Ejecutar simulación
                            </button>
                        </form>
                    </div>
                </div><!-- /tabs container -->


                <!-- ── Log de simulaciones ── -->
                <div class="logs-section" style="background:white; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.08); padding:1.75rem;">
                    <h3>
                        <i class="fas fa-history"></i>
                        Historial de simulaciones
                        <span style="margin-left:auto; font-size:0.8rem; font-weight:400; color:#9ca3af;">
                            Últimas 50 ejecuciones
                        </span>
                    </h3>

                    <?php if ($logs_error): ?>
                    <div class="alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Tabla no encontrada.</strong><br>
                            <?= htmlspecialchars($logs_error) ?><br>
                            <code style="font-size:0.82rem; background:#fef3c7; padding:0.15rem 0.4rem; border-radius:4px; margin-top:0.4rem; display:inline-block;">
                                docker exec esfero_db mysql -u fer -ppassword esfero -e "source /tmp/patch_007.sql"
                            </code>
                        </div>
                    </div>

                    <?php elseif (empty($logs_recientes)): ?>
                    <div style="text-align:center; padding:3rem 1rem; color:#9ca3af;">
                        <i class="fas fa-flask" style="font-size:2.5rem; margin-bottom:1rem; display:block; opacity:0.4;"></i>
                        <p style="font-size:0.95rem; margin:0;">Aún no se han ejecutado simulaciones.</p>
                        <p style="font-size:0.85rem; margin:0.5rem 0 0;">Usa los formularios de arriba para comenzar.</p>
                    </div>

                    <?php else: ?>
                    <div class="logs-table-wrap" id="logsTableWrap">
                        <table class="logs-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tipo</th>
                                    <th>Admin</th>
                                    <th>Resultado</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                            <?php foreach ($logs_recientes as $log):
                                $tinfo = $tipo_labels[$log['tipo']] ?? ['label' => $log['tipo'], 'icon' => 'fa-circle', 'color' => '#6b7280'];
                                $pasos_data = json_decode($log['pasos'] ?? '[]', true) ?: [];
                            ?>
                                <tr>
                                    <td style="color:#9ca3af; font-size:0.82rem;">#<?= $log['id'] ?></td>
                                    <td>
                                        <span class="badge-tipo" style="background:<?= $tinfo['color'] ?>;">
                                            <i class="fas <?= $tinfo['icon'] ?>"></i>
                                            <?= htmlspecialchars($tinfo['label']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.87rem;">
                                        <?= htmlspecialchars(($log['admin_nombre'] ?? '') . ' ' . ($log['admin_apellidos'] ?? '')) ?>
                                    </td>
                                    <td>
                                        <span class="badge-resultado <?= $log['resultado'] ?>">
                                            <i class="fas <?= $log['resultado'] === 'exitoso' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                            <?= ucfirst($log['resultado']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.82rem; color:#6b7280; white-space:nowrap;">
                                        <?= date('d/m/Y H:i', strtotime($log['fecha'])) ?>
                                    </td>
                                    <td>
                                        <button class="btn-ver-detalle" onclick="toggleDetalle(<?= $log['id'] ?>)">
                                            <i class="fas fa-chevron-down" id="icon-<?= $log['id'] ?>"></i> Ver pasos
                                        </button>
                                    </td>
                                </tr>
                                <tr class="row-detalle" id="detalle-<?= $log['id'] ?>">
                                    <td colspan="6">
                                        <div style="margin-bottom:0.5rem; font-size:0.82rem; color:#6b7280;">
                                            <strong>Mensaje:</strong> <?= htmlspecialchars($log['mensaje_final']) ?>
                                        </div>
                                        <div class="pasos-mini">
                                            <?php foreach ($pasos_data as $paso): ?>
                                            <div class="paso-mini <?= $paso['exito'] ? 'ok' : 'fail' ?>">
                                                <i class="fas <?= $paso['exito'] ? 'fa-check' : 'fa-times' ?>"></i>
                                                <div>
                                                    <strong><?= htmlspecialchars($paso['descripcion']) ?></strong>
                                                    <?php if (!empty($paso['detalle'])): ?>
                                                    <br><span style="opacity:0.85;"><?= htmlspecialchars($paso['detalle']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div><!-- /logs-section -->

            </div><!-- /main content -->
        </div><!-- /grid -->
    </div>

    <!-- ── Modal de resultados ── -->
    <div class="modal-overlay" id="resultModal">
        <div class="modal-box">
            <div class="modal-header">
                <div class="modal-icon-wrap" id="modalIconWrap">
                    <i class="fas fa-check" id="modalIcon"></i>
                </div>
                <div>
                    <div class="modal-title" id="modalTitle">Resultado de simulación</div>
                    <div class="modal-subtitle" id="modalSubtitle"></div>
                </div>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size:0.82rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; margin:0 0 0.75rem;">
                    Ejecución paso a paso
                </p>
                <ul class="steps-list" id="modalSteps"></ul>
                <div class="result-message" id="modalResultMsg"></div>
                <div id="modalLogBadge"></div>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
    // ── Tabs ────────────────────────────────────────────────────────────────
    document.querySelectorAll('.sim-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.sim-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.sim-panel').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById('panel-' + tab.dataset.tab).classList.add('active');
        });
    });

    // ── Formularios AJAX ────────────────────────────────────────────────────
    document.querySelectorAll('.sim-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('.btn-simular');
            const tipo = this.dataset.tipo;

            // Spinner en botón
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Ejecutando...';

            const data = new FormData(this);
            data.append('tipo', tipo);

            try {
                const res = await fetch('process_simulacion.php', { method: 'POST', body: data });
                const json = await res.json();
                showModal(tipo, json);
                // Recargar logs si fue exitoso al guardar log
                if (json.log_id > 0) addLogToTable(tipo, json);
            } catch (err) {
                showModal(tipo, { exito: false, mensaje: 'Error de red: ' + err.message, pasos: [], log_id: 0 });
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        });
    });

    // ── Modal ───────────────────────────────────────────────────────────────
    const tipoLabels = {
        login:             { label: 'Simulación de Login',             icon: 'fa-sign-in-alt',  color: '#3b82f6' },
        registro:          { label: 'Simulación de Registro',          icon: 'fa-user-plus',     color: '#8b5cf6' },
        publicar_producto: { label: 'Simulación: Publicar Producto',   icon: 'fa-box',           color: '#f97316' },
        compra:            { label: 'Simulación de Compra',            icon: 'fa-shopping-cart', color: '#10b981' },
        ayuda:             { label: 'Simulación: Solicitud de Ayuda',  icon: 'fa-headset',       color: '#ec4899' },
    };

    function showModal(tipo, data) {
        const tinfo = tipoLabels[tipo] || { label: 'Simulación', icon: 'fa-flask', color: '#6b7280' };
        const modal  = document.getElementById('resultModal');
        const exito  = data.exito;

        // Encabezado
        document.getElementById('modalIconWrap').className = 'modal-icon-wrap ' + (exito ? 'success' : 'failure');
        document.getElementById('modalIcon').className     = 'fas ' + (exito ? 'fa-check' : 'fa-times');
        document.getElementById('modalTitle').textContent  = tinfo.label;
        document.getElementById('modalSubtitle').textContent =
            exito ? 'Proceso completado sin errores — operación revertida' : 'El proceso encontró un error y fue detenido';

        // Pasos
        const stepsList = document.getElementById('modalSteps');
        stepsList.innerHTML = '';
        (data.pasos || []).forEach(paso => {
            const li = document.createElement('li');
            li.className = 'step-item ' + (paso.exito ? 'ok' : 'fail');
            li.innerHTML = `
                <div class="step-icon ${paso.exito ? 'ok' : 'fail'}">
                    <i class="fas ${paso.exito ? 'fa-check' : 'fa-times'}"></i>
                </div>
                <div>
                    <div class="step-desc">${escapeHtml(paso.descripcion)}</div>
                    ${paso.detalle ? `<div class="step-detail">${escapeHtml(paso.detalle)}</div>` : ''}
                </div>`;
            stepsList.appendChild(li);
        });

        // Mensaje final
        const msg = document.getElementById('modalResultMsg');
        msg.className = 'result-message ' + (exito ? 'success' : 'failure');
        msg.textContent = data.mensaje || '';

        // Badge de log
        const logBadge = document.getElementById('modalLogBadge');
        if (data.log_id > 0) {
            logBadge.innerHTML = `<div class="log-badge"><i class="fas fa-save"></i> Guardado en log como entrada #${data.log_id}</div>`;
        } else {
            logBadge.innerHTML = '';
        }

        modal.classList.add('open');
    }

    function closeModal() {
        document.getElementById('resultModal').classList.remove('open');
    }

    document.getElementById('resultModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    // ── Expandir detalle de log ──────────────────────────────────────────────
    function toggleDetalle(id) {
        const row  = document.getElementById('detalle-' + id);
        const icon = document.getElementById('icon-' + id);
        const open = row.classList.toggle('open');
        icon.className = 'fas ' + (open ? 'fa-chevron-up' : 'fa-chevron-down');
    }

    // ── Agregar fila de log sin recargar página ───────────────────────────────
    function addLogToTable(tipo, data) {
        const wrap = document.getElementById('logsTableWrap');
        const body = document.getElementById('logsTableBody');
        if (!body) return; // tabla no existe (patch no aplicado)

        const tinfo = tipoLabels[tipo] || { label: tipo, icon: 'fa-circle', color: '#6b7280' };
        const ahora = new Date();
        const fecha = ahora.toLocaleDateString('es-MX', {day:'2-digit',month:'2-digit',year:'numeric'})
            + ' ' + ahora.toLocaleTimeString('es-MX', {hour:'2-digit',minute:'2-digit'});

        const exito   = data.exito;
        const logId   = data.log_id;
        const pasosJson = JSON.stringify(data.pasos || []).replace(/"/g, '&quot;');

        // Construir pasos HTML para el detalle
        let pasosHtml = (data.pasos || []).map(p => `
            <div class="paso-mini ${p.exito ? 'ok' : 'fail'}">
                <i class="fas ${p.exito ? 'fa-check' : 'fa-times'}"></i>
                <div>
                    <strong>${escapeHtml(p.descripcion)}</strong>
                    ${p.detalle ? '<br><span style="opacity:0.85;">' + escapeHtml(p.detalle) + '</span>' : ''}
                </div>
            </div>`).join('');

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="color:#9ca3af; font-size:0.82rem;">#${logId}</td>
            <td><span class="badge-tipo" style="background:${tinfo.color};">
                <i class="fas ${tinfo.icon}"></i> ${tinfo.label}
            </span></td>
            <td style="font-size:0.87rem;">Tú (admin)</td>
            <td><span class="badge-resultado ${exito ? 'exitoso' : 'fallido'}">
                <i class="fas ${exito ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                ${exito ? 'Exitoso' : 'Fallido'}
            </span></td>
            <td style="font-size:0.82rem; color:#6b7280; white-space:nowrap;">${fecha}</td>
            <td><button class="btn-ver-detalle" onclick="toggleDetalle(${logId})">
                <i class="fas fa-chevron-down" id="icon-${logId}"></i> Ver pasos
            </button></td>`;

        const trDetalle = document.createElement('tr');
        trDetalle.className = 'row-detalle';
        trDetalle.id = 'detalle-' + logId;
        trDetalle.innerHTML = `<td colspan="6">
            <div style="margin-bottom:0.5rem; font-size:0.82rem; color:#6b7280;">
                <strong>Mensaje:</strong> ${escapeHtml(data.mensaje || '')}
            </div>
            <div class="pasos-mini">${pasosHtml}</div>
        </td>`;

        body.insertBefore(trDetalle, body.firstChild);
        body.insertBefore(tr, body.firstChild);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
    </script>
</body>
</html>
