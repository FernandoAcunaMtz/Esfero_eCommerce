<?php
/**
 * Procesa la calificación de vendedor y/o producto tras una compra.
 *
 * Flujo:
 *  1. Verificar CSRF + sesión
 *  2. Verificar que la orden pertenece al comprador y está completada
 *  3. Verificar que no ha calificado ya
 *  4. INSERT calificaciones (vendedor + producto si aplica)
 *  5. Recalcular calificación_promedio en perfiles del vendedor
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: compras.php');
    exit;
}

csrf_verify();
require_login();

$user = get_session_user();
if ($user['rol'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

if (!isset($pdo)) {
    redirect_with_message('compras.php', 'Error de conexión a la base de datos.', 'error');
    exit;
}

// ── Leer y sanear inputs ──────────────────────────────────────────────────────
$orden_id    = (int)($_POST['orden_id']    ?? 0);
$vendedor_id = (int)($_POST['vendedor_id'] ?? 0);
$producto_id = (int)($_POST['producto_id'] ?? 0);

$cal_vendedor = (int)($_POST['cal_vendedor'] ?? 0);
$cal_producto = (int)($_POST['cal_producto'] ?? 0);

$titulo_vendedor    = mb_substr(strip_tags(trim($_POST['titulo_vendedor']    ?? '')), 0, 100);
$comentario_vendedor= mb_substr(strip_tags(trim($_POST['comentario_vendedor']?? '')), 0, 600);
$comentario_producto= mb_substr(strip_tags(trim($_POST['comentario_producto']?? '')), 0, 600);

// Validaciones básicas
if ($orden_id < 1 || $vendedor_id < 1) {
    redirect_with_message('compras.php', 'Datos de la orden inválidos.', 'error');
    exit;
}

if ($cal_vendedor < 1 || $cal_vendedor > 5) {
    redirect_with_message("calificar.php?orden_id=$orden_id", 'Debes seleccionar entre 1 y 5 estrellas para el vendedor.', 'error');
    exit;
}

// Si se envió calificación de producto, validar rango
if ($cal_producto !== 0 && ($cal_producto < 1 || $cal_producto > 5)) {
    redirect_with_message("calificar.php?orden_id=$orden_id", 'Calificación de producto inválida.', 'error');
    exit;
}

try {
    // ── Verificar propiedad y estado de la orden ──────────────────────────────
    $stmt = $pdo->prepare(
        "SELECT id, vendedor_id, estado_pago FROM ordenes
         WHERE id = ? AND comprador_id = ?"
    );
    $stmt->execute([$orden_id, $user['id']]);
    $orden = $stmt->fetch();

    if (!$orden) {
        redirect_with_message('compras.php', 'No tienes acceso a esa orden.', 'error');
        exit;
    }

    if ($orden['estado_pago'] !== 'completado') {
        redirect_with_message('compras.php', 'Solo puedes calificar órdenes con pago completado.', 'error');
        exit;
    }

    // Que el vendedor_id del POST coincida con el de la orden (evita manipulación)
    if ((int)$orden['vendedor_id'] !== $vendedor_id) {
        redirect_with_message('compras.php', 'Datos inconsistentes.', 'error');
        exit;
    }

    // ── Verificar que no haya calificado ya ───────────────────────────────────
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM calificaciones WHERE orden_id = ? AND calificador_id = ?"
    );
    $stmt->execute([$orden_id, $user['id']]);
    if ((int)$stmt->fetchColumn() > 0) {
        redirect_with_message('compras.php', 'Ya calificaste esta orden.', 'info');
        exit;
    }

    // ── Insertar calificaciones ───────────────────────────────────────────────
    $pdo->beginTransaction();

    // 1. Calificación del vendedor (obligatoria)
    $stmt = $pdo->prepare(
        "INSERT INTO calificaciones
            (orden_id, calificador_id, calificado_id, tipo, calificacion, titulo, comentario, aprobada, visible)
         VALUES (?, ?, ?, 'vendedor', ?, ?, ?, 1, 1)"
    );
    $stmt->execute([
        $orden_id,
        $user['id'],
        $vendedor_id,
        $cal_vendedor,
        $titulo_vendedor ?: null,
        $comentario_vendedor ?: null,
    ]);

    // 2. Calificación del producto (opcional)
    if ($cal_producto >= 1 && $producto_id > 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO calificaciones
                (orden_id, producto_id, calificador_id, calificado_id, tipo, calificacion, comentario, aprobada, visible)
             VALUES (?, ?, ?, ?, 'producto', ?, ?, 1, 1)"
        );
        $stmt->execute([
            $orden_id,
            $producto_id,
            $user['id'],
            $vendedor_id,
            $cal_producto,
            $comentario_producto ?: null,
        ]);
    }

    // 3. Recalcular calificación_promedio del vendedor en perfiles
    $stmt = $pdo->prepare(
        "UPDATE perfiles SET calificacion_promedio = (
            SELECT ROUND(AVG(calificacion), 2)
            FROM calificaciones
            WHERE calificado_id = ? AND tipo = 'vendedor' AND visible = 1
         )
         WHERE usuario_id = ?"
    );
    $stmt->execute([$vendedor_id, $vendedor_id]);

    // Si no existe fila en perfiles, insertarla
    if ($stmt->rowCount() === 0) {
        $avg = $pdo->prepare(
            "SELECT ROUND(AVG(calificacion), 2) FROM calificaciones
             WHERE calificado_id = ? AND tipo = 'vendedor' AND visible = 1"
        );
        $avg->execute([$vendedor_id]);
        $pdo->prepare("INSERT IGNORE INTO perfiles (usuario_id, calificacion_promedio) VALUES (?, ?)")
            ->execute([$vendedor_id, $avg->fetchColumn()]);
    }

    $pdo->commit();

    redirect_with_message('compras.php', '¡Gracias por tu calificación! Tu opinión ayuda a toda la comunidad.', 'success');

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('process_calificacion error: ' . $e->getMessage());
    redirect_with_message("calificar.php?orden_id=$orden_id", 'Ocurrió un error al guardar tu calificación. Intenta de nuevo.', 'error');
}
