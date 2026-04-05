@echo off
REM Script para configurar el log de errores de PHP
REM Windows Batch Script

echo ========================================
echo Configuracion de Log de Errores PHP
echo ========================================
echo.

REM Buscar Apache y su log
set APACHE_LOG=
set PHP_LOG=

REM XAMPP
if exist "C:\xampp\apache\logs\error.log" (
    set APACHE_LOG=C:\xampp\apache\logs\error.log
    echo [OK] Encontrado log de Apache XAMPP: %APACHE_LOG%
)

REM WAMP
if exist "C:\wamp64\logs\apache_error.log" (
    set APACHE_LOG=C:\wamp64\logs\apache_error.log
    echo [OK] Encontrado log de Apache WAMP: %APACHE_LOG%
)

REM Buscar php.ini
set PHP_INI=
if exist "C:\xampp\php\php.ini" (
    set PHP_INI=C:\xampp\php\php.ini
    echo [OK] Encontrado php.ini: %PHP_INI%
) else if exist "C:\wamp64\bin\php\php8.2.0\php.ini" (
    set PHP_INI=C:\wamp64\bin\php\php8.2.0\php.ini
    echo [OK] Encontrado php.ini: %PHP_INI%
) else if exist "C:\php\php.ini" (
    set PHP_INI=C:\php\php.ini
    echo [OK] Encontrado php.ini: %PHP_INI%
)

if "%PHP_INI%"=="" (
    echo [ERROR] No se encontro php.ini
    echo.
    echo Busca manualmente tu php.ini ejecutando:
    echo php --ini
    pause
    exit /b 1
)

echo.
echo ========================================
echo Configuracion actual en php.ini:
echo ========================================
findstr /C:"error_log" "%PHP_INI%"
findstr /C:"log_errors" "%PHP_INI%"
findstr /C:"display_errors" "%PHP_INI%"

echo.
echo ========================================
echo Logs disponibles:
echo ========================================
if not "%APACHE_LOG%"=="" (
    echo Apache Log: %APACHE_LOG%
    dir "%APACHE_LOG%" 2>nul
)

echo.
echo ========================================
echo Para usar el log de Apache, agrega esto
echo al inicio de tus scripts PHP:
echo ========================================
echo.
if not "%APACHE_LOG%"=="" (
    echo ini_set('error_log', '%APACHE_LOG%');
    echo ini_set('log_errors', '1');
    echo ini_set('display_errors', '0');
) else (
    echo No se encontro log de Apache.
    echo Usa el log por defecto del sistema.
)

echo.
pause

