#!/bin/bash
# ============================================================================
# Script para verificar y establecer permisos del sistema para Esfero
# ============================================================================

echo "═══════════════════════════════════════════════════════════════════════════════"
echo "VERIFICACIÓN Y CONFIGURACIÓN DE PERMISOS - ESFERO"
echo "═══════════════════════════════════════════════════════════════════════════════"
echo ""

# Colores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PROJECT_DIR="/home/fer/Esfero"
ENV_FILE="$PROJECT_DIR/.env"

# Función para verificar si un comando existe
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Verificar que estamos en el directorio correcto
if [ ! -d "$PROJECT_DIR" ]; then
    echo -e "${RED}❌ Error: El directorio $PROJECT_DIR no existe${NC}"
    exit 1
fi

echo "📁 Directorio del proyecto: $PROJECT_DIR"
echo ""

# ============================================================================
# PASO 1: Verificar archivo .env
# ============================================================================
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "PASO 1: Verificando archivo .env"
echo "═══════════════════════════════════════════════════════════════════════════════"

if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}❌ Error: El archivo .env no existe en $ENV_FILE${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Archivo .env encontrado${NC}"
echo ""

# Mostrar configuración de base de datos
echo "Configuración de base de datos en .env:"
grep "^DB_" "$ENV_FILE" | while IFS= read -r line; do
    if [[ $line == *"PASSWORD"* ]]; then
        echo "  DB_PASSWORD=***OCULTO***"
    else
        echo "  $line"
    fi
done
echo ""

# ============================================================================
# PASO 2: Verificar permisos de archivos y directorios
# ============================================================================
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "PASO 2: Verificando permisos de archivos y directorios"
echo "═══════════════════════════════════════════════════════════════════════════════"

# Verificar permisos del .env
ENV_PERMS=$(stat -c "%a" "$ENV_FILE" 2>/dev/null)
if [ "$ENV_PERMS" != "644" ] && [ "$ENV_PERMS" != "640" ]; then
    echo -e "${YELLOW}⚠️  Permisos del .env: $ENV_PERMS (debería ser 644 o 640)${NC}"
    echo "   Corrigiendo permisos..."
    sudo chmod 644 "$ENV_FILE"
    echo -e "${GREEN}✅ Permisos corregidos${NC}"
else
    echo -e "${GREEN}✅ Permisos del .env: $ENV_PERMS${NC}"
fi

# Verificar y corregir permisos de directorios
DIRS=(
    "/home/fer"
    "/home/fer/Esfero"
    "/home/fer/Esfero/config"
    "/home/fer/Esfero/frontend"
    "/home/fer/Esfero/frontend/includes"
)

for dir in "${DIRS[@]}"; do
    if [ -d "$dir" ]; then
        DIR_PERMS=$(stat -c "%a" "$dir" 2>/dev/null)
        if [ "$DIR_PERMS" != "755" ]; then
            echo -e "${YELLOW}⚠️  Permisos de $dir: $DIR_PERMS (debería ser 755)${NC}"
            echo "   Corrigiendo permisos..."
            sudo chmod 755 "$dir"
            echo -e "${GREEN}✅ Permisos corregidos${NC}"
        else
            echo -e "${GREEN}✅ Permisos de $dir: $DIR_PERMS${NC}"
        fi
    else
        echo -e "${RED}❌ Directorio no existe: $dir${NC}"
    fi
done
echo ""

# ============================================================================
# PASO 3: Verificar propietario de archivos
# ============================================================================
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "PASO 3: Verificando propietario de archivos"
echo "═══════════════════════════════════════════════════════════════════════════════"

ENV_OWNER=$(stat -c "%U:%G" "$ENV_FILE" 2>/dev/null)
if [ "$ENV_OWNER" != "fer:www-data" ] && [ "$ENV_OWNER" != "fer:fer" ]; then
    echo -e "${YELLOW}⚠️  Propietario del .env: $ENV_OWNER (debería ser fer:www-data)${NC}"
    echo "   Corrigiendo propietario..."
    sudo chown fer:www-data "$ENV_FILE"
    echo -e "${GREEN}✅ Propietario corregido${NC}"
else
    echo -e "${GREEN}✅ Propietario del .env: $ENV_OWNER${NC}"
fi

# Corregir propietario del directorio frontend
if [ -d "$PROJECT_DIR/frontend" ]; then
    sudo chown -R fer:www-data "$PROJECT_DIR/frontend"
    echo -e "${GREEN}✅ Propietario de frontend corregido${NC}"
fi
echo ""

# ============================================================================
# PASO 4: Verificar que www-data está en el grupo fer
# ============================================================================
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "PASO 4: Verificando grupo de www-data"
echo "═══════════════════════════════════════════════════════════════════════════════"

if groups www-data | grep -q "\bfer\b"; then
    echo -e "${GREEN}✅ www-data ya está en el grupo fer${NC}"
else
    echo -e "${YELLOW}⚠️  www-data no está en el grupo fer${NC}"
    echo "   Agregando www-data al grupo fer..."
    sudo usermod -a -G fer www-data
    echo -e "${GREEN}✅ www-data agregado al grupo fer${NC}"
    echo -e "${YELLOW}⚠️  Nota: Puede ser necesario reiniciar Apache para que los cambios surtan efecto${NC}"
fi
echo ""

# ============================================================================
# PASO 5: Verificar que Apache puede leer el .env
# ============================================================================
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "PASO 5: Verificando acceso de Apache al .env"
echo "═══════════════════════════════════════════════════════════════════════════════"

if sudo -u www-data test -r "$ENV_FILE"; then
    echo -e "${GREEN}✅ Apache puede leer el archivo .env${NC}"
else
    echo -e "${RED}❌ Apache NO puede leer el archivo .env${NC}"
    echo "   Esto puede causar problemas. Verifica los permisos."
fi
echo ""

# ============================================================================
# PASO 6: Verificar estado de servicios
# ============================================================================
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "PASO 6: Verificando estado de servicios"
echo "═══════════════════════════════════════════════════════════════════════════════"

# Verificar Apache
if systemctl is-active --quiet apache2; then
    echo -e "${GREEN}✅ Apache está corriendo${NC}"
else
    echo -e "${RED}❌ Apache NO está corriendo${NC}"
    echo "   Ejecuta: sudo systemctl start apache2"
fi

# Verificar MySQL
if systemctl is-active --quiet mysql; then
    echo -e "${GREEN}✅ MySQL está corriendo${NC}"
else
    echo -e "${RED}❌ MySQL NO está corriendo${NC}"
    echo "   Ejecuta: sudo systemctl start mysql"
fi
echo ""

# ============================================================================
# RESUMEN
# ============================================================================
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "RESUMEN"
echo "═══════════════════════════════════════════════════════════════════════════════"
echo ""
echo "✅ Verificación de permisos completada"
echo ""
echo "PRÓXIMOS PASOS:"
echo "1. Ejecuta el script SQL para establecer permisos de MySQL:"
echo "   sudo mysql -u root < $PROJECT_DIR/sql/ESTABLECER_PERMISOS_MYSQL.sql"
echo ""
echo "2. Prueba la conexión desde la línea de comandos:"
echo "   mysql -u fer -p'Getomichico123!' -h localhost esfero -e 'SELECT 1;'"
echo ""
echo "3. Reinicia Apache:"
echo "   sudo systemctl restart apache2"
echo ""
echo "4. Abre en tu navegador:"
echo "   http://10.241.109.37/"
echo ""
echo "═══════════════════════════════════════════════════════════════════════════════"

