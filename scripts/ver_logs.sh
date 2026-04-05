#!/bin/bash
# Script para ver logs de errores desde /home/fer/Esfero

echo "=== LOGS DE ERROR DE APACHE (últimas 50 líneas) ==="
echo ""
sudo tail -n 50 /var/log/apache2/error.log

echo ""
echo ""
echo "=== LOGS DE PHP (si están configurados) ==="
echo ""
# Verificar si hay logs de PHP en el directorio del proyecto
if [ -f "/home/fer/Esfero/logs/php_errors.log" ]; then
    tail -n 50 /home/fer/Esfero/logs/php_errors.log
else
    echo "No se encontró log de PHP en /home/fer/Esfero/logs/php_errors.log"
fi

echo ""
echo ""
echo "=== ÚLTIMOS ERRORES DE APACHE (en tiempo real) ==="
echo "Presiona Ctrl+C para salir"
echo ""
sudo tail -f /var/log/apache2/error.log

