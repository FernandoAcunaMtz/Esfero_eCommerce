<?php
/**
 * API CHECKOUT - Ejecuta Python directamente sin usar CGI
 * Esta versión ejecuta Python como proceso hijo, evitando problemas de CGI
 */

while (ob_get_level() > 0) {
    ob_end_clean();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

function send_json($success, $error = null, $data = []) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($success ? 200 : ($error === 'No autorizado' ? 401 : 500));
    $response = ['success' => (bool)$success];
    if ($error !== null) {
        $response['error'] = (string)$error;
    }
    foreach ($data as $key => $value) {
        $response[$key] = $value;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/includes/auth_middleware.php';
    
    // Verificar autenticación
    if (!is_logged_in()) {
        send_json(false, 'Debes iniciar sesión');
    }
    
    if (is_admin()) {
        send_json(false, 'Los administradores no pueden realizar compras');
    }
    
    $action = isset($_GET['action']) ? trim($_GET['action']) : '';
    
    if (empty($action)) {
        send_json(false, 'Acción no especificada');
    }
    
    // Rutas
    $script_dir = __DIR__ . '/../backend/cgi-bin';
    $python_path = '/usr/bin/python3';
    $token = $_SESSION['auth_token'] ?? '';
    
    // Función para ejecutar Python directamente
    function exec_python_direct($script_name, $method = 'POST', $data = [], $token = '', $endpoint = null) {
        global $script_dir, $python_path;
        
        $script_path = $script_dir . '/' . $script_name;
        
        if (!file_exists($script_path)) {
            return ['success' => false, 'error' => 'Script Python no encontrado: ' . $script_name];
        }
        
        // Preparar variables de entorno para Python
        // Configurar PATH_INFO según el script y endpoint
        if ($script_name === 'paypal.py') {
            if ($endpoint === 'capture') {
                $path_info = '/capture_order';
                $request_uri = '/backend/cgi-bin/paypal.py/capture_order';
            } else {
                // paypal.py espera path_info.endswith('/create_order')
                $path_info = '/create_order';
                $request_uri = '/backend/cgi-bin/paypal.py/create_order';
            }
        } else {
            // ordenes.py busca '/create' en path_info o request_uri
            $path_info = '/create';
            $request_uri = '/backend/cgi-bin/ordenes.py/create';
        }
        
        $env = [
            'REQUEST_METHOD' => $method,
            'PATH_INFO' => $path_info,
            'REQUEST_URI' => $request_uri,
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_X_AUTH_TOKEN' => $token,
            'PYTHONPATH' => $script_dir
        ];
        
        // Preparar datos POST
        $stdin_data = '';
        if (!empty($data)) {
            $post_data = http_build_query($data);
            $env['CONTENT_LENGTH'] = (string)strlen($post_data);
            $stdin_data = $post_data;
        } else {
            $env['CONTENT_LENGTH'] = '0';
        }
        
        // Crear proceso
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];
        
        // Preparar comando con variables de entorno
        $cmd = $python_path . ' ' . escapeshellarg($script_path);
        
        $env_array = [];
        foreach ($env as $key => $value) {
            $env_array[$key] = $value;
        }
        
        $process = proc_open($cmd, $descriptorspec, $pipes, $script_dir, $env_array);
        
        if (!is_resource($process)) {
            return ['success' => false, 'error' => 'No se pudo ejecutar el proceso Python'];
        }
        
        // Escribir datos a stdin
        if (!empty($stdin_data)) {
            fwrite($pipes[0], $stdin_data);
        }
        fclose($pipes[0]);
        
        // Leer stdout y stderr
        $output = stream_get_contents($pipes[1]);
        $error_output = stream_get_contents($pipes[2]);
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $return_code = proc_close($process);
        
        // Si hay error en stderr, loguearlo
        if (!empty($error_output)) {
            error_log("Python stderr para {$script_name}: " . $error_output);
        }
        
        // Log para debugging
        error_log("Python ejecutado - Script: {$script_name}, PATH_INFO: {$env['PATH_INFO']}, Return code: {$return_code}");
        
        if ($return_code !== 0) {
            return [
                'success' => false,
                'error' => 'Error al ejecutar Python (código: ' . $return_code . ')',
                'stderr' => substr($error_output, 0, 500),
                'stdout' => substr($output, 0, 500),
                'debug_path_info' => $env['PATH_INFO']
            ];
        }
        
        // Parsear respuesta JSON (buscar después de headers HTTP)
        $lines = explode("\n", $output);
        $json_started = false;
        $json_lines = [];
        
        foreach ($lines as $line) {
            // Si encontramos una línea vacía después de headers, empezar JSON
            if (empty(trim($line)) && !$json_started) {
                $json_started = true;
                continue;
            }
            if ($json_started) {
                $json_lines[] = $line;
            }
        }
        
        $json_content = implode("\n", $json_lines);
        
        if (empty(trim($json_content))) {
            return [
                'success' => false,
                'error' => 'No se recibió respuesta JSON de Python',
                'raw_output' => substr($output, 0, 1000),
                'debug_path_info' => $env['PATH_INFO'],
                'debug_method' => $method
            ];
        }
        
        $decoded = @json_decode(trim($json_content), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Si la respuesta contiene "Endpoint no encontrado", el problema es que Python no detectó el endpoint
            if (stripos($json_content, 'Endpoint no encontrado') !== false || stripos($json_content, 'endpoint') !== false) {
                return [
                    'success' => false,
                    'error' => 'Python no detectó el endpoint. PATH_INFO enviado: ' . $env['PATH_INFO'],
                    'raw_output' => substr($output, 0, 500),
                    'json_content' => substr($json_content, 0, 500),
                    'debug_env' => ['PATH_INFO' => $env['PATH_INFO'], 'REQUEST_URI' => $env['REQUEST_URI']]
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Respuesta JSON inválida: ' . json_last_error_msg(),
                'raw_output' => substr($output, 0, 500),
                'json_content' => substr($json_content, 0, 500)
            ];
        }
        
        return $decoded;
    }
    
    if ($action === 'create_order') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!is_array($data)) {
            send_json(false, 'Datos inválidos');
        }
        
        $direccion = trim($data['direccion_envio'] ?? '');
        $ciudad = trim($data['ciudad_envio'] ?? '');
        $estado = trim($data['estado_envio'] ?? '');
        $cp = trim($data['codigo_postal_envio'] ?? '');
        $telefono = trim($data['telefono_envio'] ?? '');
        $nombre = trim($data['nombre_destinatario'] ?? '');
        $notas = trim($data['notas_comprador'] ?? '');
        
        if (empty($direccion) || empty($ciudad) || empty($estado) || empty($cp) || empty($telefono) || empty($nombre)) {
            send_json(false, 'Todos los campos son requeridos');
        }
        
        $result = exec_python_direct('ordenes.py', 'POST', [
            'direccion_envio' => $direccion,
            'ciudad_envio' => $ciudad,
            'estado_envio' => $estado,
            'codigo_postal_envio' => $cp,
            'telefono_envio' => $telefono,
            'nombre_destinatario' => $nombre,
            'notas_comprador' => $notas
        ], $token);
        
        if (!isset($result['success']) || !$result['success']) {
            send_json(false, $result['error'] ?? 'Error al crear la orden');
        }
        
        // Extraer orden_ids de la respuesta
        $orden_ids = [];
        if (isset($result['ordenes']) && is_array($result['ordenes'])) {
            foreach ($result['ordenes'] as $orden) {
                if (isset($orden['orden_id'])) {
                    $orden_ids[] = (int)$orden['orden_id'];
                }
            }
        }
        
        if (empty($orden_ids)) {
            send_json(false, 'No se crearon órdenes válidas');
        }
        
        send_json(true, null, ['orden_ids' => $orden_ids]);
        
    } elseif ($action === 'create_paypal') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!is_array($data) || empty($data['orden_ids']) || !is_array($data['orden_ids'])) {
            send_json(false, 'Datos inválidos');
        }
        
        $orden_ids = [];
        foreach ($data['orden_ids'] as $id) {
            $id_int = (int)$id;
            if ($id_int > 0) {
                $orden_ids[] = $id_int;
            }
        }
        
        if (empty($orden_ids)) {
            send_json(false, 'IDs de órdenes inválidos');
        }
        
        // Obtener la URL base dinámicamente para las URLs de retorno
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $base_url = $protocol . '://' . $host;
        
        $result = exec_python_direct('paypal.py', 'POST', [
            'orden_ids' => json_encode($orden_ids),
            'return_url' => $base_url . '/paypal_return.php',
            'cancel_url' => $base_url . '/paypal_cancel.php'
        ], $token);
        
        if (!isset($result['success']) || !$result['success']) {
            send_json(false, $result['error'] ?? 'Error al crear pago en PayPal');
        }
        
        if (empty($result['approve_url'])) {
            send_json(false, 'No se recibió URL de PayPal');
        }
        
        send_json(true, null, ['approve_url' => $result['approve_url']]);
        
    } elseif ($action === 'capture_paypal') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!is_array($data)) {
            send_json(false, 'Datos inválidos');
        }
        
        // El token que PayPal devuelve es el paypal_order_id
        $paypal_order_id = $data['paypal_order_id'] ?? $data['token'] ?? '';
        
        if (empty($paypal_order_id)) {
            send_json(false, 'paypal_order_id requerido');
        }
        
        $result = exec_python_direct('paypal.py', 'POST', [
            'paypal_order_id' => $paypal_order_id
        ], $token, 'capture');
        
        if (!isset($result['success']) || !$result['success']) {
            send_json(false, $result['error'] ?? 'Error al capturar el pago');
        }
        
        send_json(true, null, $result);
        
    } else {
        send_json(false, 'Acción no válida');
    }
    
} catch (Throwable $e) {
    error_log("Error en api_checkout_directo.php: " . $e->getMessage() . " en línea " . $e->getLine());
    send_json(false, 'Error interno: ' . $e->getMessage());
}

