#!/bin/bash
# Script para configurar el log de errores de PHP
# Linux/WSL

echo "========================================"
echo "Configuración de Log de Errores PHP"
echo "========================================"
echo

# Buscar Apache y su log
APACHE_LOG=""
PHP_INI=""

# Rutas comunes de Apache en Linux
if [ -f "/var/log/apache2/error.log" ]; then
    APACHE_LOG="/var/log/apache2/error.log"
    echo "[OK] Encontrado log de Apache: $APACHE_LOG"
elif [ -f "/var/log/httpd/error_log" ]; then
    APACHE_LOG="/var/log/httpd/error_log"
    echo "[OK] Encontrado log de Apache: $APACHE_LOG"
fi

# Buscar php.ini
PHP_INI=$(php --ini | grep "Loaded Configuration File" | awk '{print $5}')

if [ -z "$PHP_INI" ]; then
    echo "[ERROR] No se encontró php.ini"
    echo
    echo "Busca manualmente ejecutando: php --ini"
    exit 1
fi

echo "[OK] Encontrado php.ini: $PHP_INI"
echo
echo "========================================"
echo "Configuración actual en php.ini:"
echo "========================================"
grep -E "error_log|log_errors|display_errors" "$PHP_INI" | grep -v "^;" | head -5

echo
echo "========================================"
echo "Logs disponibles:"
echo "========================================"
if [ -n "$APACHE_LOG" ]; then
    echo "Apache Log: $APACHE_LOG"
    ls -lh "$APACHE_LOG" 2>/dev/null || echo "  (no accesible sin sudo)"
fi

echo
echo "========================================"
echo "Para usar el log de Apache, agrega esto"
echo "al inicio de tus scripts PHP:"
echo "========================================"
echo
if [ -n "$APACHE_LOG" ]; then
    echo "ini_set('error_log', '$APACHE_LOG');"
    echo "ini_set('log_errors', '1');"
    echo "ini_set('display_errors', '0');"
else
    echo "No se encontró log de Apache."
    echo "Usa el log por defecto del sistema."
fi

echo

