<?php
/**
 * Middleware de Autenticación
 * Gestiona autenticación con JWT y control de roles
 * Esfero Marketplace
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/api_helper.php';
require_once __DIR__ . '/db_connection.php';

/**
 * Inicializa la autenticación verificando sesión activa
 */
function init_auth() {
    // Verificar si hay token en la sesión
    if (isset($_SESSION['auth_token'])) {
        // Verificar si el token sigue válido
        $user = get_session_user();
        
        if ($user) {
            // Actualizar último acceso si ha pasado más de 5 minutos
            $last_activity = $_SESSION['last_activity'] ?? 0;
            if (time() - $last_activity > 300) {
                update_last_access($user['id']);
                $_SESSION['last_activity'] = time();
            }
            
            return true;
        }
    }
    
    return false;
}



/**
 * Actualiza la fecha de último acceso del usuario
 */
function update_last_access($user_id) {
    global $pdo;
    
    // Verificar que $pdo esté disponible
    if (!isset($pdo) || $pdo === null) {
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
    } catch (Exception $e) {
        error_log("Error al actualizar último acceso: " . $e->getMessage());
    }
}

/**
 * Cierra la sesión del usuario
 */
function logout_user() {
    // Limpiar sesión
    $_SESSION = [];
    
    // Destruir cookie de sesión
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destruir sesión
    session_destroy();
}

/**
 * Obtiene la URL de redirección según el rol del usuario
 */
function get_redirect_by_role($rol) {
    $redirects = [
        'admin'   => '/admin_dashboard.php',
        'usuario' => '/catalogo.php',
    ];
    
    return $redirects[$rol] ?? '/index.php';
}

/**
 * Verifica si el usuario actual tiene permisos específicos
 */
function user_can($permission) {
    global $pdo;
    
    // Verificar que $pdo esté disponible
    if (!isset($pdo) || $pdo === null) {
        return false;
    }
    
    $user = get_session_user();
    if (!$user) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM permisos
            WHERE rol = ? AND permiso = ? AND activo = 1
        ");
        $stmt->execute([$user['rol'], $permission]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
        
    } catch (Exception $e) {
        error_log("Error al verificar permiso: " . $e->getMessage());
        return false;
    }
}

/**
 * Middleware para proteger rutas
 */
function protect_route($required_role = null, $required_permission = null) {
    init_auth();
    
    // Verificar si está autenticado
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit;
    }
    
    $user = get_session_user();
    
    // Verificar rol si se requiere
    if ($required_role && $user['rol'] !== $required_role) {
        header('Location: /index.php?error=access_denied');
        exit;
    }
    
    // Verificar permiso si se requiere
    if ($required_permission && !user_can($required_permission)) {
        header('Location: /index.php?error=permission_denied');
        exit;
    }
    
    return true;
}

/**
 * Middleware para rutas de admin
 */
function require_admin() {
    return protect_route('admin');
}

// require_vendedor() is defined in api_helper.php — do not redeclare here

/**
 * Obtiene notificaciones no leídas del usuario
 */
function get_user_notifications($user_id, $limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notificaciones
            WHERE usuario_id = ? AND leida = 0 AND archivada = 0
            ORDER BY fecha_creacion DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error al obtener notificaciones: " . $e->getMessage());
        return [];
    }
}

/**
 * Cuenta notificaciones no leídas
 */
function count_unread_notifications($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM notificaciones
            WHERE usuario_id = ? AND leida = 0 AND archivada = 0
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'];
        
    } catch (Exception $e) {
        error_log("Error al contar notificaciones: " . $e->getMessage());
        return 0;
    }
}

/**
 * Marca una notificación como leída
 */
function mark_notification_read($notification_id, $user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notificaciones
            SET leida = 1, fecha_leida = NOW()
            WHERE id = ? AND usuario_id = ?
        ");
        return $stmt->execute([$notification_id, $user_id]);
        
    } catch (Exception $e) {
        error_log("Error al marcar notificación: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene contadores del dashboard del usuario
 */
// Función simple para obtener contador de favoritos
function get_favoritos_count($user_id) {
    global $pdo;
    
    if (!$pdo || !$user_id) {
        return 0;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM favoritos WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? (int)$result['count'] : 0;
    } catch (Exception $e) {
        error_log("Error al obtener contador de favoritos: " . $e->getMessage());
        return 0;
    }
}

// Función simple para obtener contador de carrito
function get_carrito_count($user_id) {
    global $pdo;

    if (!$pdo || !$user_id) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM carrito c
            JOIN productos p ON p.id = c.producto_id
            WHERE c.usuario_id = ? AND p.activo = 1 AND p.vendido = 0 AND p.stock > 0
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? (int)$result['count'] : 0;
    } catch (Exception $e) {
        error_log("Error al obtener contador de carrito: " . $e->getMessage());
        return 0;
    }
}

function get_user_dashboard_counters($user_id, $rol) {
    global $pdo;
    
    $counters = [];
    
    try {
        if (puede_vender($user_id) || $rol === 'admin') {
            // Productos activos
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM productos 
                WHERE vendedor_id = ? AND activo = 1
            ");
            $stmt->execute([$user_id]);
            $counters['productos_activos'] = $stmt->fetch()['count'];
            
            // Ventas pendientes
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM ordenes 
                WHERE vendedor_id = ? AND estado IN ('pendiente', 'confirmada', 'preparando')
            ");
            $stmt->execute([$user_id]);
            $counters['ventas_pendientes'] = $stmt->fetch()['count'];
            
            // Mensajes sin leer
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM mensajes 
                WHERE destinatario_id = ? AND leido = 0
            ");
            $stmt->execute([$user_id]);
            $counters['mensajes_sin_leer'] = $stmt->fetch()['count'];
        }
        
        // Todos los usuarios no-admin pueden comprar (rol dual C2C)
        if ($rol === 'usuario') {
            // Compras
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM ordenes
                WHERE comprador_id = ?
            ");
            $stmt->execute([$user_id]);
            $counters['mis_compras'] = $stmt->fetch()['count'];

            // Favoritos
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM favoritos
                WHERE usuario_id = ?
            ");
            $stmt->execute([$user_id]);
            $counters['favoritos'] = $stmt->fetch()['count'];

            // Carrito
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM carrito
                WHERE usuario_id = ?
            ");
            $stmt->execute([$user_id]);
            $counters['carrito'] = $stmt->fetch()['count'];
        }
        
        if ($rol === 'admin') {
            // Total usuarios
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM usuarios WHERE estado = 'activo'");
            $counters['total_usuarios'] = $stmt->fetch()['count'];
            
            // Reportes pendientes
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM reportes WHERE estado = 'pendiente'");
            $counters['reportes_pendientes'] = $stmt->fetch()['count'];
        }
        
    } catch (Exception $e) {
        error_log("Error al obtener contadores: " . $e->getMessage());
    }
    
    return $counters;
}

/**
 * Verifica si el usuario puede vender productos.
 * Un usuario puede vender si:
 * - Tiene puede_vender = 1 en la base de datos, O
 * - Tiene rol 'admin'
 */
function puede_vender($user_id = null) {
    global $pdo;
    
    // Obtener user_id si no se proporciona
    if (!$user_id) {
        $user = get_session_user();
        if (!$user) {
            return false;
        }
        $user_id = $user['id'] ?? null;
        if (!$user_id) {
            return false;
        }
    }
    
    // Verificar que PDO esté disponible
    if (!isset($pdo) || $pdo === null) {
        // Fallback: verificar sesión (sin PDO no podemos confirmar puede_vender)
        $user = get_session_user();
        if (!$user) {
            return false;
        }
        return ($user['rol'] ?? '') === 'usuario' && (bool)($user['puede_vender'] ?? false);
    }

    // Consultar la base de datos directamente para obtener rol y puede_vender
    try {
        $stmt = $pdo->prepare("SELECT rol, puede_vender FROM usuarios WHERE id = ? AND estado = 'activo'");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        
        if (!$user_data) {
            return false;
        }
        
        $rol          = $user_data['rol'] ?? 'usuario';
        $puede_vender = (bool)($user_data['puede_vender'] ?? false);

        // Solo usuarios con rol 'usuario' y puede_vender=1 son vendedores
        // Los admins tienen su propio panel y no deben ser tratados como vendedores
        return $rol === 'usuario' && $puede_vender;
        
    } catch (Exception $e) {
        error_log("Error al verificar puede_vender: " . $e->getMessage());
        // Fallback: verificar solo el rol de la sesión
        $user = get_session_user();
        if (!$user) {
            return false;
        }
        return ($user['rol'] ?? '') === 'usuario' && (bool)($user['puede_vender'] ?? false);
    }
}

// ============================================================
//  FUNCIONES DE ROL DUAL (comprador + vendedor)
// ============================================================

/**
 * Un usuario puede comprar si está autenticado, activo y NO es admin.
 * Esto incluye tanto clientes como vendedores (C2C bidireccional).
 */
function puede_comprar() {
    $user = get_session_user();
    if (!$user) return false;
    return ($user['rol'] ?? '') === 'usuario';
}

/**
 * Protege páginas de comprador: acepta cliente Y vendedor, rechaza admin.
 */
function require_comprador() {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit;
    }
    if (!puede_comprar()) {
        header('Location: /index.php?error=access_denied');
        exit;
    }
    return true;
}

// ============================================================
//  RATE LIMITING — intentos de login
// ============================================================

const LOGIN_MAX_INTENTOS  = 5;
const LOGIN_BLOQUEO_MINS  = 15;

/**
 * Verifica si el email o la IP está bloqueado por exceso de intentos.
 * Devuelve ['bloqueado' => false] o ['bloqueado' => true, 'segundos' => N].
 */
function verificar_rate_limit(string $email): array {
    global $pdo;

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
        : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $ip = trim($ip);

    if (!isset($pdo)) return ['bloqueado' => false];

    try {
        // Limpiar registros expirados primero
        $pdo->prepare("DELETE FROM intentos_login WHERE bloqueado_hasta IS NOT NULL AND bloqueado_hasta < NOW()")
            ->execute();

        $stmt = $pdo->prepare(
            "SELECT intentos, bloqueado_hasta FROM intentos_login WHERE (ip = ? OR email = ?) LIMIT 1"
        );
        $stmt->execute([$ip, $email]);
        $row = $stmt->fetch();

        if (!$row) return ['bloqueado' => false];

        if ($row['bloqueado_hasta'] !== null) {
            $restante = strtotime($row['bloqueado_hasta']) - time();
            if ($restante > 0) {
                return ['bloqueado' => true, 'segundos' => $restante, 'minutos' => ceil($restante / 60)];
            }
        }

        return ['bloqueado' => false, 'intentos' => (int)$row['intentos']];

    } catch (Exception $e) {
        error_log('rate_limit check error: ' . $e->getMessage());
        return ['bloqueado' => false];
    }
}

/**
 * Registra un intento fallido. Si supera el límite, bloquea.
 */
function registrar_intento_fallido(string $email): void {
    global $pdo;

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
        : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $ip = trim($ip);

    if (!isset($pdo)) return;

    try {
        // INSERT … ON DUPLICATE KEY UPDATE para upsert
        $stmt = $pdo->prepare(
            "INSERT INTO intentos_login (ip, email, intentos, bloqueado_hasta)
             VALUES (?, ?, 1, NULL)
             ON DUPLICATE KEY UPDATE
               intentos = intentos + 1,
               bloqueado_hasta = IF(
                   intentos + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NULL
               )"
        );
        $stmt->execute([$ip, $email, LOGIN_MAX_INTENTOS, LOGIN_BLOQUEO_MINS]);
    } catch (Exception $e) {
        error_log('registrar_intento_fallido error: ' . $e->getMessage());
    }
}

/**
 * Limpia intentos fallidos después de login exitoso.
 */
function limpiar_intentos_login(string $email): void {
    global $pdo;

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
        : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $ip = trim($ip);

    if (!isset($pdo)) return;

    try {
        $pdo->prepare("DELETE FROM intentos_login WHERE ip = ? OR email = ?")
            ->execute([$ip, $email]);
    } catch (Exception $e) {
        error_log('limpiar_intentos_login error: ' . $e->getMessage());
    }
}

// ============================================================
//  OWNERSHIP VERIFICATION — evitar IDOR
// ============================================================

/**
 * Verifica que un recurso (por tabla + columna de owner) pertenece al usuario logueado.
 * Ejemplo: verify_ownership('carrito', 'usuario_id', $carrito_id)
 * Retorna true si pertenece, false si no.
 */
function verify_ownership(string $table, string $owner_col, int $resource_id): bool {
    global $pdo;

    $user = get_session_user();
    if (!$user || !isset($pdo)) return false;

    // Whitelist de tablas permitidas para evitar SQL injection en nombre de tabla/columna
    $allowed = [
        'carrito'          => ['usuario_id'],
        'favoritos'        => ['usuario_id'],
        'ordenes'          => ['comprador_id', 'vendedor_id'],
        'productos'        => ['vendedor_id'],
        'mensajes'         => ['remitente_id', 'destinatario_id'],
        'calificaciones'   => ['calificador_id'],
        'ayuda_solicitudes'=> ['usuario_id'],
    ];

    if (!array_key_exists($table, $allowed) || !in_array($owner_col, $allowed[$table])) {
        error_log("verify_ownership: tabla/columna no permitida: $table.$owner_col");
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE id = ? AND `$owner_col` = ?");
        $stmt->execute([$resource_id, $user['id']]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log('verify_ownership error: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
//  DATOS DE REPUTACIÓN DEL VENDEDOR
// ============================================================

/**
 * Obtiene estadísticas de reputación del vendedor desde la vista v_vendedor_stats.
 */
function get_vendedor_stats(int $usuario_id): array {
    global $pdo;

    $defaults = [
        'calificacion_promedio'    => 0,
        'total_ventas_completadas' => 0,
        'total_resenas'            => 0,
        'pct_positivo'             => 100,
        'productos_activos'        => 0,
        'telefono_verificado'      => 0,
        'descripcion'              => '',
        'foto_perfil'              => '',
    ];

    if (!isset($pdo)) return $defaults;

    try {
        $stmt = $pdo->prepare("SELECT * FROM v_vendedor_stats WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $row = $stmt->fetch();
        return $row ? array_merge($defaults, $row) : $defaults;
    } catch (Exception $e) {
        error_log('get_vendedor_stats error: ' . $e->getMessage());
        return $defaults;
    }
}

// ============================================================
//  FIX: get_user_dashboard_counters para rol dual
//  Los vendedores también son compradores — incluir contadores de compra
// ============================================================
// Nota: la función original solo daba counters de compra a cliente/admin.
// La versión corregida incluye vendedor en el bloque de compra.
function get_buyer_counters(int $user_id): array {
    global $pdo;

    $counters = ['mis_compras' => 0, 'favoritos' => 0, 'carrito' => 0, 'pendientes_calificar' => 0];

    if (!isset($pdo)) return $counters;

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ordenes WHERE comprador_id = ?");
        $stmt->execute([$user_id]);
        $counters['mis_compras'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM favoritos WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $counters['favoritos'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM carrito WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $counters['carrito'] = (int)$stmt->fetchColumn();

        // Órdenes completadas sin calificar
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM ordenes o
             WHERE o.comprador_id = ?
               AND o.estado_pago = 'completado'
               AND NOT EXISTS (
                   SELECT 1 FROM calificaciones c
                   WHERE c.orden_id = o.id AND c.calificador_id = ?
               )"
        );
        $stmt->execute([$user_id, $user_id]);
        $counters['pendientes_calificar'] = (int)$stmt->fetchColumn();

    } catch (Exception $e) {
        error_log('get_buyer_counters error: ' . $e->getMessage());
    }

    return $counters;
}

// Inicializar autenticación automáticamente
init_auth();
