#!/bin/bash
# Script para probar la API Python directamente y ver el error

echo "═══════════════════════════════════════════════════════════════════"
echo "  PROBAR API PYTHON DIRECTAMENTE"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

# Obtener token de la sesión actual (necesitarás ponerlo manualmente)
echo "⚠️  NOTA: Necesitas un token válido para probar."
echo "   Puedes obtenerlo desde la sesión activa del navegador"
echo ""
echo "Ejecuta este comando con tu token:"
echo ""
echo 'TOKEN="TU_TOKEN_AQUI"'
echo ""
echo 'curl -X POST "http://10.241.109.37/backend/cgi-bin/ordenes.py/create" \'
echo '  -H "Content-Type: application/json" \'
echo '  -H "Authorization: Bearer $TOKEN" \'
echo '  -H "X-Auth-Token: $TOKEN" \'
echo '  -d '"'"'{
    "direccion_envio": "Calle Test 123",
    "ciudad_envio": "Ciudad de México",
    "estado_envio": "CDMX",
    "codigo_postal_envio": "03020",
    "telefono_envio": "5555555555",
    "nombre_destinatario": "Test Usuario",
    "notas_comprador": ""
  }'"'"''
echo ""
echo ""
echo "O prueba directamente desde el navegador con:"
echo "  http://10.241.109.37/backend/cgi-bin/ordenes.py/create"
echo ""
echo "═══════════════════════════════════════════════════════════════════"
echo "  VER ERRORES ESPECÍFICOS DE PYTHON"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

# Buscar errores relacionados con Python
echo "Buscando errores de Python en los logs..."
sudo grep -i "python\|traceback\|exception\|error" /var/log/apache2/error.log | tail -n 20

echo ""
echo "═══════════════════════════════════════════════════════════════════"
echo "  VERIFICAR SCRIPT PYTHON"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

# Verificar que el script existe
if [ -f "/home/fer/Esfero/backend/cgi-bin/ordenes.py" ]; then
    echo "✅ Script existe: /home/fer/Esfero/backend/cgi-bin/ordenes.py"
    echo ""
    echo "Permisos:"
    ls -la /home/fer/Esfero/backend/cgi-bin/ordenes.py
    echo ""
    echo "Probando sintaxis Python..."
    python3 -m py_compile /home/fer/Esfero/backend/cgi-bin/ordenes.py 2>&1
    if [ $? -eq 0 ]; then
        echo "✅ Sintaxis correcta"
    else
        echo "❌ Error de sintaxis encontrado"
    fi
else
    echo "❌ Script NO existe en: /home/fer/Esfero/backend/cgi-bin/ordenes.py"
fi

