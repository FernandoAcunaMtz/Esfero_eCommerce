#!/bin/bash
# Script para configurar HTTPS con Ngrok

echo "=== Configurando HTTPS para Ngrok ==="
echo ""

# 1. Verificar configuración actual de HTTPS
echo "1. Verificando VirtualHost de HTTPS:"
grep -A 10 "DocumentRoot\|ServerName" /etc/apache2/sites-enabled/esfero-ssl.conf

echo ""
echo "2. Verificando que el puerto 443 esté escuchando:"
sudo netstat -tulpn | grep :443

echo ""
echo "3. Verificando orden de VirtualHosts:"
apache2ctl -S | grep ":443"

echo ""
echo "4. Verificando certificados SSL:"
ls -la /home/fer/Esfero/ssl/ 2>/dev/null || echo "Directorio SSL no encontrado"

echo ""
echo "=== Pasos para configurar ==="
echo ""
echo "1. Editar esfero-ssl.conf:"
echo "   sudo nano /etc/apache2/sites-enabled/esfero-ssl.conf"
echo ""
echo "2. Asegurar que tenga:"
echo "   DocumentRoot /home/fer/Esfero/frontend"
echo "   ServerAlias localhost"
echo ""
echo "3. Recargar Apache:"
echo "   sudo systemctl reload apache2"
echo ""
echo "4. Reiniciar Ngrok apuntando a puerto 443:"
echo "   pkill ngrok"
echo "   ngrok http localhost:443"

