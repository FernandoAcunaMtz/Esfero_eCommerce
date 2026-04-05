#!/bin/bash
# Script para crear el directorio de logs en el servidor remoto

LOG_DIR="/home/fer/Esfero/logs"
LOG_FILE="$LOG_DIR/php_errors.log"

echo "═══════════════════════════════════════════════════════════════════"
echo "  CREAR DIRECTORIO DE LOGS"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

# Crear directorio si no existe
if [ ! -d "$LOG_DIR" ]; then
    echo "📁 Creando directorio de logs..."
    mkdir -p "$LOG_DIR"
    echo "✅ Directorio creado: $LOG_DIR"
else
    echo "✅ El directorio ya existe: $LOG_DIR"
fi

# Crear archivo de log si no existe
if [ ! -f "$LOG_FILE" ]; then
    echo "📄 Creando archivo de log..."
    touch "$LOG_FILE"
    echo "✅ Archivo creado: $LOG_FILE"
else
    echo "✅ El archivo ya existe: $LOG_FILE"
fi

# Establecer permisos
echo "🔐 Estableciendo permisos..."
chmod 755 "$LOG_DIR"
chmod 666 "$LOG_FILE"

# Verificar permisos
echo ""
echo "═══════════════════════════════════════════════════════════════════"
echo "  VERIFICACIÓN"
echo "═══════════════════════════════════════════════════════════════════"
ls -la "$LOG_DIR"
echo ""
echo "✅ Directorio y archivo de logs listos!"
echo ""
echo "📁 Ubicación: $LOG_FILE"

