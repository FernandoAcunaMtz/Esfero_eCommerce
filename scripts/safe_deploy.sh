#!/bin/bash
# Script completo y seguro para deployment
# Combina permisos y verificaciones sin romper nada

echo "🚀 Iniciando deployment seguro..."
echo ""

# Directorio del proyecto
PROJECT_DIR="/home/fer/Esfero/frontend"

# Verificar que estamos en el directorio correcto
if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ Error: El directorio $PROJECT_DIR no existe"
    echo "   Asegúrate de estar en el servidor correcto"
    exit 1
fi

# Cambiar al directorio del proyecto
cd "$PROJECT_DIR" || exit 1

echo "📂 Directorio de trabajo: $(pwd)"
echo ""

# PASO 1: Establecer permisos
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "PASO 1: Estableciendo permisos"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Archivos PHP (644 = rw-r--r--)
find . -type f -name "*.php" -exec chmod 644 {} \;
echo "✅ Archivos .php: 644"

# Archivos JavaScript (644)
find . -type f -name "*.js" -exec chmod 644 {} \;
echo "✅ Archivos .js: 644"

# Archivos CSS (644)
find . -type f -name "*.css" -exec chmod 644 {} \;
echo "✅ Archivos .css: 644"

# Archivos HTML (644)
find . -type f -name "*.html" -exec chmod 644 {} \;
echo "✅ Archivos .html: 644"

# Directorios (755 = rwxr-xr-x)
find . -type d -exec chmod 755 {} \;
echo "✅ Directorios: 755"

# PASO 2: Verificar archivos críticos
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "PASO 2: Verificando archivos críticos"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Verificar que los archivos principales existan
CRITICAL_FILES=(
    "includes/db_connection.php"
    "includes/sanitize.php"
    "includes/api_helper.php"
    "includes/navbar.php"
    "includes/footer.php"
)

for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file existe"
    else
        echo "⚠️  ADVERTENCIA: $file no encontrado"
    fi
done

# PASO 3: Verificar sintaxis PHP (si php está disponible)
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "PASO 3: Verificando sintaxis PHP"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if command -v php &> /dev/null; then
    ERROR_COUNT=0
    while IFS= read -r -d '' file; do
        if ! php -l "$file" &> /dev/null; then
            echo "❌ Error de sintaxis en: $file"
            ERROR_COUNT=$((ERROR_COUNT + 1))
        fi
    done < <(find . -type f -name "*.php" -print0)
    
    if [ $ERROR_COUNT -eq 0 ]; then
        echo "✅ Todos los archivos PHP tienen sintaxis correcta"
    else
        echo "⚠️  Se encontraron $ERROR_COUNT archivos con errores de sintaxis"
    fi
else
    echo "⚠️  PHP no está disponible para verificar sintaxis"
fi

# PASO 4: Verificar permisos de Apache
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "PASO 4: Verificando permisos de Apache"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Verificar que Apache puede leer los archivos
if [ -d "/var/www" ]; then
    echo "📋 Si usas /var/www, asegúrate de que Apache tenga permisos de lectura"
fi

# Verificar usuario actual
CURRENT_USER=$(whoami)
echo "👤 Usuario actual: $CURRENT_USER"

# Verificar grupo
CURRENT_GROUP=$(id -gn)
echo "👥 Grupo actual: $CURRENT_GROUP"

# PASO 5: Resumen final
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ DEPLOYMENT COMPLETADO"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📋 Resumen de permisos:"
echo "   • Archivos: 644 (rw-r--r--)"
echo "   • Directorios: 755 (rwxr-xr-x)"
echo ""
echo "🔍 Próximos pasos recomendados:"
echo "   1. Verificar que Apache puede leer los archivos"
echo "   2. Probar acceso a la aplicación en el navegador"
echo "   3. Revisar logs de Apache si hay errores"
echo ""
echo "📝 Comandos útiles:"
echo "   • Ver logs de Apache: sudo tail -f /var/log/apache2/error.log"
echo "   • Reiniciar Apache: sudo systemctl restart apache2"
echo "   • Verificar estado Apache: sudo systemctl status apache2"
echo ""

