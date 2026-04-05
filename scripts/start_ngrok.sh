#!/bin/bash
# Script para iniciar Ngrok y exponer Esfero en servidor Ubuntu
# Asegúrate de tener Ngrok instalado y configurado

echo "========================================"
echo "Iniciando Ngrok para Esfero"
echo "Servidor Ubuntu"
echo "========================================"
echo ""

# Verificar que Ngrok esté disponible
if ! command -v ngrok &> /dev/null; then
    echo "ERROR: Ngrok no encontrado en el PATH"
    echo ""
    echo "Por favor instala Ngrok:"
    echo "  wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.tgz"
    echo "  tar xvzf ngrok-v3-stable-linux-amd64.tgz"
    echo "  sudo mv ngrok /usr/local/bin/"
    echo ""
    echo "O usa: sudo snap install ngrok"
    echo ""
    exit 1
fi

# Verificar que Apache esté corriendo
if ! systemctl is-active --quiet apache2; then
    echo "ADVERTENCIA: Apache no parece estar corriendo"
    echo "¿Deseas continuar de todas formas? (s/n)"
    read -r respuesta
    if [ "$respuesta" != "s" ] && [ "$respuesta" != "S" ]; then
        exit 1
    fi
fi

echo "Verificando configuración..."
echo ""
echo "IMPORTANTE:"
echo "- Asegúrate de que Apache esté corriendo"
echo "- Asegúrate de que tu aplicación esté accesible en https://esfero.local"
echo "- La URL pública de Ngrok se mostrará a continuación"
echo "- Presiona Ctrl+C para detener el túnel"
echo ""
echo "Presiona Enter para continuar..."
read -r

echo ""
echo "Iniciando túnel Ngrok..."
echo ""
echo "La URL pública se mostrará a continuación."
echo "También puedes verla en: http://localhost:4040"
echo ""
echo "Presiona Ctrl+C para detener el túnel."
echo ""

# Iniciar Ngrok apuntando al puerto 443 (HTTPS)
# Usar host-header para que Apache reconozca el dominio
ngrok http 443 --host-header=esfero.local

echo ""
echo "Túnel Ngrok detenido."
echo ""

