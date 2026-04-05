<?php
/**
 * API CHECKOUT - Con manejo de errores robusto
 */

// CAPTURAR ERRORES FATALES
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR])) {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error fatal del servidor'], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

while (ob_get_level() > 0) {
    ob_end_clean();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function send_json($success, $error = null, $data = []) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    @header_remove();
    header('Content-Type: application/json; charset=utf-8', true);
    header('Cache-Control: no-cache', true);
    $response = ['success' => (bool)$success];
    if ($error !== null) {
        $response['error'] = (string)$error;
    }
    foreach ($data as $key => $value) {
        $response[$key] = $value;
    }
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"success":false,"error":"Error al generar JSON"}';
    }
    echo $json;
    exit;
}

try {
    // Verificar autenticación
    if (!isset($_SESSION['user']) || !isset($_SESSION['auth_token'])) {
        send_json(false, 'Debes iniciar sesión');
    }

    if (isset($_SESSION['user']['rol']) && $_SESSION['user']['rol'] === 'admin') {
        send_json(false, 'Los administradores no pueden realizar compras');
    }

    $action = isset($_GET['action']) ? trim($_GET['action']) : '';

    if (empty($action)) {
        send_json(false, 'Acción no especificada');
    }

    // URL base de la API Python
    $api_base = 'http://10.241.109.37/backend/cgi-bin';
    $token = $_SESSION['auth_token'];

    // Función para llamar a Python - Con fallback si cURL no está disponible
    function call_python_api($endpoint, $method = 'POST', $data = []) {
        global $api_base, $token;
        
        $url = $api_base . '/' . ltrim($endpoint, '/');
        
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'X-Auth-Token: ' . $token
        ];
        
        $response = false;
        $http_code = 0;
        $error = '';
        
        // Preparar datos POST si existen
        $post_data = '';
        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $post_data = http_build_query($data);
        }
        
        // Intentar con cURL primero (si está disponible)
        if (function_exists('curl_init')) {
            $ch = curl_init();
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ];
            
            if (!empty($post_data)) {
                $options[CURLOPT_POSTFIELDS] = $post_data;
            }
            
            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
        } else {
            // Fallback: usar file_get_contents con stream_context
            $context_options = [
                'http' => [
                    'method' => $method,
                    'timeout' => 30,
                    'ignore_errors' => true,
                    'header' => implode("\r\n", $headers) . "\r\n"
                ]
            ];
            
            if (!empty($post_data)) {
                $context_options['http']['content'] = $post_data;
            }
            
            $context = stream_context_create($context_options);
            $response = @file_get_contents($url, false, $context);
            
            // Obtener código HTTP de $http_response_header
            if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
                $http_code = (int)$m[1];
            }
            
            if ($response === false) {
                $error = 'No se pudo conectar con el servidor (file_get_contents falló)';
            }
        }
        
        if ($error) {
            error_log("Error calling {$url}: {$error}");
            return ['success' => false, 'error' => 'Error de conexión: ' . $error, 'http_code' => $http_code ?: 0];
        }
        
        if ($response === false || $response === '') {
            error_log("Empty response from {$url}. HTTP Code: {$http_code}");
            return ['success' => false, 'error' => 'No se recibió respuesta del servidor', 'http_code' => $http_code ?: 0];
        }
        
        $response = trim($response);
        
        // Si es HTML o error 500, extraer información útil
        if ($http_code >= 500 || stripos($response, '<!DOCTYPE') === 0 || stripos($response, '<html') === 0) {
            // Intentar extraer mensaje de error del HTML
            $error_msg = 'Error 500 del servidor Python';
            if (preg_match('/<title>(.*?)<\/title>/i', $response, $matches)) {
                $error_msg = $matches[1];
            } elseif (preg_match('/<h1>(.*?)<\/h1>/i', $response, $matches)) {
                $error_msg = $matches[1];
            } elseif (preg_match('/<p>(.*?)<\/p>/i', $response, $matches)) {
                $error_msg = substr($matches[1], 0, 100);
            }
            
            // Log para debugging
            error_log("Error 500 de Python - URL: $url - Response: " . substr($response, 0, 500));
            
            return [
                'success' => false,
                'error' => $error_msg,
                'http_code' => $http_code,
                'raw_response' => substr($response, 0, 500),
                'is_html_error' => true
            ];
        }
        
        // Intentar decodificar JSON
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Log para debugging
            error_log("JSON inválido de Python - URL: $url - Response: " . substr($response, 0, 500));
            
            return [
                'success' => false,
                'error' => 'El servidor no devolvió JSON válido: ' . json_last_error_msg(),
                'http_code' => $http_code,
                'raw_response' => substr($response, 0, 500)
            ];
        }
        
        $decoded['http_code'] = $http_code;
        return $decoded;
    }

    if ($action === 'create_order') {
        $input = @file_get_contents('php://input');
        if ($input === false) {
            send_json(false, 'No se recibieron datos');
        }
        
        $data = @json_decode($input, true);
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
        
        $result = call_python_api('ordenes.py/create', 'POST', [
            'direccion_envio' => $direccion,
            'ciudad_envio' => $ciudad,
            'estado_envio' => $estado,
            'codigo_postal_envio' => $cp,
            'telefono_envio' => $telefono,
            'nombre_destinatario' => $nombre,
            'notas_comprador' => $notas
        ]);
        
        if (!isset($result['success']) || !$result['success']) {
            send_json(false, $result['error'] ?? 'Error al crear la orden');
        }
        
        $orden_ids = [];
        if (isset($result['ordenes']) && is_array($result['ordenes'])) {
            foreach ($result['ordenes'] as $orden) {
                if (isset($orden['orden_id'])) {
                    $orden_ids[] = (int)$orden['orden_id'];
                }
            }
        }
        
        if (empty($orden_ids)) {
            send_json(false, 'No se crearon órdenes');
        }
        
        send_json(true, null, ['orden_ids' => $orden_ids]);
        
    } elseif ($action === 'create_paypal') {
        $input = @file_get_contents('php://input');
        if ($input === false) {
            send_json(false, 'No se recibieron datos');
        }
        
        $data = @json_decode($input, true);
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
        
        $result = call_python_api('paypal.py/create_order', 'POST', [
            'orden_ids' => json_encode($orden_ids)
        ]);
        
        if (!isset($result['success']) || !$result['success']) {
            send_json(false, $result['error'] ?? 'Error al crear pago en PayPal');
        }
        
        if (empty($result['approve_url'])) {
            send_json(false, 'No se recibió URL de PayPal');
        }
        
        send_json(true, null, ['approve_url' => $result['approve_url']]);
        
    } else {
        send_json(false, 'Acción no válida');
    }
    
} catch (Throwable $e) {
    error_log("Error en api_checkout.php: " . $e->getMessage() . " en línea " . $e->getLine());
    send_json(false, 'Error interno: ' . $e->getMessage());
}
