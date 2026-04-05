#!/bin/bash
# Script para generar certificado SSL autofirmado para Esfero
# Ejecutar con sudo

echo "========================================"
echo "Generador de Certificado SSL Autofirmado"
echo "Esfero Marketplace - Ubuntu/Linux"
echo "========================================"
echo ""

# Verificar que OpenSSL esté disponible
if ! command -v openssl &> /dev/null; then
    echo "ERROR: OpenSSL no encontrado"
    echo ""
    echo "Instala OpenSSL con:"
    echo "  sudo apt-get update"
    echo "  sudo apt-get install openssl"
    echo ""
    exit 1
fi

# Obtener directorio del script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Crear directorio ssl si no existe
SSL_DIR="$PROJECT_DIR/ssl"
mkdir -p "$SSL_DIR"
cd "$SSL_DIR"

echo "Generando clave privada..."
openssl genrsa -out esfero.key 2048
if [ $? -ne 0 ]; then
    echo "ERROR: No se pudo generar la clave privada"
    exit 1
fi

echo ""
echo "Generando certificado autofirmado..."
openssl req -new -x509 -key esfero.key -out esfero.crt -days 365 \
    -subj "/CN=esfero.local/O=Esfero/C=MX"
if [ $? -ne 0 ]; then
    echo "ERROR: No se pudo generar el certificado"
    exit 1
fi

# Ajustar permisos
chmod 600 esfero.key
chmod 644 esfero.crt

echo ""
echo "========================================"
echo "Certificado generado exitosamente!"
echo "========================================"
echo ""
echo "Archivos creados:"
echo "  - $SSL_DIR/esfero.key (clave privada)"
echo "  - $SSL_DIR/esfero.crt (certificado)"
echo ""
echo "Siguiente paso: Configurar Apache con estos certificados"
echo "Ver: Documentación/CONFIGURACION_DOMINIO_SSL_LINUX.md"
echo ""

