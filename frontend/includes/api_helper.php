<?php
/**
 * API Helper - Funciones para comunicarse con las APIs Python CGI
 * Esfero Marketplace
 */

// Configuración de la API — todos los valores vienen de variables de entorno (.env)
define('API_BASE_URL', getenv('API_BASE_URL') ?: 'http://localhost/esfero/backend/cgi-bin');
define('API_TIMEOUT', getenv('API_TIMEOUT') ? (int)getenv('API_TIMEOUT') : 30);
define('API_VERIFY_SSL', getenv('API_VERIFY_SSL') === 'false' ? false : true);

/**
 * Realiza una petición HTTP a la API de Python
 * 
 * @param string $endpoint Endpoint de la API (ej: 'usuarios.py/login')
 * @param string $method Método HTTP (GET, POST, PUT, DELETE)
 * @param array $data Datos a enviar
 * @param string $token Token JWT (opcional)
 * @return array Respuesta de la API
 */
function api_request($endpoint, $method = 'GET', $data = [], $token = null, $as_json = false) {
    $url = API_BASE_URL . '/' . ltrim($endpoint, '/');
    $method = strtoupper($method);

    // Headers base
    $headers = [
        'Accept: application/json'
    ];

    if ($token) {
        // Header estándar
        $headers[] = 'Authorization: Bearer ' . $token;
        // Header alternativo para entornos donde HTTP_AUTHORIZATION
        // no se propaga correctamente hasta el CGI
        $headers[] = 'X-Auth-Token: ' . $token;
    }

    // ==========================
    // Implementación con cURL (si está disponible)
    // ==========================
    if (function_exists('curl_init')) {
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => API_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => $method,
        ];

        // SOLO mandar Content-Type cuando hay cuerpo real
        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            if ($as_json) {
                $headers[] = 'Content-Type: application/json';
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            } else {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                $options[CURLOPT_POSTFIELDS] = http_build_query($data);
            }
        } elseif ($method === 'GET' && !empty($data)) {
            // GET con datos: solo query string, SIN Content-Type
            $url .= '?' . http_build_query($data);
            $options[CURLOPT_URL] = $url;
        }
        // NO enviar Content-Type en GET o DELETE sin cuerpo

        $options[CURLOPT_HTTPHEADER] = $headers;

        if (stripos(API_BASE_URL, 'https://') === 0) {
            $options[CURLOPT_SSL_VERIFYPEER] = API_VERIFY_SSL;
            $options[CURLOPT_SSL_VERIFYHOST] = API_VERIFY_SSL ? 2 : 0;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $error,
                'http_code' => 0
            ];
        }
        
        // Si no hay respuesta, devolver error
        if ($response === false || $response === '') {
            return [
                'success' => false,
                'error' => 'No se recibió respuesta del servidor',
                'http_code' => $http_code ?: 0
            ];
        }
    } else {
        // ==========================
        // Fallback sin cURL: stream_context + file_get_contents
        // ==========================

        // Configurar datos según el método
        $context_options = [
            'http' => [
                'method'  => $method,
                'timeout' => API_TIMEOUT,
                'ignore_errors' => true
            ]
        ];

        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            if ($as_json) {
                $headers[] = 'Content-Type: application/json';
                $context_options['http']['content'] = json_encode($data);
            } else {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                $context_options['http']['content'] = http_build_query($data);
            }
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        $context_options['http']['header'] = implode("\r\n", $headers) . "\r\n";

        $context = stream_context_create($context_options);
        $response = @file_get_contents($url, false, $context);

        $http_code = 0;
        if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
            $http_code = (int)$m[1];
        }

        if ($response === false || $response === '') {
            return [
                'success' => false,
                'error' => 'Error de conexión al contactar la API. El servidor no respondió.',
                'http_code' => $http_code
            ];
        }
    }

    // Validar que tenemos una respuesta antes de decodificar
    if (!isset($response) || $response === '') {
        return [
            'success' => false,
            'error' => 'No se recibió respuesta del servidor',
            'http_code' => $http_code ?: 0
        ];
    }

    // Decodificar respuesta
    $result = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // Log de depuración para entender qué está devolviendo realmente la API
        $log_file = __DIR__ . '/../../logs/api_debug.log';
        @file_put_contents(
            $log_file,
            "==== ".date('Y-m-d H:i:s')." ====\nURL: {$url}\nHTTP: {$http_code}\nJSON Error: " . json_last_error_msg() . "\nRespuesta cruda (primeros 1000 chars):\n" . substr($response, 0, 1000) . "\n\n",
            FILE_APPEND
        );
        
        // Log también en el log principal
        error_log("API no devolvió JSON válido. URL: {$url}, HTTP: {$http_code}, Error: " . json_last_error_msg());

        // Determinar mensaje de error más útil
        $error_msg = 'Error en el servidor';
        
        // Si es un error HTTP específico, ser más específico
        if ($http_code >= 500) {
            $error_msg = 'El servidor está experimentando problemas. Por favor, intenta nuevamente en unos momentos.';
        } elseif ($http_code === 404) {
            $error_msg = 'El servicio no está disponible. Por favor, contacta al administrador.';
        } elseif ($http_code === 0 || empty($http_code)) {
            $error_msg = 'No se pudo conectar con el servidor. Verifica tu conexión.';
        }
        
        return [
            'success' => false,
            'error' => $error_msg,
            'http_code' => $http_code
        ];
    }

    // Asegurar que $result sea un array
    if (!is_array($result)) {
        return [
            'success' => false,
            'error' => 'Respuesta del servidor en formato inválido',
            'http_code' => $http_code
        ];
    }

    // Agregar información de HTTP code y success si no está presente
    $result['http_code'] = $http_code;
    if (!isset($result['success'])) {
        $result['success'] = ($http_code >= 200 && $http_code < 300);
    }

    return $result;
}


// ═══════════════════════════════════════════════════════════════════════════
// FUNCIONES DE USUARIOS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Registra un nuevo usuario
 */
function api_register_user($email, $nombre, $password, $apellidos = '', $telefono = '', $rol = 'usuario') {
    return api_request('usuarios.py/register', 'POST', [
        'email' => $email,
        'nombre' => $nombre,
        'password' => $password,
        'apellidos' => $apellidos,
        'telefono' => $telefono,
        'rol' => $rol
    ]);
}

/**
 * Inicia sesión de usuario
 */
function api_login_user($email, $password) {
    return api_request('usuarios.py/login', 'POST', [
        'email' => $email,
        'password' => $password
    ]);
}

/**
 * Obtiene el perfil de un usuario
 */
function api_get_user_profile($user_id = null, $token = null) {
    $endpoint = $user_id ? "usuarios.py/profile/$user_id" : 'usuarios.py/profile';
    return api_request($endpoint, 'GET', [], $token);
}

/**
 * Actualiza el perfil de usuario
 */
function api_update_user_profile($data, $token) {
    return api_request('usuarios.py/profile', 'PUT', $data, $token);
}

/**
 * Obtiene lista de usuarios (solo admin)
 */
function api_get_users($filters = [], $token = null) {
    return api_request('usuarios.py', 'GET', $filters, $token);
}


// ═══════════════════════════════════════════════════════════════════════════
// FUNCIONES DE PRODUCTOS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Obtiene lista de productos con filtros
 */
function api_get_productos($filters = []) {
    return api_request('productos.py', 'GET', $filters);
}

/**
 * Obtiene un producto por ID
 */
function api_get_producto($producto_id) {
    return api_request("productos.py/producto/$producto_id", 'GET');
}

/**
 * Crea un nuevo producto
 */
function api_create_producto($data, $token) {
    return api_request('productos.py', 'POST', $data, $token);
}

/**
 * Actualiza un producto
 */
function api_update_producto($producto_id, $data, $token) {
    return api_request("productos.py/producto/$producto_id", 'PUT', $data, $token);
}

/**
 * Elimina un producto
 */
function api_delete_producto($producto_id, $token) {
    return api_request("productos.py/producto/$producto_id", 'DELETE', [], $token);
}


// ═══════════════════════════════════════════════════════════════════════════
// FUNCIONES DE CARRITO
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Obtiene el carrito del usuario
 */
function api_get_carrito($token) {
    return api_request('carrito.py', 'GET', [], $token);
}

/**
 * Agrega un producto al carrito
 */
function api_add_to_carrito($producto_id, $cantidad, $token) {
    return api_request('carrito.py/agregar', 'POST', [
        'producto_id' => $producto_id,
        'cantidad' => $cantidad
    ], $token);
}

/**
 * Actualiza la cantidad de un producto en el carrito
 */
function api_update_carrito_item($producto_id, $cantidad, $token) {
    return api_request('carrito.py/actualizar', 'PUT', [
        'producto_id' => $producto_id,
        'cantidad' => $cantidad
    ], $token);
}

/**
 * Elimina un producto del carrito
 */
function api_remove_from_carrito($producto_id, $token) {
    return api_request("carrito.py/eliminar/$producto_id", 'DELETE', [], $token);
}

/**
 * Vacía el carrito completo
 */
function api_clear_carrito($token) {
    return api_request('carrito.py/vaciar', 'DELETE', [], $token);
}


// ═══════════════════════════════════════════════════════════════════════════
// FUNCIONES DE ÓRDENES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Crea una orden desde el carrito
 */
function api_create_orden($datos_envio, $token) {
    return api_request('ordenes.py/crear', 'POST', $datos_envio, $token);
}

/**
 * Obtiene las órdenes del usuario
 */
function api_get_ordenes($tipo = 'compras', $token = null) {
    return api_request("ordenes.py?tipo=$tipo", 'GET', [], $token);
}

/**
 * Obtiene una orden por ID
 */
function api_get_orden($orden_id, $token) {
    return api_request("ordenes.py/orden/$orden_id", 'GET', [], $token);
}

/**
 * Actualiza el estado de una orden
 */
function api_update_orden_estado($orden_id, $estado, $token) {
    return api_request("ordenes.py/orden/$orden_id/estado", 'PUT', [
        'estado' => $estado
    ], $token);
}


// ═══════════════════════════════════════════════════════════════════════════
// FUNCIONES DE FAVORITOS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Obtiene los favoritos del usuario
 */
function api_get_favoritos($token) {
    return api_request('favoritos.py', 'GET', [], $token);
}

/**
 * Agrega un producto a favoritos
 */
function api_add_favorito($producto_id, $token) {
    return api_request('favoritos.py/agregar', 'POST', [
        'producto_id' => $producto_id
    ], $token);
}

/**
 * Elimina un producto de favoritos
 */
function api_remove_favorito($producto_id, $token) {
    return api_request("favoritos.py/eliminar/$producto_id", 'DELETE', [], $token);
}


// ═══════════════════════════════════════════════════════════════════════════
// FUNCIONES DE PAYPAL
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Crea una orden de pago en PayPal para una o varias órdenes internas
 */
function api_create_paypal_order($orden_ids) {
    $token = get_user_token();
    return api_request('paypal.py/create_order', 'POST', [
        'orden_ids' => $orden_ids
    ], $token, true);
}

/**
 * Captura el pago de una orden de PayPal
 */
function api_capture_paypal_order($paypal_order_id) {
    $token = get_user_token();
    return api_request('paypal.py/capture_order', 'POST', [
        'paypal_order_id' => $paypal_order_id
    ], $token, true);
}

/**
 * Consulta el estado de una orden de PayPal
 */
function api_get_paypal_order_status($paypal_order_id) {
    $token = get_user_token();
    return api_request('paypal.py/order_status', 'GET', [
        'paypal_order_id' => $paypal_order_id
    ], $token);
}


// ═══════════════════════════════════════════════════════════════════════════
// FUNCIONES DE ADMINISTRACIÓN
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Obtiene estadísticas del dashboard de admin
 */
function api_get_admin_stats($token) {
    return api_request('admin.py/estadisticas', 'GET', [], $token);
}

/**
 * Obtiene todos los usuarios (admin)
 */
function api_get_all_users($filters = [], $token = null) {
    return api_request('admin.py/usuarios', 'GET', $filters, $token);
}

/**
 * Actualiza el estado de un usuario (admin)
 */
function api_update_user_status($user_id, $estado, $token) {
    return api_request("admin.py/usuarios/$user_id/estado", 'PUT', [
        'estado' => $estado
    ], $token);
}

/**
 * Obtiene reportes y denuncias (admin)
 */
function api_get_reportes($token) {
    return api_request('admin.py/reportes', 'GET', [], $token);
}


// ═══════════════════════════════════════════════════════════════════════════
// FUNCIONES HELPER
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Obtiene el token JWT de la sesión
 */
function get_user_token() {
    if (isset($_SESSION['auth_token'])) {
        return $_SESSION['auth_token'];
    }
    return null;
}

/**
 * Guarda el token en la sesión
 */
function save_user_token($token) {
    $_SESSION['auth_token'] = $token;
}

/**
 * Elimina el token de la sesión
 */
function clear_user_token() {
    unset($_SESSION['auth_token']);
}

/**
 * Verifica si hay una sesión activa
 */
function is_logged_in() {
    return isset($_SESSION['auth_token']) && isset($_SESSION['user']);
}

/**
 * Obtiene los datos del usuario de la sesión
 */
function get_session_user() {
    return $_SESSION['user'] ?? null;
}

/**
 * Guarda los datos del usuario en la sesión
 */
function save_session_user($user_data) {
    $_SESSION['user'] = $user_data;
}

/**
 * Verifica si el usuario tiene un rol específico
 */
function user_has_role($role) {
    $user = get_session_user();
    return $user && isset($user['rol']) && $user['rol'] === $role;
}

/**
 * Verifica si el usuario es admin
 */
function is_admin() {
    return user_has_role('admin');
}

/**
 * Verifica si el usuario es vendedor
 */
function is_vendedor() {
    // Verificar si el usuario puede vender (rol vendedor/admin o campo puede_vender activo)
    if (!is_logged_in()) {
        return false;
    }
    
    // Si existe la función puede_vender, usarla (está en auth_middleware.php)
    if (function_exists('puede_vender')) {
        $user = get_session_user();
        return puede_vender($user['id'] ?? null);
    }
    
    // Fallback: verificar campo puede_vender en sesión
    $user = get_session_user();
    return ($user['rol'] ?? '') === 'usuario' && (bool)($user['puede_vender'] ?? false);
}

/**
 * Verifica si el usuario puede comprar (cualquier usuario no-admin)
 */
function is_cliente() {
    return user_has_role('usuario');
}

/**
 * Redirige si el usuario no está autenticado
 */
function require_login($redirect_to = '/login.php') {
    if (!is_logged_in()) {
        header('Location: ' . $redirect_to);
        exit;
    }
}

/**
 * Redirige si el usuario no tiene el rol requerido
 */
function require_role($role, $redirect_to = '/index.php') {
    require_login();

    // Para el rol "vendedor", usar la nueva lógica que incluye puede_vender
    if ($role === 'vendedor') {
        if (!is_vendedor()) {
            header('Location: ' . $redirect_to);
            exit;
        }
    } else {
        if (!user_has_role($role)) {
            header('Location: ' . $redirect_to);
            exit;
        }
    }
}

/**
 * Redirige si el usuario no tiene capacidad de vender (puede_vender=1 o admin).
 * Reemplaza require_role('vendedor') de forma semánticamente correcta.
 */
function require_vendedor($redirect_to = 'activar_vendedor.php') {
    require_login();
    if (!is_vendedor()) {
        header('Location: ' . $redirect_to);
        exit;
    }
}

/**
 * Formatea un precio en formato mexicano
 */
function format_price($price) {
    return '$' . number_format($price, 2, '.', ',') . ' MXN';
}

/**
 * Formatea una fecha en español
 */
function format_date($date, $format = 'd/m/Y H:i') {
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Sanitiza texto para mostrar en HTML
 */
function sanitize_output($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Helper para mb_strlen() - usa mbstring si está disponible, sino strlen()
 */
if (!function_exists('safe_strlen')) {
    function safe_strlen($string) {
        if (function_exists('mb_strlen')) {
            return mb_strlen($string, 'UTF-8');
        }
        return strlen($string);
    }
}

/**
 * Helper para mb_substr() - usa mbstring si está disponible, sino substr()
 */
if (!function_exists('safe_substr')) {
    function safe_substr($string, $start, $length = null) {
        if (function_exists('mb_substr')) {
            return mb_substr($string, $start, $length, 'UTF-8');
        }
        if ($length === null) {
            return substr($string, $start);
        }
        return substr($string, $start, $length);
    }
}

/**
 * Trunca texto a una longitud específica
 */
function truncate_text($text, $length = 100, $suffix = '...') {
    if (safe_strlen($text) > $length) {
        return safe_substr($text, 0, $length) . $suffix;
    }
    return $text;
}

/**
 * Genera un slug a partir de un texto
 */
function generate_slug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

/**
 * Valida un email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Genera un mensaje de error/éxito
 */
function show_message($message, $type = 'info') {
    $class_map = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $class = $class_map[$type] ?? 'alert-info';
    
    return '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">' .
           sanitize_output($message) .
           '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' .
           '</div>';
}

/**
 * Redirige con mensaje flash
 */
function redirect_with_message($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit;
}

/**
 * Obtiene y limpia el mensaje flash
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return show_message($message, $type);
    }
    return '';
}


// ═══════════════════════════════════════════════════════════════════════════
// FUNCIONES DE CARRITO
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Obtiene los items del carrito del usuario actual
 */
function api_get_cart_items() {
    $token = get_user_token();
    return api_request('carrito.py/items', 'GET', [], $token);
}

/**
 * Agrega un producto al carrito
 */
function api_add_to_cart($producto_id, $cantidad = 1) {
    $token = get_user_token();
    return api_request('carrito.py/add', 'POST', [
        'producto_id' => $producto_id,
        'cantidad' => $cantidad
    ], $token, true);
}

/**
 * Actualiza la cantidad de un item en el carrito
 */
function api_update_cart_item($carrito_id, $cantidad) {
    $token = get_user_token();
    return api_request('carrito.py/update', 'POST', [
        'carrito_id' => $carrito_id,
        'cantidad' => $cantidad
    ], $token, true);
}

/**
 * Elimina un item del carrito
 */
function api_remove_from_cart($carrito_id) {
    $token = get_user_token();
    return api_request('carrito.py/remove', 'POST', [
        'carrito_id' => $carrito_id
    ], $token, true);
}

/**
 * Vacía completamente el carrito
 */
function api_clear_cart() {
    $token = get_user_token();
    return api_request('carrito.py/clear', 'POST', [], $token);
}


// ═══════════════════════════════════════════════════════════════════════════
// FUNCIONES DE ÓRDENES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Crea una orden desde el carrito
 */
function api_create_order($direccion_envio, $ciudad_envio, $estado_envio, $codigo_postal_envio, $telefono_envio, $nombre_destinatario, $notas_comprador = '') {
    $token = get_user_token();
    return api_request('ordenes.py/create', 'POST', [
        'direccion_envio' => $direccion_envio,
        'ciudad_envio' => $ciudad_envio,
        'estado_envio' => $estado_envio,
        'codigo_postal_envio' => $codigo_postal_envio,
        'telefono_envio' => $telefono_envio,
        'nombre_destinatario' => $nombre_destinatario,
        'notas_comprador' => $notas_comprador
    ], $token, true);
}

/**
 * Obtiene los detalles de una orden
 */
function api_get_order_details($orden_id) {
    $token = get_user_token();
    return api_request("ordenes.py/details/{$orden_id}", 'GET', [], $token);
}

// ══════════════════════════════════════════════════════════════
// NOTIFICACIONES — helpers directos a MySQL (sin API Python)
// ══════════════════════════════════════════════════════════════

/**
 * Crea una notificación para un usuario.
 * Requiere que $pdo esté disponible en el contexto del llamador.
 *
 * @param PDO    $pdo
 * @param int    $usuario_id
 * @param string $tipo      'mensaje'|'orden'|'pago'|'resena'|'sistema'
 * @param string $titulo
 * @param string $mensaje
 * @param string $icono     Clase Font Awesome completa (ej. 'fas fa-envelope')
 * @param string|null $url  URL relativa al hacer clic (ej. 'mensajes.php?conversacion=...')
 */
function crear_notificacion(PDO $pdo, int $usuario_id, string $tipo, string $titulo, string $mensaje, string $icono = 'fas fa-bell', ?string $url = null): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, icono, url)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $tipo, $titulo, $mensaje, $icono, $url]);
    } catch (PDOException $e) {
        error_log('crear_notificacion: ' . $e->getMessage());
        // Fallo silencioso — no interrumpe el flujo principal
    }
}

/**
 * Devuelve el conteo de notificaciones NO leídas de un usuario.
 */
function get_notificaciones_count(PDO $pdo, int $usuario_id): int {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0");
        $stmt->execute([$usuario_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Devuelve las últimas N notificaciones de un usuario (para el dropdown del navbar).
 */
function get_notificaciones_recientes(PDO $pdo, int $usuario_id, int $limit = 5): array {
    try {
        $stmt = $pdo->prepare("
            SELECT id, tipo, titulo, mensaje, icono, url, leida, fecha_creacion
            FROM notificaciones
            WHERE usuario_id = ?
            ORDER BY fecha_creacion DESC
            LIMIT ?
        ");
        $stmt->execute([$usuario_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

