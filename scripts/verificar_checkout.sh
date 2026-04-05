#!/bin/bash

echo "=== VERIFICACIÓN DE ARCHIVOS DE CHECKOUT ==="
echo ""
echo "1. Archivos en /home/fer/Esfero/frontend/:"
echo "-------------------------------------------"
cd /home/fer/Esfero/frontend
ls -lh checkout*.php process_checkout*.php 2>/dev/null | awk '{print $9, "(" $5 ")"}'
echo ""

echo "2. Buscando referencias a checkout.php en el proyecto:"
echo "-----------------------------------------------------"
cd /home/fer/Esfero
grep -r "checkout\.php" --include="*.php" --include="*.js" --include="*.html" --exclude-dir=node_modules . 2>/dev/null | grep -v ".git" | grep -v "checkout_old.php" | head -20
echo ""

echo "3. Buscando referencias a process_checkout.php:"
echo "-----------------------------------------------"
grep -r "process_checkout\.php" --include="*.php" --include="*.js" --include="*.html" --exclude-dir=node_modules . 2>/dev/null | grep -v ".git" | grep -v "process_checkout_old.php" | head -20
echo ""

echo "4. Buscando referencias a checkout_nuevo.php (si quedaron):"
echo "----------------------------------------------------------"
grep -r "checkout_nuevo\.php" --include="*.php" --include="*.js" --include="*.html" --exclude-dir=node_modules . 2>/dev/null | grep -v ".git" | head -10
echo ""

echo "5. Verificando permisos:"
echo "------------------------"
cd /home/fer/Esfero/frontend
ls -l checkout.php process_checkout.php 2>/dev/null
echo ""

echo "=== VERIFICACIÓN COMPLETA ==="

