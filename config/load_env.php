<?php
/**
 * Carga las variables de entorno desde .env
 * Esfero - Marketplace
 */

function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Dividir por el primer =
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover comillas si existen
            $value = trim($value, '"\'');
            
            // Establecer la variable de entorno si no existe
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
    
    return true;
}

// Cargar automáticamente al incluir este archivo
loadEnv();
