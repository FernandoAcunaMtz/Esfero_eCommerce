#!/bin/bash
# Script para establecer permisos correctos en archivos PHP
# Evita rupturas y asegura que los archivos sean accesibles

echo "🔧 Estableciendo permisos correctos para archivos PHP..."

# Directorio base del proyecto
PROJECT_DIR="/home/fer/Esfero/frontend"

# Verificar que el directorio existe
if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ Error: El directorio $PROJECT_DIR no existe"
    exit 1
fi

# Permisos para archivos PHP (644 = rw-r--r--)
# Permite lectura/escritura al propietario, solo lectura a grupo y otros
find "$PROJECT_DIR" -type f -name "*.php" -exec chmod 644 {} \;
echo "✅ Permisos establecidos para archivos .php (644)"

# Permisos para archivos JavaScript (644)
find "$PROJECT_DIR" -type f -name "*.js" -exec chmod 644 {} \;
echo "✅ Permisos establecidos para archivos .js (644)"

# Permisos para archivos CSS (644)
find "$PROJECT_DIR" -type f -name "*.css" -exec chmod 644 {} \;
echo "✅ Permisos establecidos para archivos .css (644)"

# Permisos para directorios (755 = rwxr-xr-x)
# Permite todo al propietario, lectura/ejecución a grupo y otros
find "$PROJECT_DIR" -type d -exec chmod 755 {} \;
echo "✅ Permisos establecidos para directorios (755)"

# Archivos específicos que necesitan permisos especiales (si existen)
# Scripts ejecutables
if [ -f "$PROJECT_DIR/../scripts/start_ngrok.sh" ]; then
    chmod 755 "$PROJECT_DIR/../scripts/start_ngrok.sh"
    echo "✅ Permisos establecidos para start_ngrok.sh (755)"
fi

# Asegurar que el propietario sea correcto (ajustar según tu usuario)
# chown -R fer:fer "$PROJECT_DIR"  # Descomentar si necesitas cambiar propietario

echo ""
echo "✨ Permisos establecidos correctamente"
echo "📋 Resumen:"
echo "   - Archivos PHP/JS/CSS: 644 (rw-r--r--)"
echo "   - Directorios: 755 (rwxr-xr-x)"
echo "   - Scripts ejecutables: 755 (rwxr-xr-x)"

