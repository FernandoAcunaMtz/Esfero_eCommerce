#!/bin/bash
# Script para verificar y corregir configuración de Apache para Ngrok

echo "=== Verificando configuración de Apache ==="
echo ""

echo "1. Ver VirtualHost de HTTPS (esfero-ssl.conf):"
sudo grep -A 10 "DocumentRoot\|ServerName" /etc/apache2/sites-enabled/esfero-ssl.conf

echo ""
echo "2. Ver qué puerto está usando Ngrok:"
ps aux | grep ngrok | grep -v grep

echo ""
echo "3. Verificar orden de VirtualHosts:"
apache2ctl -S | head -20

