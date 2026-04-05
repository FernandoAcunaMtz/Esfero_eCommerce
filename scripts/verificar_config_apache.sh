#!/bin/bash
# Script para verificar configuración de Apache

echo "=== Verificando configuración de Apache ==="
echo ""

echo "1. DocumentRoot actual:"
grep -r "DocumentRoot" /etc/apache2/sites-enabled/ 2>/dev/null | head -5

echo ""
echo "2. VirtualHosts activos:"
ls -la /etc/apache2/sites-enabled/

echo ""
echo "3. Contenido del VirtualHost por defecto:"
cat /etc/apache2/sites-enabled/000-default.conf 2>/dev/null | grep -A 10 "DocumentRoot\|ServerName\|<Directory"

echo ""
echo "4. ¿Dónde está process_cart.php?"
find /home/fer/Esfero -name "process_cart.php" -type f 2>/dev/null

echo ""
echo "5. ¿Dónde está el DocumentRoot de Apache?"
apache2ctl -S 2>/dev/null | grep "DocumentRoot\|namevhost"

echo ""
echo "6. Verificar si hay un VirtualHost para Esfero:"
grep -r "Esfero\|esfero" /etc/apache2/sites-enabled/ 2>/dev/null

