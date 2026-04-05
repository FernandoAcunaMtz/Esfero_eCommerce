#!/bin/bash
# Script para ejecutar el reemplazo de dependencias de sanitize.php

echo "========================================"
echo "Reemplazando dependencias de sanitize.php"
echo "========================================"
echo ""

# Verificar que PHP esté disponible
if ! command -v php &> /dev/null; then
    echo "ERROR: PHP no encontrado"
    echo "Por favor, instala PHP primero"
    exit 1
fi

# Cambiar al directorio del script
cd "$(dirname "$0")"

# Ejecutar el script PHP
php reemplazar_sanitize.php

echo ""
echo "========================================"
echo "Proceso finalizado"
echo "========================================"

