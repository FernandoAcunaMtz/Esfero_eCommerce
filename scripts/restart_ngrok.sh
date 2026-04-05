#!/bin/bash
# Script para reiniciar Ngrok después de cambios en Apache
# Uso: ./restart_ngrok.sh

echo "========================================"
echo "Reiniciando Ngrok para Esfero"
echo "========================================"
echo ""

# 1. Detener Ngrok si está corriendo
echo "1. Deteniendo procesos de Ngrok existentes..."
pkill -f ngrok 2>/dev/null
sleep 2

# Verificar que se detuvo
if pgrep -f ngrok > /dev/null; then
    echo "   ⚠️  Advertencia: Algunos procesos de Ngrok aún están corriendo"
    echo "   Forzando cierre..."
    pkill -9 -f ngrok 2>/dev/null
    sleep 1
else
    echo "   ✅ Ngrok detenido correctamente"
fi

# 2. Verificar que Apache esté corriendo
echo ""
echo "2. Verificando estado de Apache..."
if systemctl is-active --quiet apache2; then
    echo "   ✅ Apache está corriendo"
else
    echo "   ❌ ERROR: Apache no está corriendo"
    echo "   Iniciando Apache..."
    sudo systemctl start apache2
    sleep 2
    if systemctl is-active --quiet apache2; then
        echo "   ✅ Apache iniciado correctamente"
    else
        echo "   ❌ ERROR: No se pudo iniciar Apache"
        exit 1
    fi
fi

# 3. Verificar que el puerto 443 esté escuchando
echo ""
echo "3. Verificando puerto 443..."
if sudo netstat -tulpn 2>/dev/null | grep -q ":443 " || sudo ss -tulpn 2>/dev/null | grep -q ":443 "; then
    echo "   ✅ Puerto 443 está escuchando"
else
    echo "   ⚠️  Advertencia: Puerto 443 no parece estar escuchando"
    echo "   Verificando módulo SSL..."
    sudo a2enmod ssl 2>/dev/null
    echo "   Recargando Apache..."
    sudo systemctl reload apache2
    sleep 2
fi

# 4. Verificar configuración de Apache
echo ""
echo "4. Verificando configuración de Apache..."
if sudo apache2ctl configtest 2>&1 | grep -q "Syntax OK"; then
    echo "   ✅ Configuración de Apache es válida"
else
    echo "   ⚠️  Advertencia: Hay problemas en la configuración de Apache"
    sudo apache2ctl configtest
fi

# 5. Iniciar Ngrok en background
echo ""
echo "5. Iniciando Ngrok..."
echo "   Comando: ngrok http localhost:443"
echo ""

# Crear directorio para logs si no existe
mkdir -p ~/Esfero/logs 2>/dev/null

# Iniciar Ngrok en background y guardar PID
nohup ngrok http localhost:443 > ~/Esfero/logs/ngrok.log 2>&1 &
NGROK_PID=$!

# Esperar un momento para que Ngrok se inicie
sleep 3

# Verificar que Ngrok esté corriendo
if ps -p $NGROK_PID > /dev/null 2>&1; then
    echo "   ✅ Ngrok iniciado correctamente (PID: $NGROK_PID)"
else
    echo "   ❌ ERROR: Ngrok no se pudo iniciar"
    echo "   Revisa los logs: tail -20 ~/Esfero/logs/ngrok.log"
    exit 1
fi

# 6. Obtener URL pública de Ngrok
echo ""
echo "6. Obteniendo URL pública de Ngrok..."
sleep 2

# Intentar obtener la URL desde la API local de Ngrok
NGROK_URL=$(curl -s http://localhost:4040/api/tunnels 2>/dev/null | grep -o '"public_url":"[^"]*' | grep -o 'https://[^"]*' | head -1)

if [ -n "$NGROK_URL" ]; then
    echo ""
    echo "========================================"
    echo "✅ Ngrok reiniciado exitosamente"
    echo "========================================"
    echo ""
    echo "🌐 URL pública: $NGROK_URL"
    echo ""
    echo "📊 Panel de control: http://localhost:4040"
    echo ""
    echo "📝 Logs: ~/Esfero/logs/ngrok.log"
    echo ""
    echo "🛑 Para detener: pkill ngrok"
    echo ""
else
    echo "   ⚠️  No se pudo obtener la URL automáticamente"
    echo "   Abre http://localhost:4040 en tu navegador para ver la URL"
    echo ""
    echo "   O ejecuta: curl -s http://localhost:4040/api/tunnels | grep -o 'https://[^"]*'"
    echo ""
fi

# Guardar PID en un archivo para referencia futura
echo $NGROK_PID > ~/Esfero/logs/ngrok.pid
echo "   PID guardado en: ~/Esfero/logs/ngrok.pid"

echo ""
echo "========================================"

