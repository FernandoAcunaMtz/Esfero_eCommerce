#!/bin/bash
# Script rápido para reiniciar Ngrok (sin verificaciones extensas)
# Uso: ./quick_restart_ngrok.sh

echo "Reiniciando Ngrok rápidamente..."

# Detener Ngrok
pkill -f ngrok 2>/dev/null
sleep 1

# Iniciar Ngrok en background
nohup ngrok http localhost:443 > ~/Esfero/logs/ngrok.log 2>&1 &

# Esperar y mostrar URL
sleep 3
NGROK_URL=$(curl -s http://localhost:4040/api/tunnels 2>/dev/null | grep -o '"public_url":"[^"]*' | grep -o 'https://[^"]*' | head -1)

if [ -n "$NGROK_URL" ]; then
    echo "✅ Ngrok reiniciado: $NGROK_URL"
else
    echo "✅ Ngrok reiniciado (ver URL en http://localhost:4040)"
fi

