<?php
/**
 * Inicialización de logging para todos los scripts
 * Incluir este archivo al inicio de cada script PHP que necesite logging
 */

// Intentar usar el log de Apache primero (más confiable)
$apache_logs = [
    '/var/log/apache2/error.log',  // Ubuntu/Debian Apache
    '/var/log/httpd/error_log',     // CentOS/RHEL Apache
    'C:\\xampp\\apache\\logs\\error.log',
    'C:\\wamp64\\logs\\apache_error.log',
    'C:\\wamp\\logs\\apache_error.log',
];

$log_file = null;

// Buscar el primer log de Apache disponible (legible o escribible)
foreach ($apache_logs as $log_path) {
    if (file_exists($log_path)) {
        // Preferir logs escribibles, pero aceptar legibles
        if (is_writable($log_path)) {
            $log_file = $log_path;
            break;
        } elseif (is_readable($log_path) && !$log_file) {
            // Guardar como segunda opción si es legible
            $log_file = $log_path;
        }
    }
}

// Si no hay log de Apache, intentar usar el log por defecto del sistema
if (!$log_file) {
    $default_log = ini_get('error_log');
    if (!empty($default_log) && (file_exists($default_log) || is_writable(dirname($default_log)))) {
        $log_file = $default_log;
    }
}

// Si aún no hay log, intentar crear uno en el directorio temporal del usuario
if (!$log_file) {
    $temp_dir = sys_get_temp_dir();
    $user_log = $temp_dir . DIRECTORY_SEPARATOR . 'esfero_php_error.log';
    
    // Intentar crear el archivo en el directorio temporal
    if (@touch($user_log) || file_exists($user_log)) {
        $log_file = $user_log;
    }
}

// Si encontramos un log, configurarlo
if ($log_file) {
    ini_set('error_log', $log_file);
    ini_set('log_errors', '1');
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    
    // Log de inicialización (solo la primera vez para no llenar el log)
    static $logging_initialized = false;
    if (!$logging_initialized) {
        error_log("[" . date('Y-m-d H:i:s') . "] Sistema de logging inicializado. Log: $log_file");
        $logging_initialized = true;
    }
} else {
    // Fallback: usar configuración por defecto del sistema
    ini_set('log_errors', '1');
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

