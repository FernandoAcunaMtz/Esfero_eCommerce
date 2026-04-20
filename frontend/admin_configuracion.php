<?php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/api_helper.php';
session_start();
require_role('admin', 'index.php');

// Obtener información del sistema
$info_sistema = [];

// Estado del servidor
$info_sistema['servidor_online'] = true;
$info_sistema['fecha_actual'] = date('Y-m-d H:i:s');
$info_sistema['timezone'] = date_default_timezone_get();

// Información de PHP
$info_sistema['php_version'] = PHP_VERSION;
$info_sistema['php_sapi'] = php_sapi_name();
$info_sistema['php_memory_limit'] = ini_get('memory_limit');
$info_sistema['php_max_execution_time'] = ini_get('max_execution_time');
$info_sistema['php_upload_max_filesize'] = ini_get('upload_max_filesize');
$info_sistema['php_post_max_size'] = ini_get('post_max_size');

// Estado de la base de datos
$info_sistema['bd_estado'] = 'Desconectada';
$info_sistema['bd_version'] = 'N/A';
$info_sistema['bd_nombre'] = 'N/A';
$info_sistema['bd_host'] = 'N/A';
$info_sistema['bd_charset'] = 'N/A';

try {
    global $pdo;
    if (isset($pdo)) {
        $info_sistema['bd_estado'] = 'Conectada';
        
        // Obtener versión de MySQL
        $stmt = $pdo->query("SELECT VERSION() as version");
        $result = $stmt->fetch();
        $info_sistema['bd_version'] = $result['version'] ?? 'N/A';
        
        // Obtener nombre de la base de datos
        $stmt = $pdo->query("SELECT DATABASE() as db_name");
        $result = $stmt->fetch();
        $info_sistema['bd_nombre'] = $result['db_name'] ?? 'N/A';
        
        // Obtener charset
        $stmt = $pdo->query("SELECT @@character_set_database as charset");
        $result = $stmt->fetch();
        $info_sistema['bd_charset'] = $result['charset'] ?? 'N/A';
        
        // Obtener host
        $stmt = $pdo->query("SELECT @@hostname as hostname");
        $result = $stmt->fetch();
        $info_sistema['bd_host'] = $result['hostname'] ?? 'N/A';
    }
} catch (Exception $e) {
    $info_sistema['bd_estado'] = 'Error: ' . $e->getMessage();
}

// Información del servidor
$info_sistema['servidor_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
$info_sistema['servidor_os'] = PHP_OS;
$info_sistema['servidor_protocolo'] = $_SERVER['SERVER_PROTOCOL'] ?? 'N/A';
$info_sistema['servidor_nombre'] = $_SERVER['SERVER_NAME'] ?? 'N/A';
$info_sistema['document_root'] = $_SERVER['DOCUMENT_ROOT'] ?? 'N/A';

// Espacio en disco (si es posible)
$info_sistema['disco_total'] = 'N/A';
$info_sistema['disco_libre'] = 'N/A';
$info_sistema['disco_usado'] = 'N/A';
$info_sistema['disco_porcentaje'] = 'N/A';

if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
    try {
        $total = disk_total_space(__DIR__);
        $free = disk_free_space(__DIR__);
        $used = $total - $free;
        
        if ($total !== false && $free !== false) {
            $info_sistema['disco_total'] = formatBytes($total);
            $info_sistema['disco_libre'] = formatBytes($free);
            $info_sistema['disco_usado'] = formatBytes($used);
            $info_sistema['disco_porcentaje'] = round(($used / $total) * 100, 2) . '%';
        }
    } catch (Exception $e) {
        // Ignorar errores de disco
    }
}

// Estadísticas del sistema
$stats = [];
try {
    global $pdo;
    if (isset($pdo)) {
        // Total de usuarios
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
        $stats['total_usuarios'] = $stmt->fetch()['total'];
        
        // Total de productos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos");
        $stats['total_productos'] = $stmt->fetch()['total'];
        
        // Total de órdenes
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM ordenes");
        $stats['total_ordenes'] = $stmt->fetch()['total'];
        
        // Tamaño de la base de datos
        $stmt = $pdo->query("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        $result = $stmt->fetch();
        $stats['bd_tamaño'] = ($result['size_mb'] ?? 0) . ' MB';
    }
} catch (Exception $e) {
    error_log("Error al obtener estadísticas: " . $e->getMessage());
}

// Información de Seguridad
$info_seguridad = [];

// JWT y Tokens
$info_seguridad['jwt_algorithm'] = 'RS256';
$info_seguridad['jwt_expiration'] = '24 horas'; // Por defecto según jwt_tools.py
$info_seguridad['jwt_token_en_sesion'] = isset($_SESSION['auth_token']) ? 'Sí' : 'No';

// Verificar si existen las claves JWT
$jwt_private_path = __DIR__ . '/../../backend/keys/jwt_private.pem';
$jwt_public_path = __DIR__ . '/../../backend/keys/jwt_public.pem';
$info_seguridad['jwt_private_key_exists'] = file_exists($jwt_private_path) ? 'Sí' : 'No';
$info_seguridad['jwt_public_key_exists'] = file_exists($jwt_public_path) ? 'Sí' : 'No';

if (file_exists($jwt_private_path)) {
    $private_key_info = openssl_pkey_get_private(file_get_contents($jwt_private_path));
    if ($private_key_info) {
        $key_details = openssl_pkey_get_details($private_key_info);
        $info_seguridad['jwt_key_bits'] = $key_details['bits'] ?? 'N/A';
        $info_seguridad['jwt_key_type'] = $key_details['type'] === OPENSSL_KEYTYPE_RSA ? 'RSA' : 'N/A';
    }
}

// OpenSSL
$info_seguridad['openssl_version'] = OPENSSL_VERSION_TEXT ?? 'N/A';
$info_seguridad['openssl_available'] = extension_loaded('openssl') ? 'Disponible' : 'No disponible';

// Sesiones PHP
$info_seguridad['session_status'] = session_status() === PHP_SESSION_ACTIVE ? 'Activa' : 'Inactiva';
$info_seguridad['session_name'] = session_name();
$info_seguridad['session_save_path'] = session_save_path();
$info_seguridad['session_cookie_httponly'] = ini_get('session.cookie_httponly') ? 'Sí' : 'No';
$info_seguridad['session_cookie_secure'] = ini_get('session.cookie_secure') ? 'Sí' : 'No';
$info_seguridad['session_cookie_samesite'] = ini_get('session.cookie_samesite') ?: 'Lax';

// Hash y Criptografía
$info_seguridad['password_hash_algo'] = PASSWORD_DEFAULT === PASSWORD_BCRYPT ? 'bcrypt' : (PASSWORD_DEFAULT === PASSWORD_ARGON2ID ? 'argon2id' : 'N/A');
$info_seguridad['hash_available'] = [];
$info_seguridad['hash_available']['md5'] = function_exists('md5') ? 'Sí' : 'No';
$info_seguridad['hash_available']['sha1'] = function_exists('sha1') ? 'Sí' : 'No';
$info_seguridad['hash_available']['sha256'] = function_exists('hash') && in_array('sha256', hash_algos()) ? 'Sí' : 'No';
$info_seguridad['hash_available']['bcrypt'] = defined('PASSWORD_BCRYPT') ? 'Sí' : 'No';
$info_seguridad['hash_available']['argon2'] = defined('PASSWORD_ARGON2ID') ? 'Sí' : 'No';

// Configuración de Seguridad PHP
$info_seguridad['php_allow_url_fopen'] = ini_get('allow_url_fopen') ? 'Habilitado' : 'Deshabilitado';
$info_seguridad['php_allow_url_include'] = ini_get('allow_url_include') ? 'Habilitado' : 'Deshabilitado';
$info_seguridad['php_display_errors'] = ini_get('display_errors') ? 'Sí' : 'No';
$info_seguridad['php_expose_php'] = ini_get('expose_php') ? 'Sí' : 'No';
$info_seguridad['php_disable_functions'] = ini_get('disable_functions') ?: 'Ninguna';

// HTTPS/SSL
$info_seguridad['https_enabled'] = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                                   (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'Sí' : 'No';
$info_seguridad['server_port'] = $_SERVER['SERVER_PORT'] ?? 'N/A';

// Headers de Seguridad
$info_seguridad['headers'] = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    $info_seguridad['headers']['x_frame_options'] = $headers['X-Frame-Options'] ?? 'No configurado';
    $info_seguridad['headers']['x_content_type_options'] = $headers['X-Content-Type-Options'] ?? 'No configurado';
    $info_seguridad['headers']['x_xss_protection'] = $headers['X-XSS-Protection'] ?? 'No configurado';
    $info_seguridad['headers']['strict_transport_security'] = $headers['Strict-Transport-Security'] ?? 'No configurado';
}

// Función para formatear bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Función para obtener color según estado
function getStatusColor($status) {
    if (stripos($status, 'conectada') !== false || stripos($status, 'online') !== false) {
        return '#0C9268'; // Verde
    } elseif (stripos($status, 'error') !== false || stripos($status, 'desconectada') !== false) {
        return '#dc3545'; // Rojo
    }
    return '#F6A623'; // Amarillo
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Configuración del Sistema - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <div style="width: 100%; margin: 100px auto 50px; padding: 2rem;">
        <h1 style="color: #dc3545; margin-bottom: 2rem; font-size: clamp(1.5rem, 4vw, 2.5rem);">
            <i class="fas fa-cog"></i> Configuración del Sistema
        </h1>
        
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem;" id="adminConfigLayout">
            <?php include 'components/sidebar_admin.php'; ?>
            
            <div>
                <!-- Estado General -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); margin-bottom: 2rem;">
                    <h2 style="color: #0D87A8; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-heartbeat"></i> Estado del Sistema
                    </h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                        <div style="text-align: center; padding: 1.5rem; background: #f8f9fa; border-radius: 10px;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-server" style="color: <?php echo getStatusColor($info_sistema['servidor_online'] ? 'online' : 'offline'); ?>;"></i>
                            </div>
                            <div style="font-weight: bold; color: #0D87A8;">Servidor</div>
                            <div style="color: <?php echo getStatusColor($info_sistema['servidor_online'] ? 'online' : 'offline'); ?>; font-weight: 600;">
                                <?php echo $info_sistema['servidor_online'] ? 'En Línea' : 'Desconectado'; ?>
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 1.5rem; background: #f8f9fa; border-radius: 10px;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-database" style="color: <?php echo getStatusColor($info_sistema['bd_estado']); ?>;"></i>
                            </div>
                            <div style="font-weight: bold; color: #0D87A8;">Base de Datos</div>
                            <div style="color: <?php echo getStatusColor($info_sistema['bd_estado']); ?>; font-weight: 600;">
                                <?php echo htmlspecialchars($info_sistema['bd_estado']); ?>
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 1.5rem; background: #f8f9fa; border-radius: 10px;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-clock" style="color: #0D87A8;"></i>
                            </div>
                            <div style="font-weight: bold; color: #0D87A8;">Fecha/Hora</div>
                            <div style="color: #666; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($info_sistema['fecha_actual']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Información Técnica -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;" id="adminConfigGrid">
                    <!-- Información de PHP -->
                    <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                        <h2 style="color: #0D87A8; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fab fa-php"></i> Información de PHP
                        </h2>
                        
                        <div style="display: grid; gap: 1rem;">
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">Versión:</span>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['php_version']); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">SAPI:</span>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['php_sapi']); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">Límite de Memoria:</span>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['php_memory_limit']); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">Tiempo Máx. Ejecución:</span>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['php_max_execution_time']); ?>s</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">Upload Máx.:</span>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['php_upload_max_filesize']); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">POST Máx.:</span>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['php_post_max_size']); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">Timezone:</span>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['timezone']); ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Información de Base de Datos -->
                    <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                        <h2 style="color: #0D87A8; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-database"></i> Base de Datos
                        </h2>
                        
                        <div style="display: grid; gap: 1rem;">
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">Estado:</span>
                                <strong style="color: <?php echo getStatusColor($info_sistema['bd_estado']); ?>;">
                                    <?php echo htmlspecialchars($info_sistema['bd_estado']); ?>
                                </strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">Versión MySQL:</span>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['bd_version']); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">Nombre BD:</span>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['bd_nombre']); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">Host:</span>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['bd_host']); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">Charset:</span>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['bd_charset']); ?></strong>
                            </div>
                            <?php if (!empty($stats['bd_tamaño'])): ?>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <span style="color: #666;">Tamaño BD:</span>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($stats['bd_tamaño']); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Información del Servidor -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); margin-bottom: 2rem;">
                    <h2 style="color: #0D87A8; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-server"></i> Información del Servidor
                    </h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                            <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Sistema Operativo</div>
                            <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['servidor_os']); ?></strong>
                        </div>
                        <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                            <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Software del Servidor</div>
                            <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['servidor_software']); ?></strong>
                        </div>
                        <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                            <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Protocolo</div>
                            <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['servidor_protocolo']); ?></strong>
                        </div>
                        <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                            <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Nombre del Servidor</div>
                            <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_sistema['servidor_nombre']); ?></strong>
                        </div>
                    </div>
                </div>
                
                <!-- Espacio en Disco -->
                <?php if ($info_sistema['disco_total'] !== 'N/A'): ?>
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); margin-bottom: 2rem;">
                    <h2 style="color: #0D87A8; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-hdd"></i> Espacio en Disco
                    </h2>
                    
                    <div style="margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span style="color: #666;">Usado: <?php echo htmlspecialchars($info_sistema['disco_usado']); ?></span>
                            <span style="color: #666;">Libre: <?php echo htmlspecialchars($info_sistema['disco_libre']); ?></span>
                            <span style="color: #666;">Total: <?php echo htmlspecialchars($info_sistema['disco_total']); ?></span>
                        </div>
                        <div style="background: #f0f0f0; height: 20px; border-radius: 10px; overflow: hidden;">
                            <div style="background: linear-gradient(90deg, #0C9268, #0D87A8); height: 100%; width: <?php echo htmlspecialchars($info_sistema['disco_porcentaje']); ?>; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.75rem; font-weight: bold;">
                                <?php echo htmlspecialchars($info_sistema['disco_porcentaje']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Estadísticas del Sistema -->
                <?php if (!empty($stats)): ?>
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); margin-bottom: 2rem;">
                    <h2 style="color: #0D87A8; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-chart-line"></i> Estadísticas del Sistema
                    </h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                        <div style="text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #0D87A8, #0C9268); border-radius: 10px; color: white;">
                            <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem;">
                                <?php echo number_format($stats['total_usuarios'] ?? 0); ?>
                            </div>
                            <div style="opacity: 0.9;">Usuarios Totales</div>
                        </div>
                        <div style="text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #F6A623, #f37d00); border-radius: 10px; color: white;">
                            <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem;">
                                <?php echo number_format($stats['total_productos'] ?? 0); ?>
                            </div>
                            <div style="opacity: 0.9;">Productos Totales</div>
                        </div>
                        <div style="text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #8E2DE2, #4A00E0); border-radius: 10px; color: white;">
                            <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem;">
                                <?php echo number_format($stats['total_ordenes'] ?? 0); ?>
                            </div>
                            <div style="opacity: 0.9;">Órdenes Totales</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Información de Seguridad -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); margin-bottom: 2rem;">
                    <h2 style="color: #0D87A8; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-shield-alt"></i> Especificaciones de Seguridad
                    </h2>
                    
                    <!-- JWT y Tokens -->
                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #dc3545; margin-bottom: 1rem; font-size: 1.2rem;">
                            <i class="fas fa-key"></i> JWT (JSON Web Tokens)
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Algoritmo de Firma</div>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_seguridad['jwt_algorithm']); ?></strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Expiración de Tokens</div>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_seguridad['jwt_expiration']); ?></strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Token en Sesión</div>
                                <strong style="color: <?php echo $info_seguridad['jwt_token_en_sesion'] === 'Sí' ? '#0C9268' : '#666'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['jwt_token_en_sesion']); ?>
                                </strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Clave Privada RSA</div>
                                <strong style="color: <?php echo $info_seguridad['jwt_private_key_exists'] === 'Sí' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['jwt_private_key_exists']); ?>
                                </strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Clave Pública RSA</div>
                                <strong style="color: <?php echo $info_seguridad['jwt_public_key_exists'] === 'Sí' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['jwt_public_key_exists']); ?>
                                </strong>
                            </div>
                            <?php if (isset($info_seguridad['jwt_key_bits'])): ?>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Tamaño de Clave</div>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_seguridad['jwt_key_bits']); ?> bits</strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Tipo de Clave</div>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_seguridad['jwt_key_type']); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- OpenSSL -->
                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #dc3545; margin-bottom: 1rem; font-size: 1.2rem;">
                            <i class="fas fa-lock"></i> OpenSSL
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Estado</div>
                                <strong style="color: <?php echo $info_seguridad['openssl_available'] === 'Disponible' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['openssl_available']); ?>
                                </strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Versión</div>
                                <strong style="color: #0D87A8; font-size: 0.9rem;"><?php echo htmlspecialchars($info_seguridad['openssl_version']); ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sesiones PHP -->
                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #dc3545; margin-bottom: 1rem; font-size: 1.2rem;">
                            <i class="fas fa-user-shield"></i> Sesiones PHP
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Estado</div>
                                <strong style="color: <?php echo $info_seguridad['session_status'] === 'Activa' ? '#0C9268' : '#666'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['session_status']); ?>
                                </strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Nombre de Sesión</div>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_seguridad['session_name']); ?></strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Cookie HttpOnly</div>
                                <strong style="color: <?php echo $info_seguridad['session_cookie_httponly'] === 'Sí' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['session_cookie_httponly']); ?>
                                </strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Cookie Secure</div>
                                <strong style="color: <?php echo $info_seguridad['session_cookie_secure'] === 'Sí' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['session_cookie_secure']); ?>
                                </strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">SameSite</div>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_seguridad['session_cookie_samesite']); ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hash y Criptografía -->
                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #dc3545; margin-bottom: 1rem; font-size: 1.2rem;">
                            <i class="fas fa-fingerprint"></i> Hash y Criptografía
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Algoritmo Password</div>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_seguridad['password_hash_algo']); ?></strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">SHA-256</div>
                                <strong style="color: <?php echo $info_seguridad['hash_available']['sha256'] === 'Sí' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['hash_available']['sha256']); ?>
                                </strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">bcrypt</div>
                                <strong style="color: <?php echo $info_seguridad['hash_available']['bcrypt'] === 'Sí' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['hash_available']['bcrypt']); ?>
                                </strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Argon2</div>
                                <strong style="color: <?php echo $info_seguridad['hash_available']['argon2'] === 'Sí' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['hash_available']['argon2']); ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- HTTPS/SSL -->
                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #dc3545; margin-bottom: 1rem; font-size: 1.2rem;">
                            <i class="fas fa-lock"></i> HTTPS/SSL
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">HTTPS Habilitado</div>
                                <strong style="color: <?php echo $info_seguridad['https_enabled'] === 'Sí' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['https_enabled']); ?>
                                </strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">Puerto del Servidor</div>
                                <strong style="color: #0D87A8;"><?php echo htmlspecialchars($info_seguridad['server_port']); ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Configuración de Seguridad PHP -->
                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #dc3545; margin-bottom: 1rem; font-size: 1.2rem;">
                            <i class="fas fa-cog"></i> Configuración de Seguridad PHP
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">allow_url_fopen</div>
                                <strong style="color: <?php echo $info_seguridad['php_allow_url_fopen'] === 'Deshabilitado' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['php_allow_url_fopen']); ?>
                                </strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">allow_url_include</div>
                                <strong style="color: <?php echo $info_seguridad['php_allow_url_include'] === 'Deshabilitado' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['php_allow_url_include']); ?>
                                </strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">display_errors</div>
                                <strong style="color: <?php echo $info_seguridad['php_display_errors'] === 'No' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['php_display_errors']); ?>
                                </strong>
                            </div>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.25rem;">expose_php</div>
                                <strong style="color: <?php echo $info_seguridad['php_expose_php'] === 'No' ? '#0C9268' : '#dc3545'; ?>;">
                                    <?php echo htmlspecialchars($info_seguridad['php_expose_php']); ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <style>
    /* Responsividad para admin configuración */
    @media (max-width: 968px) {
        #adminConfigLayout {
            grid-template-columns: 1fr !important;
        }
        
        #adminConfigGrid {
            grid-template-columns: 1fr !important;
        }
    }
    
    @media (max-width: 640px) {
        div[style*="width: 100%"] {
            padding: 1rem !important;
        }
    }
    </style>
</body>
</html>

