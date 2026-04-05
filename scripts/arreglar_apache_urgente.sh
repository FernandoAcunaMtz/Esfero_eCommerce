#!/bin/bash
# Script urgente para arreglar Apache

echo "=== ARREGLANDO APACHE ==="

# 1. Deshabilitar completamente el VirtualHost por defecto
sudo a2dissite 000-default.conf 2>/dev/null
sudo a2dissite default-ssl.conf 2>/dev/null

# 2. Verificar que 000-esfero-ssl.conf existe y tiene el DocumentRoot correcto
if [ -f /etc/apache2/sites-enabled/000-esfero-ssl.conf ]; then
    echo "✓ 000-esfero-ssl.conf existe"
    grep -q "DocumentRoot /home/fer/Esfero/frontend" /etc/apache2/sites-enabled/000-esfero-ssl.conf && echo "✓ DocumentRoot correcto" || echo "✗ DocumentRoot incorrecto"
else
    echo "✗ 000-esfero-ssl.conf NO existe"
    # Buscar esfero-ssl.conf
    if [ -f /etc/apache2/sites-enabled/esfero-ssl.conf ]; then
        sudo mv /etc/apache2/sites-enabled/esfero-ssl.conf /etc/apache2/sites-enabled/000-esfero-ssl.conf
    fi
fi

# 3. Asegurar que esfero.conf (HTTP) también esté bien
if [ -f /etc/apache2/sites-enabled/esfero.conf ]; then
    grep -q "DocumentRoot /home/fer/Esfero/frontend" /etc/apache2/sites-enabled/esfero.conf && echo "✓ esfero.conf DocumentRoot correcto" || echo "✗ esfero.conf DocumentRoot incorrecto"
fi

# 4. Verificar sintaxis
echo ""
echo "Verificando sintaxis..."
sudo apache2ctl configtest

# 5. Reiniciar Apache completamente
echo ""
echo "Reiniciando Apache..."
sudo systemctl restart apache2

# 6. Verificar qué VirtualHosts están activos
echo ""
echo "VirtualHosts activos:"
apache2ctl -S | grep -E "default server|:443|:80" | head -10

echo ""
echo "=== FIN ==="

