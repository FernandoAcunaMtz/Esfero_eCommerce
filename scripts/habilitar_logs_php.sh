#!/bin/bash
# Script para habilitar logging de errores PHP

echo "=== Habilitando logging de errores PHP ==="

# Crear directorio de logs si no existe
mkdir -p /home/fer/Esfero/logs
chmod 755 /home/fer/Esfero/logs

# Crear archivo .htaccess para habilitar display_errors (solo para debugging)
cat > /home/fer/Esfero/frontend/.htaccess << 'EOF'
# Habilitar logging de errores PHP
php_flag log_errors on
php_value error_log /home/fer/Esfero/logs/php_errors.log
php_value error_reporting E_ALL
php_flag display_errors off
EOF

echo "✓ .htaccess creado en frontend/"
echo "✓ Los errores PHP se registrarán en /home/fer/Esfero/logs/php_errors.log"
echo ""
echo "Para ver los errores en tiempo real, ejecuta:"
echo "  tail -f /home/fer/Esfero/logs/php_errors.log"

