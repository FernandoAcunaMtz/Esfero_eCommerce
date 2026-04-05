#!/bin/bash
# Script para ver los logs de PHP en el servidor remoto

LOG_FILE="/home/fer/Esfero/logs/php_errors.log"

echo "═══════════════════════════════════════════════════════════════════"
echo "  VER LOGS DE PHP - Esfero Marketplace"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

# Verificar si el archivo existe
if [ ! -f "$LOG_FILE" ]; then
    echo "⚠️  El archivo de log no existe aún:"
    echo "   $LOG_FILE"
    echo ""
    echo "Creando el directorio y archivo si no existen..."
    mkdir -p /home/fer/Esfero/logs
    touch "$LOG_FILE"
    chmod 666 "$LOG_FILE"
    echo "✅ Directorio y archivo creados."
    echo ""
fi

echo "📁 Ubicación del log:"
echo "   $LOG_FILE"
echo ""

# Mostrar tamaño del archivo
if [ -f "$LOG_FILE" ]; then
    FILE_SIZE=$(du -h "$LOG_FILE" | cut -f1)
    echo "📊 Tamaño del archivo: $FILE_SIZE"
    echo ""
    
    # Mostrar últimas 50 líneas
    echo "═══════════════════════════════════════════════════════════════════"
    echo "  ÚLTIMAS 50 LÍNEAS DEL LOG"
    echo "═══════════════════════════════════════════════════════════════════"
    echo ""
    tail -n 50 "$LOG_FILE"
    echo ""
    echo ""
    echo "💡 Para ver el log en tiempo real, usa:"
    echo "   tail -f $LOG_FILE"
    echo ""
    echo "💡 Para ver todo el contenido:"
    echo "   cat $LOG_FILE"
    echo ""
    echo "💡 Para buscar errores específicos:"
    echo "   grep -i error $LOG_FILE"
fi

