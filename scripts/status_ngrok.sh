#!/bin/bash
# Script para verificar el estado de Ngrok
# Uso: ./status_ngrok.sh

echo "========================================"
echo "Estado de Ngrok y Apache"
echo "========================================"
echo ""

# 1. Verificar si Ngrok está corriendo
echo "1. Estado de Ngrok:"
if pgrep -f ngrok > /dev/null; then
    NGROK_PID=$(pgrep -f ngrok | head -1)
    echo "   ✅ Ngrok está corriendo (PID: $NGROK_PID)"
    
    # Obtener URL pública
    echo ""
    echo "   Obteniendo URL pública..."
    NGROK_URL=$(curl -s http://localhost:4040/api/tunnels 2>/dev/null | grep -o '"public_url":"[^"]*' | grep -o 'https://[^"]*' | head -1)
    
    if [ -n "$NGROK_URL" ]; then
        echo "   🌐 URL pública: $NGROK_URL"
    else
        echo "   ⚠️  No se pudo obtener la URL automáticamente"
        echo "   Abre http://localhost:4040 para ver la URL"
    fi
    
    echo ""
    echo "   Panel de control: http://localhost:4040"
else
    echo "   ❌ Ngrok NO está corriendo"
    echo ""
    echo "   Para iniciarlo, ejecuta:"
    echo "   ./scripts/restart_ngrok.sh"
fi

# 2. Verificar Apache
echo ""
echo "2. Estado de Apache:"
if systemctl is-active --quiet apache2; then
    echo "   ✅ Apache está corriendo"
else
    echo "   ❌ Apache NO está corriendo"
    echo ""
    echo "   Para iniciarlo, ejecuta:"
    echo "   sudo systemctl start apache2"
fi

# 3. Verificar puerto 443
echo ""
echo "3. Puerto 443:"
if sudo netstat -tulpn 2>/dev/null | grep -q ":443 " || sudo ss -tulpn 2>/dev/null | grep -q ":443 "; then
    echo "   ✅ Puerto 443 está escuchando"
    sudo netstat -tulpn 2>/dev/null | grep ":443 " || sudo ss -tulpn 2>/dev/null | grep ":443 "
else
    echo "   ❌ Puerto 443 NO está escuchando"
    echo ""
    echo "   Verifica que el módulo SSL esté habilitado:"
    echo "   sudo a2enmod ssl"
    echo "   sudo systemctl restart apache2"
fi

# 4. Verificar DocumentRoot
echo ""
echo "4. DocumentRoot configurado:"
DOCROOT=$(grep -h "DocumentRoot" /etc/apache2/sites-enabled/*.conf 2>/dev/null | head -1 | awk '{print $2}')
if [ -n "$DOCROOT" ]; then
    echo "   DocumentRoot: $DOCROOT"
    if [ -d "$DOCROOT" ]; then
        echo "   ✅ Directorio existe"
    else
        echo "   ❌ Directorio NO existe"
    fi
else
    echo "   ⚠️  No se pudo determinar el DocumentRoot"
fi

# 5. Últimos logs de Ngrok
echo ""
echo "5. Últimas líneas del log de Ngrok:"
if [ -f ~/Esfero/logs/ngrok.log ]; then
    echo ""
    tail -5 ~/Esfero/logs/ngrok.log
else
    echo "   No hay archivo de log"
fi

echo ""
echo "========================================"

