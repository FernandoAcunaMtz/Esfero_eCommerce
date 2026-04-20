<?php
/**
 * Conexión directa a MySQL desde PHP
 * Para casos donde necesitamos leer datos sin pasar por el backend CGI
 */

function get_db_connection_php() {
    // Leer credenciales del .env
    $env_file = __DIR__ . '/../../.env';
    $db_config = [
        'host'     => 'db',
        'port'     => '3306',
        'database' => 'esfero',
        'username' => 'esfero_user',
        'password' => 'esfero_pass'
    ];
    
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                
                switch ($key) {
                    case 'DB_HOST':
                        $db_config['host'] = $value;
                        break;
                    case 'DB_PORT':
                        $db_config['port'] = $value;
                        break;
                    case 'DB_NAME':
                        $db_config['database'] = $value;
                        break;
                    case 'DB_USER':
                        $db_config['username'] = $value;
                        break;
                    case 'DB_PASSWORD':
                        $db_config['password'] = $value;
                        break;
                }
            }
        }
    }
    
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
