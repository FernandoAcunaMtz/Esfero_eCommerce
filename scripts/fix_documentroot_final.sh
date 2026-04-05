#!/bin/bash
# Script para corregir definitivamente el DocumentRoot

echo "=== Corrigiendo DocumentRoot ==="

# 1. Verificar todos los VirtualHosts activos
echo "1. VirtualHosts activos:"
ls -la /etc/apache2/sites-enabled/

echo ""
echo "2. Verificar DocumentRoot en cada uno:"
for conf in /etc/apache2/sites-enabled/*.conf; do
    echo "--- $conf ---"
    grep -A 2 "DocumentRoot" "$conf" 2>/dev/null || echo "No tiene DocumentRoot"
done

echo ""
echo "3. Corregir DocumentRoot en todos los VirtualHosts de Esfero:"
sudo sed -i 's|DocumentRoot /var/www/html|DocumentRoot /home/fer/Esfero/frontend|g' /etc/apache2/sites-enabled/*.conf

echo ""
echo "4. Verificar cambios:"
grep "DocumentRoot" /etc/apache2/sites-enabled/*.conf

echo ""
echo "5. Verificar sintaxis:"
sudo apache2ctl configtest

echo ""
echo "6. Recargar Apache:"
sudo systemctl reload apache2

echo ""
echo "=== FIN ==="

