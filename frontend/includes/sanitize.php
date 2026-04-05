<?php
/**
 * Funciones de sanitización y validación centralizadas
 * Esfero Marketplace
 */

/**
 * Helper para mb_strlen() - usa mbstring si está disponible, sino strlen()
 * SIMPLIFICADO: solo usa strlen() nativo para evitar errores
 */
function safe_strlen($string) {
    return strlen((string)$string);
}

/**
 * Helper para mb_substr() - usa mbstring si está disponible, sino substr()
 * SIMPLIFICADO: solo usa substr() nativo para evitar errores
 */
function safe_substr($string, $start, $length = null) {
    $string = (string)$string;
    if ($length === null) {
        return substr($string, $start);
    }
    return substr($string, $start, $length);
}

/**
 * Sanitiza texto para mostrar en HTML (escapar caracteres especiales)
 */
function sanitize_html($text) {
    if ($text === null || $text === '') {
        return '';
    }
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitiza texto para usar en atributos HTML
 */
function sanitize_attr($text) {
    if ($text === null || $text === '') {
        return '';
    }
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitiza texto para usar en JavaScript (escapar comillas y caracteres especiales)
 */
function sanitize_js($text) {
    if ($text === null || $text === '') {
        return '';
    }
    $text = (string)$text;
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace("'", "\\'", $text);
    $text = str_replace('"', '\\"', $text);
    $text = str_replace("\n", "\\n", $text);
    $text = str_replace("\r", "\\r", $text);
    return $text;
}

/**
 * Sanitiza y valida un email - SIMPLIFICADO
 */
function sanitize_email($email) {
    if ($email === null) {
        return false;
    }
    
    $email = trim((string)$email);
    if (empty($email)) {
        return false;
    }
    
    // Validar email directamente (filter_var ya sanitiza)
    $validated = filter_var($email, FILTER_VALIDATE_EMAIL);
    if ($validated === false) {
        return false;
    }
    
    return $validated;
}

/**
 * Sanitiza un número entero
 * Optimizado para combinar sanitización y validación
 */
function sanitize_int($value, $min = null, $max = null) {
    // Manejar null o valores vacíos
    if ($value === null || $value === '') {
        return false;
    }
    
    // Primero sanitizar, luego validar en una sola operación optimizada
    $sanitized = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    $validated = filter_var($sanitized, FILTER_VALIDATE_INT);
    
    if ($validated === false) {
        return false;
    }
    
    // Validar rango si se especifica
    if ($min !== null && $validated < $min) {
        return false;
    }
    
    if ($max !== null && $validated > $max) {
        return false;
    }
    
    return $validated;
}

/**
 * Sanitiza un número decimal
 * Optimizado para combinar sanitización y validación
 */
function sanitize_float($value, $min = null, $max = null) {
    // Manejar null o valores vacíos
    if ($value === null || $value === '') {
        return false;
    }
    
    // Primero sanitizar, luego validar en una sola operación optimizada
    $sanitized = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $validated = filter_var($sanitized, FILTER_VALIDATE_FLOAT);
    
    if ($validated === false) {
        return false;
    }
    
    // Validar rango si se especifica
    if ($min !== null && $validated < $min) {
        return false;
    }
    
    if ($max !== null && $validated > $max) {
        return false;
    }
    
    return $validated;
}

/**
 * Sanitiza texto plano - SIMPLIFICADO: solo limpia básicamente
 */
function sanitize_text($text, $max_length = null) {
    if ($text === null || $text === '') {
        return '';
    }
    
    $text = (string)$text;
    
    // Eliminar tags HTML
    $text = strip_tags($text);
    
    // Trim espacios
    $text = trim($text);
    
    // Validar longitud
    if ($max_length !== null && $max_length > 0 && strlen($text) > $max_length) {
        $text = substr($text, 0, $max_length);
    }
    
    return $text;
}

/**
 * Sanitiza texto permitiendo HTML básico (para descripciones)
 * Optimizado para evitar problemas con texto muy largo
 */
function sanitize_text_html($text, $max_length = null) {
    if ($text === null || $text === '') {
        return '';
    }
    
    $text = (string)$text;
    
    // Limitar longitud antes de procesar para evitar problemas de rendimiento
    $max_process_length = 50000; // Límite razonable para procesamiento
    if (strlen($text) > $max_process_length) {
        $text = substr($text, 0, $max_process_length);
    }
    
    // Permitir solo tags HTML básicos y seguros
    $allowed_tags = '<p><br><strong><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowed_tags);
    
    // Limpiar atributos de los tags permitidos
    $cleaned = @preg_replace('/<([a-z][a-z0-9]*)[^>]*>/i', '<$1>', $text);
    if ($cleaned !== null) {
        $text = $cleaned;
    }
    // Si preg_replace falla, mantener el texto ya procesado por strip_tags
    
    if ($max_length !== null && safe_strlen($text) > $max_length) {
        $text = safe_substr($text, 0, $max_length);
    }
    
    return $text;
}

/**
 * Sanitiza una URL
 * Optimizado para evitar múltiples llamadas a filter_var y agregar límite de longitud
 */
function sanitize_url($url, $max_length = 2048) {
    if ($url === null) {
        return false;
    }
    
    $url = trim((string)$url);
    if (empty($url)) {
        return false;
    }
    
    // Validar longitud máxima (estándar HTTP: 2048 caracteres)
    if ($max_length > 0 && strlen($url) > $max_length) {
        return false;
    }
    
    // Validar directamente (filter_var con VALIDATE_URL ya sanitiza)
    $validated = filter_var($url, FILTER_VALIDATE_URL);
    
    // Si falla la validación estricta, intentar sanitizar primero
    if ($validated === false) {
        $sanitized = filter_var($url, FILTER_SANITIZE_URL);
        $validated = filter_var($sanitized, FILTER_VALIDATE_URL);
    }
    
    return $validated !== false ? $validated : false;
}

/**
 * Sanitiza un código postal mexicano
 */
function sanitize_codigo_postal($cp) {
    $cp = trim((string)$cp);
    // Código postal mexicano: 5 dígitos
    if (preg_match('/^\d{5}$/', $cp)) {
        return $cp;
    }
    return false;
}

/**
 * Sanitiza un teléfono mexicano
 */
function sanitize_telefono($telefono) {
    $telefono = trim((string)$telefono);
    // Eliminar caracteres no numéricos excepto +, espacios, guiones y paréntesis
    $telefono = preg_replace('/[^\d\+\s\-\(\)]/', '', $telefono);
    // Validar que tenga al menos 10 dígitos
    $digits = preg_replace('/\D/', '', $telefono);
    if (strlen($digits) >= 10 && strlen($digits) <= 15) {
        return $telefono;
    }
    return false;
}

/**
 * Valida que un texto no contenga caracteres peligrosos para SQL
 * (aunque usamos prepared statements, esto es una capa adicional)
 * Optimizado para evitar problemas de rendimiento
 */
function validate_no_sql_injection($text) {
    if ($text === null || $text === '') {
        return true;
    }
    
    $text = (string)$text;
    
    // Limitar longitud para evitar problemas de rendimiento
    if (strlen($text) > 10000) {
        return false; // Texto demasiado largo, potencialmente peligroso
    }
    
    $dangerous_patterns = [
        '/(\bUNION\b.*\bSELECT\b)/i',
        '/(\bSELECT\b.*\bFROM\b)/i',
        '/(\bINSERT\b.*\bINTO\b)/i',
        '/(\bUPDATE\b.*\bSET\b)/i',
        '/(\bDELETE\b.*\bFROM\b)/i',
        '/(\bDROP\b.*\bTABLE\b)/i',
        '/(\bEXEC\b|\bEXECUTE\b)/i',
        '/(\bSCRIPT\b)/i',
        '/(\bJAVASCRIPT\b)/i',
        '/(\bON\w+\s*=\s*)/i',
    ];
    
    foreach ($dangerous_patterns as $pattern) {
        if (@preg_match($pattern, $text)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Valida que un texto no contenga XSS básico
 * Optimizado para evitar problemas con patrones regex
 */
function validate_no_xss($text) {
    if ($text === null || $text === '') {
        return true;
    }
    
    $text = (string)$text;
    
    // Limitar longitud para evitar problemas de rendimiento
    if (strlen($text) > 10000) {
        return false; // Texto demasiado largo, potencialmente peligroso
    }
    
    $xss_patterns = [
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi',
        '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi',
        '/on\w+\s*=\s*["\'][^"\']*["\']/i',
        '/javascript:/i',
        '/vbscript:/i',
        '/data:text\/html/i',
    ];
    
    foreach ($xss_patterns as $pattern) {
        if (@preg_match($pattern, $text)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Sanitiza un nombre - SIMPLIFICADO: solo limpia básicamente
 */
function sanitize_nombre($nombre, $max_length = 100) {
    if ($nombre === null) {
        return false;
    }
    
    $nombre = trim((string)$nombre);
    if (empty($nombre)) {
        return false;
    }
    
    // Limpiar tags HTML
    $nombre = strip_tags($nombre);
    
    // Validar longitud
    if ($max_length !== null && strlen($nombre) > $max_length) {
        return false;
    }
    
    // Trim final
    $nombre = trim($nombre);
    
    if (empty($nombre)) {
        return false;
    }
    
    return $nombre;
}

/**
 * Sanitiza un parámetro GET
 * Optimizado para evitar dobles llamadas a funciones de sanitización
 */
function sanitize_get($key, $default = null, $type = 'string') {
    if (!isset($_GET[$key])) {
        return $default;
    }
    
    $value = $_GET[$key];
    
    switch ($type) {
        case 'int':
            $result = sanitize_int($value);
            return $result !== false ? $result : $default;
        case 'float':
            $result = sanitize_float($value);
            return $result !== false ? $result : $default;
        case 'email':
            $result = sanitize_email($value);
            return $result !== false ? $result : $default;
        case 'url':
            $result = sanitize_url($value);
            return $result !== false ? $result : $default;
        case 'text':
            return sanitize_text($value);
        default:
            return sanitize_html($value);
    }
}

/**
 * Sanitiza un parámetro POST
 * Optimizado para evitar dobles llamadas a funciones de sanitización
 */
function sanitize_post($key, $default = null, $type = 'string') {
    if (!isset($_POST[$key])) {
        return $default;
    }
    
    $value = $_POST[$key];
    
    switch ($type) {
        case 'int':
            $result = sanitize_int($value);
            return $result !== false ? $result : $default;
        case 'float':
            $result = sanitize_float($value);
            return $result !== false ? $result : $default;
        case 'email':
            $result = sanitize_email($value);
            return $result !== false ? $result : $default;
        case 'url':
            $result = sanitize_url($value);
            return $result !== false ? $result : $default;
        case 'text':
            return sanitize_text($value);
        case 'text_html':
            return sanitize_text_html($value);
        default:
            return sanitize_html($value);
    }
}

/**
 * Valida y sanitiza un array de datos
 */
function sanitize_array($data, $rules) {
    $sanitized = [];
    $errors = [];
    
    foreach ($rules as $key => $rule) {
        $value = $data[$key] ?? null;
        $type = $rule['type'] ?? 'string';
        $required = $rule['required'] ?? false;
        $max_length = $rule['max_length'] ?? null;
        $min = $rule['min'] ?? null;
        $max = $rule['max'] ?? null;
        $default = $rule['default'] ?? null;
        
        if ($required && ($value === null || $value === '')) {
            $errors[$key] = "El campo $key es requerido";
            continue;
        }
        
        if ($value === null || $value === '') {
            $sanitized[$key] = $default;
            continue;
        }
        
        switch ($type) {
            case 'int':
                $sanitized[$key] = sanitize_int($value, $min, $max);
                if ($sanitized[$key] === false) {
                    $errors[$key] = "El campo $key debe ser un número entero válido";
                }
                break;
            case 'float':
                $sanitized[$key] = sanitize_float($value, $min, $max);
                if ($sanitized[$key] === false) {
                    $errors[$key] = "El campo $key debe ser un número válido";
                }
                break;
            case 'email':
                $sanitized[$key] = sanitize_email($value);
                if ($sanitized[$key] === false) {
                    $errors[$key] = "El campo $key debe ser un email válido";
                }
                break;
            case 'url':
                $sanitized[$key] = sanitize_url($value);
                if ($sanitized[$key] === false) {
                    $errors[$key] = "El campo $key debe ser una URL válida";
                }
                break;
            case 'nombre':
                $sanitized[$key] = sanitize_nombre($value, $max_length);
                if ($sanitized[$key] === false) {
                    $errors[$key] = "El campo $key contiene caracteres inválidos";
                }
                break;
            case 'text':
                $sanitized[$key] = sanitize_text($value, $max_length);
                break;
            case 'text_html':
                $sanitized[$key] = sanitize_text_html($value, $max_length);
                break;
            default:
                $sanitized[$key] = sanitize_html($value);
        }
    }
    
    return [
        'data' => $sanitized,
        'errors' => $errors
    ];
}

