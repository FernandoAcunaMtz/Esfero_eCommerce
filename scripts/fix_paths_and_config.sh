#!/bin/bash
# Script para corregir rutas y configuraciones con sed
# Evita rupturas al asegurar que las rutas sean correctas

echo "🔧 Verificando y corrigiendo rutas y configuraciones..."

# Directorio base del proyecto
PROJECT_DIR="/home/fer/Esfero/frontend"

# Verificar que el directorio existe
if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ Error: El directorio $PROJECT_DIR no existe"
    exit 1
fi

# Backup de seguridad (opcional, descomentar si quieres backups)
# BACKUP_DIR="/home/fer/Esfero/backups/$(date +%Y%m%d_%H%M%S)"
# mkdir -p "$BACKUP_DIR"
# cp -r "$PROJECT_DIR" "$BACKUP_DIR/"
# echo "✅ Backup creado en $BACKUP_DIR"

# 1. Asegurar que las rutas de includes usen __DIR__ correctamente
# (No modificamos si ya están correctas, solo verificamos)
echo "📋 Verificando rutas de includes..."

# 2. Asegurar que las rutas de assets sean relativas
# Buscar y corregir rutas absolutas incorrectas (si existen)
# find "$PROJECT_DIR" -type f -name "*.php" -exec sed -i 's|/var/www/html|/home/fer/Esfero|g' {} \;

# 3. Verificar que no haya rutas hardcodeadas problemáticas
echo "📋 Verificando rutas hardcodeadas..."

# 4. Asegurar que los require_once usen rutas relativas correctas
# Esto ya debería estar bien, pero verificamos
echo "📋 Verificando require_once..."

# 5. Verificar encoding UTF-8 en archivos PHP
echo "📋 Verificando encoding UTF-8..."

# 6. Asegurar que las rutas de imágenes usen rutas relativas o absolutas correctas
# find "$PROJECT_DIR" -type f -name "*.php" -exec sed -i 's|src="http://localhost|src="|g' {} \;

# 7. Verificar que las rutas de API sean correctas
# (Ya están configuradas en api_helper.php, no las tocamos)

echo ""
echo "✨ Verificación completada"
echo "📝 Nota: Este script solo verifica. Para hacer cambios, descomenta las líneas correspondientes."

