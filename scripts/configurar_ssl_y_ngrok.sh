#!/bin/bash
# Script de configuración rápida para SSL y Ngrok en Ubuntu
# Ejecutar con sudo

set -e  # Salir si hay algún error

echo "========================================"
echo "Configuración SSL y Ngrok para Esfero"
echo "Servidor Ubuntu"
echo "========================================"
echo ""

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para imprimir mensajes
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${YELLOW}→${NC} $1"
}

# Obtener directorio del script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Verificar que estamos en el directorio correcto
if [ ! -f "$PROJECT_DIR/frontend/index.php" ]; then
    print_error "No se encontró el proyecto Esfero en: $PROJECT_DIR"
    exit 1
fi

print_info "Directorio del proyecto: $PROJECT_DIR"

# Paso 1: Verificar dependencias
echo ""
echo "=== Paso 1: Verificando dependencias ==="
echo ""

if ! command -v openssl &> /dev/null; then
    print_error "OpenSSL no encontrado. Instalando..."
    sudo apt-get update
    sudo apt-get install -y openssl
fi
print_success "OpenSSL instalado"

if ! command -v apache2 &> /dev/null; then
    print_error "Apache no encontrado. Por favor instálalo primero."
    exit 1
fi
print_success "Apache encontrado"

# Paso 2: Generar certificado SSL
echo ""
echo "=== Paso 2: Generando certificado SSL ==="
echo ""

SSL_DIR="$PROJECT_DIR/ssl"
mkdir -p "$SSL_DIR"

if [ ! -f "$SSL_DIR/esfero.crt" ] || [ ! -f "$SSL_DIR/esfero.key" ]; then
    print_info "Generando certificado SSL..."
    cd "$SSL_DIR"
    openssl genrsa -out esfero.key 2048
    openssl req -new -x509 -key esfero.key -out esfero.crt -days 365 \
        -subj "/CN=esfero.local/O=Esfero/C=MX"
    chmod 600 esfero.key
    chmod 644 esfero.crt
    print_success "Certificado SSL generado"
else
    print_info "Certificado SSL ya existe, omitiendo..."
fi

# Paso 3: Habilitar módulos de Apache
echo ""
echo "=== Paso 3: Habilitando módulos de Apache ==="
echo ""

MODULES=("ssl" "rewrite" "headers" "cgi")
for module in "${MODULES[@]}"; do
    if [ ! -L "/etc/apache2/mods-enabled/${module}.load" ]; then
        print_info "Habilitando módulo: $module"
        sudo a2enmod "$module" > /dev/null 2>&1
        print_success "Módulo $module habilitado"
    else
        print_info "Módulo $module ya está habilitado"
    fi
done

# Paso 4: Crear configuración de VirtualHost
echo ""
echo "=== Paso 4: Configurando VirtualHost ==="
echo ""

# Preguntar por la ruta del DocumentRoot
read -p "Ruta completa del DocumentRoot (ej: /var/www/html/esfero/frontend): " DOCUMENT_ROOT
if [ -z "$DOCUMENT_ROOT" ]; then
    DOCUMENT_ROOT="/var/www/html/esfero/frontend"
    print_info "Usando ruta por defecto: $DOCUMENT_ROOT"
fi

# Crear archivo de configuración
CONFIG_FILE="/etc/apache2/sites-available/esfero-ssl.conf"
print_info "Creando archivo de configuración: $CONFIG_FILE"

sudo tee "$CONFIG_FILE" > /dev/null <<EOF
<VirtualHost *:443>
    ServerName esfero.local
    ServerAlias www.esfero.local
    
    DocumentRoot $DOCUMENT_ROOT
    
    SSLEngine on
    SSLCertificateFile $PROJECT_DIR/ssl/esfero.crt
    SSLCertificateKeyFile $PROJECT_DIR/ssl/esfero.key
    
    <Directory $DOCUMENT_ROOT>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ScriptAlias /backend/cgi-bin/ $PROJECT_DIR/backend/cgi-bin/
    <Directory $PROJECT_DIR/backend/cgi-bin>
        Options +ExecCGI
        AddHandler cgi-script .py
        Require all granted
    </Directory>
    
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    
    ErrorLog \${APACHE_LOG_DIR}/esfero_ssl_error.log
    CustomLog \${APACHE_LOG_DIR}/esfero_ssl_access.log combined
</VirtualHost>

<VirtualHost *:80>
    ServerName esfero.local
    ServerAlias www.esfero.local
    Redirect permanent / https://esfero.local/
</VirtualHost>
EOF

print_success "Archivo de configuración creado"

# Habilitar el sitio
if [ ! -L "/etc/apache2/sites-enabled/esfero-ssl.conf" ]; then
    print_info "Habilitando sitio esfero-ssl"
    sudo a2ensite esfero-ssl.conf > /dev/null 2>&1
    print_success "Sitio habilitado"
else
    print_info "Sitio ya está habilitado"
fi

# Verificar configuración
echo ""
print_info "Verificando configuración de Apache..."
if sudo apache2ctl configtest > /dev/null 2>&1; then
    print_success "Configuración de Apache válida"
else
    print_error "Error en la configuración de Apache"
    sudo apache2ctl configtest
    exit 1
fi

# Reiniciar Apache
print_info "Reiniciando Apache..."
sudo systemctl restart apache2
print_success "Apache reiniciado"

# Paso 5: Verificar puertos
echo ""
echo "=== Paso 5: Verificando puertos ==="
echo ""

if sudo netstat -tlnp 2>/dev/null | grep -q ":443"; then
    print_success "Puerto 443 está escuchando"
else
    print_error "Puerto 443 no está escuchando"
fi

if sudo netstat -tlnp 2>/dev/null | grep -q ":80"; then
    print_success "Puerto 80 está escuchando"
else
    print_error "Puerto 80 no está escuchando"
fi

# Paso 6: Información sobre Ngrok
echo ""
echo "=== Paso 6: Información sobre Ngrok ==="
echo ""

print_info "Para instalar Ngrok, ejecuta:"
echo "  wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.tgz"
echo "  tar xvzf ngrok-v3-stable-linux-amd64.tgz"
echo "  sudo mv ngrok /usr/local/bin/"
echo ""
print_info "Para iniciar Ngrok, ejecuta:"
echo "  ./scripts/start_ngrok.sh"
echo "  O manualmente: ngrok http 443 --host-header=esfero.local"
echo ""

# Resumen final
echo ""
echo "========================================"
echo "Configuración completada!"
echo "========================================"
echo ""
print_success "Certificado SSL generado en: $PROJECT_DIR/ssl/"
print_success "Configuración de Apache creada en: $CONFIG_FILE"
print_success "Apache reiniciado y listo"
echo ""
print_info "Próximos pasos:"
echo "  1. Verifica que puedes acceder a: https://esfero.local"
echo "  2. Si accedes desde fuera del servidor, usa: https://$(hostname -I | awk '{print $1}')"
echo "  3. Instala y configura Ngrok si lo necesitas"
echo ""
print_info "Documentación completa:"
echo "  - Documentación/CONFIGURACION_DOMINIO_SSL_LINUX.md"
echo "  - Documentación/NGROK_IMPLEMENTACION_LINUX.md"
echo ""

