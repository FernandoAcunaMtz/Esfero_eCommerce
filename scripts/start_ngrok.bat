@echo off
REM Script para iniciar Ngrok y exponer Esfero localmente
REM Asegúrate de tener Ngrok instalado y configurado

echo ========================================
echo Iniciando Ngrok para Esfero
echo ========================================
echo.

REM Verificar que Ngrok esté disponible
where ngrok >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Ngrok no encontrado en el PATH
    echo.
    echo Por favor:
    echo 1. Descarga Ngrok desde https://ngrok.com/download
    echo 2. Extrae ngrok.exe a una carpeta
    echo 3. Agrega la carpeta al PATH del sistema
    echo.
    echo O ejecuta este script desde la carpeta donde está ngrok.exe
    echo.
    pause
    exit /b 1
)

echo Verificando que la aplicación esté corriendo...
echo.
echo IMPORTANTE:
echo - Asegúrate de que Apache esté corriendo
echo - Asegúrate de que tu aplicación esté accesible en https://esfero.local
echo - La URL pública de Ngrok se mostrará a continuación
echo.
echo Presiona cualquier tecla para continuar...
pause >nul

echo.
echo Iniciando túnel Ngrok...
echo.
echo La URL pública se mostrará a continuación.
echo Presiona Ctrl+C para detener el túnel.
echo.

REM Iniciar Ngrok apuntando al puerto 443 (HTTPS)
REM Si tu aplicación corre en HTTP (puerto 80), cambia 443 por 80
ngrok http 443 --host-header=esfero.local

echo.
echo Túnel Ngrok detenido.
pause

