#!/bin/bash
# Script para diagnosticar dónde se están registrando los errores

echo "=== 1. Verificar configuración de PHP ==="
php -i | grep -E "error_log|log_errors|display_errors" | head -10

echo ""
echo ""
echo "=== 2. Ver todos los logs de Apache (incluyendo access.log) ==="
echo "Últimas 50 líneas de error.log:"
sudo tail -n 50 /var/log/apache2/error.log

echo ""
echo "Últimas 20 líneas de access.log (pueden tener errores):"
sudo tail -n 20 /var/log/apache2/access.log | grep -E "500|error|productos.php"

echo ""
echo ""
echo "=== 3. Buscar errores recientes (últimas 24 horas) ==="
sudo grep -i "error\|fatal\|warning\|parse" /var/log/apache2/error.log | tail -30

echo ""
echo ""
echo "=== 4. Verificar si hay logs en otros lugares ==="
echo "Buscando archivos .log en el proyecto:"
find /home/fer/Esfero -name "*.log" -type f 2>/dev/null

echo ""
echo ""
echo "=== 5. Probar acceso directo a productos.php ==="
echo "Ejecutando PHP directamente para ver errores:"
cd /home/fer/Esfero/frontend
php -l productos.php

echo ""
echo ""
echo "=== 6. Verificar permisos y existencia de archivos ==="
ls -la /home/fer/Esfero/frontend/productos.php
ls -la /home/fer/Esfero/frontend/includes/db_connection.php

