@echo off
REM Script para generar certificado SSL autofirmado para Esfero
REM Ejecutar como Administrador

echo ========================================
echo Generador de Certificado SSL Autofirmado
echo Esfero Marketplace
echo ========================================
echo.

REM Verificar que OpenSSL esté disponible
where openssl >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: OpenSSL no encontrado en el PATH
    echo.
    echo Por favor instala OpenSSL o agrega OpenSSL al PATH del sistema
    echo.
    pause
    exit /b 1
)

REM Crear directorio ssl si no existe
if not exist "ssl" mkdir ssl
cd ssl

echo Generando clave privada...
openssl genrsa -out esfero.key 2048
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: No se pudo generar la clave privada
    pause
    exit /b 1
)

echo.
echo Generando certificado autofirmado...
openssl req -new -x509 -key esfero.key -out esfero.crt -days 365 -subj "/CN=esfero.local/O=Esfero/C=MX"
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: No se pudo generar el certificado
    pause
    exit /b 1
)

echo.
echo ========================================
echo Certificado generado exitosamente!
echo ========================================
echo.
echo Archivos creados:
echo   - ssl\esfero.key (clave privada)
echo   - ssl\esfero.crt (certificado)
echo.
echo Siguiente paso: Configurar Apache con estos certificados
echo Ver: Documentación\CONFIGURACION_DOMINIO_SSL.md
echo.
pause

