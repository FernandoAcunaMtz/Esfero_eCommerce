<?php
/**
 * Conexión directa a MySQL desde PHP
 * Para casos donde necesitamos leer datos sin pasar por el backend CGI
 */

function get_db_connection_php() {
    $db_config = [
        'host'     => getenv('DB_HOST')     ?: 'db',
        'port'     => getenv('DB_PORT')     ?: '3306',
        'database' => getenv('DB_NAME')     ?: 'esfero',
        'username' => getenv('DB_USER')     ?: 'esfero_user',
        'password' => getenv('DB_PASSWORD') ?: 'esfero_pass',
    ];

    $conn = new mysqli(
        $db_config['host'],
        $db_config['username'],
        $db_config['password'],
        $db_config['database'],
        (int)$db_config['port']
    );
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión a MySQL: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    return $conn;
}
