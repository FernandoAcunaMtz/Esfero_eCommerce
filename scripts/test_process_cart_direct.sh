#!/bin/bash
# Script para probar process_cart.php directamente sin pasar por Ngrok

echo "=== Test directo de process_cart.php ==="
echo ""

cd /home/fer/Esfero/frontend

# Simular petición POST
export REQUEST_METHOD=POST
export CONTENT_TYPE="application/x-www-form-urlencoded"

# Probar con curl directamente al servidor local
echo "1. Probando directamente al servidor local (sin Ngrok):"
curl -X POST "http://localhost/process_cart.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=add&producto_id=65&cantidad=1" \
  -v 2>&1 | head -30

echo ""
echo "2. Probando con PHP CLI directamente:"
php -r "
\$_POST['action'] = 'add';
\$_POST['producto_id'] = '65';
\$_POST['cantidad'] = '1';
include 'process_cart.php';
" 2>&1

echo ""
echo "=== Fin del test ==="

