<?php
/**
 * Endpoint AJAX — Motor de Simulaciones
 * Solo accesible para administradores autenticados.
 * Devuelve JSON con pasos, resultado y log_id.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/simulaciones.php';

// ── Guardia de acceso ────────────────────────────────────────────────────────
if (
    !isset($_SESSION['user'], $_SESSION['auth_token']) ||
    ($_SESSION['user']['rol'] ?? '') !== 'admin' ||
    ($_SESSION['user']['estado'] ?? '') !== 'activo'
) {
    http_response_code(403);
    echo json_encode(['exito' => false, 'mensaje' => 'No autorizado', 'pasos' => [], 'log_id' => 0]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido', 'pasos' => [], 'log_id' => 0]);
    exit;
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error de conexión a la base de datos', 'pasos' => [], 'log_id' => 0]);
    exit;
}

// ── Leer tipo de simulación ──────────────────────────────────────────────────
$tipo     = trim($_POST['tipo'] ?? '');
$admin_id = (int)($_SESSION['user']['id'] ?? 0);
$motor    = new SimulacionMotor($pdo);
$resultado   = null;
$parametros  = [];

// ── Ejecutar simulación según tipo ──────────────────────────────────────────
switch ($tipo) {

    case 'login':
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']       ?? '';
        $parametros = ['email' => $email];
        $resultado  = $motor->simularLogin($email, $password);
        break;

    case 'registro':
        $email     = trim($_POST['email']     ?? '');
        $nombre    = trim($_POST['nombre']    ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $password  = $_POST['password']        ?? '';
        $rol       = $_POST['rol']             ?? 'usuario';
        // Sanitizar rol — solo roles válidos
        if (!in_array($rol, ['usuario', 'admin'])) $rol = 'usuario';
        $parametros = ['email' => $email, 'nombre' => $nombre, 'apellidos' => $apellidos, 'rol' => $rol];
        $resultado  = $motor->simularRegistro($email, $nombre, $apellidos, $password, $rol);
        break;

    case 'publicar_producto':
        $vendedor_id     = (int)($_POST['vendedor_id']     ?? 0);
        $titulo          = trim($_POST['titulo']           ?? '');
        $precio          = (float)($_POST['precio']        ?? 0);
        $stock           = (int)($_POST['stock']           ?? 1);
        $categoria_id    = (int)($_POST['categoria_id']    ?? 0);
        $estado_producto = $_POST['estado_producto']        ?? 'bueno';
        if (!in_array($estado_producto, ['nuevo','excelente','bueno','regular','para_repuesto'])) {
            $estado_producto = 'bueno';
        }
        $parametros = [
            'vendedor_id'     => $vendedor_id,
            'titulo'          => $titulo,
            'precio'          => $precio,
            'stock'           => $stock,
            'estado_producto' => $estado_producto,
        ];
        $resultado = $motor->simularPublicarProducto($vendedor_id, $titulo, $precio, $stock, $categoria_id, $estado_producto);
        break;

    case 'compra':
        $producto_id  = (int)($_POST['producto_id']  ?? 0);
        $comprador_id = (int)($_POST['comprador_id'] ?? 0);
        $cantidad     = (int)($_POST['cantidad']     ?? 1);
        $parametros   = [
            'producto_id'  => $producto_id,
            'comprador_id' => $comprador_id,
            'cantidad'     => $cantidad,
        ];
        $resultado = $motor->simularCompra($producto_id, $comprador_id, $cantidad);
        break;

    case 'ayuda':
        $nombre    = trim($_POST['nombre']    ?? '');
        $email     = trim($_POST['email']     ?? '');
        $asunto    = trim($_POST['asunto']    ?? '');
        $categoria = $_POST['categoria']       ?? 'general';
        $mensaje   = trim($_POST['mensaje']   ?? '');
        $parametros = [
            'nombre'    => $nombre,
            'email'     => $email,
            'asunto'    => $asunto,
            'categoria' => $categoria,
        ];
        $resultado = $motor->simularSolicitudAyuda($nombre, $email, $asunto, $categoria, $mensaje);
        break;

    default:
        echo json_encode(['exito' => false, 'mensaje' => "Tipo de simulación desconocido: '$tipo'", 'pasos' => [], 'log_id' => 0]);
        exit;
}

// ── Guardar log (fuera de transacción) ───────────────────────────────────────
$log_id = $motor->guardarLog($admin_id, $tipo, $parametros, $resultado['exito'], $resultado['mensaje']);

$resultado['log_id'] = $log_id;

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
