#!/bin/bash
# Script para ver logs desde SSH en el servidor remoto
# Ejecutar desde: /home/fer/Esfero

echo "=========================================="
echo "Revisando Logs - Esfero"
echo "=========================================="
echo

# Log de Apache (Ubuntu)
APACHE_LOG="/var/log/apache2/error.log"

# Log local del proyecto (si existe)
PROJECT_LOG="/home/fer/Esfero/frontend/php_error.log"

echo "1. Verificando log de Apache..."
if [ -f "$APACHE_LOG" ]; then
    if [ -r "$APACHE_LOG" ]; then
        echo "✓ Log de Apache encontrado: $APACHE_LOG"
        echo "  Tamaño: $(du -h "$APACHE_LOG" | cut -f1)"
        echo
        echo "Últimas 50 líneas del log de Apache:"
        echo "----------------------------------------"
        sudo tail -n 50 "$APACHE_LOG" 2>/dev/null || tail -n 50 "$APACHE_LOG" 2>/dev/null
    else
        echo "✗ Log de Apache existe pero no es legible (necesitas sudo)"
        echo "  Ejecuta: sudo tail -n 50 $APACHE_LOG"
    fi
else
    echo "✗ Log de Apache no encontrado en $APACHE_LOG"
fi

echo
echo "2. Verificando log local del proyecto..."
if [ -f "$PROJECT_LOG" ]; then
    echo "✓ Log local encontrado: $PROJECT_LOG"
    echo "  Tamaño: $(du -h "$PROJECT_LOG" | cut -f1)"
    echo
    echo "Últimas 50 líneas del log local:"
    echo "----------------------------------------"
    tail -n 50 "$PROJECT_LOG"
else
    echo "✗ Log local no existe: $PROJECT_LOG"
fi

echo
echo "=========================================="
echo "Buscando errores relacionados con checkout..."
echo "=========================================="
if [ -f "$APACHE_LOG" ] && [ -r "$APACHE_LOG" ]; then
    echo "Errores de checkout/JSON en Apache log:"
    grep -i "checkout\|json\|process_checkout" "$APACHE_LOG" 2>/dev/null | tail -n 20 || \
    sudo grep -i "checkout\|json\|process_checkout" "$APACHE_LOG" 2>/dev/null | tail -n 20
fi

echo
echo "=========================================="
echo "Comandos útiles:"
echo "=========================================="
echo "# Ver log de Apache en tiempo real:"
echo "sudo tail -f $APACHE_LOG"
echo
echo "# Ver últimas 100 líneas:"
echo "sudo tail -n 100 $APACHE_LOG"
echo
echo "# Buscar errores de checkout:"
echo "sudo grep -i checkout $APACHE_LOG | tail -n 50"
echo
echo "# Ver errores de PHP:"
echo "sudo grep -i php $APACHE_LOG | tail -n 50"

