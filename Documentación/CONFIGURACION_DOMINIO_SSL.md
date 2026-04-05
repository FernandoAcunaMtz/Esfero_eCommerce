# Configuración de Dominio Local y SSL Autofirmado

## Objetivo
Configurar un dominio local (ej: `esfero.local`) con certificado SSL autofirmado para que la aplicación se muestre con un nombre más amigable en lugar de la IP del servidor.

## Requisitos Previos
- Apache instalado y funcionando
- OpenSSL instalado (generalmente viene con Windows)
- Permisos de administrador

## Paso 1: Configurar Dominio Local en Windows

1. Abre el Bloc de notas como **Administrador**
2. Abre el archivo: `C:\Windows\System32\drivers\etc\hosts`
3. Agrega la siguiente línea al final:
   ```
   127.0.0.1    esfero.local
   ```
4. Guarda el archivo

**Nota:** Si no puedes guardar, asegúrate de ejecutar el Bloc de notas como Administrador.

## Paso 2: Generar Certificado SSL Autofirmado

### Opción A: Usando OpenSSL (Recomendado)

1. Abre PowerShell o CMD como Administrador
2. Navega a la carpeta del proyecto:
   ```powershell
   cd "C:\Users\Fernando Acuña\OneDrive\Escritorio\Esfero"
   ```
3. Crea un directorio para los certificados:
   ```powershell
   mkdir ssl
   cd ssl
   ```
4. Genera la clave privada:
   ```powershell
   openssl genrsa -out esfero.key 2048
   ```
5. Genera el certificado autofirmado:
   ```powershell
   openssl req -new -x509 -key esfero.key -out esfero.crt -days 365 -subj "/CN=esfero.local"
   ```

### Opción B: Usando el Script Automático

Ejecuta el script `scripts/generar_certificado_ssl.bat` (se creará en el siguiente paso).

## Paso 3: Configurar Apache para SSL

### 3.1 Habilitar Módulo SSL

Abre PowerShell como Administrador y ejecuta:

```powershell
# Si usas XAMPP, el módulo SSL ya está habilitado
# Si usas Apache standalone, ejecuta:
# (Ajusta la ruta según tu instalación)
```

### 3.2 Configurar VirtualHost

Edita el archivo de configuración de Apache (ubicación depende de tu instalación):

**Para XAMPP:** `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
**Para Apache standalone:** `C:\Apache24\conf\extra\httpd-vhosts.conf`

Agrega la siguiente configuración:

```apache
<VirtualHost *:443>
    ServerName esfero.local
    DocumentRoot "C:/Users/Fernando Acuña/OneDrive/Escritorio/Esfero/frontend"
    
    SSLEngine on
    SSLCertificateFile "C:/Users/Fernando Acuña/OneDrive/Escritorio/Esfero/ssl/esfero.crt"
    SSLCertificateKeyFile "C:/Users/Fernando Acuña/OneDrive/Escritorio/Esfero/ssl/esfero.key"
    
    <Directory "C:/Users/Fernando Acuña/OneDrive/Escritorio/Esfero/frontend">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Python CGI
    ScriptAlias /backend/cgi-bin/ "C:/Users/Fernando Acuña/OneDrive/Escritorio/Esfero/backend/cgi-bin/"
    <Directory "C:/Users/Fernando Acuña/OneDrive/Escritorio/Esfero/backend/cgi-bin">
        Options +ExecCGI
        AddHandler cgi-script .py
        Require all granted
    </Directory>
    
    ErrorLog "logs/esfero_error.log"
    CustomLog "logs/esfero_access.log" combined
</VirtualHost>

# Redirección HTTP a HTTPS
<VirtualHost *:80>
    ServerName esfero.local
    Redirect permanent / https://esfero.local/
</VirtualHost>
```

### 3.3 Reiniciar Apache

Reinicia Apache desde el panel de control de XAMPP o desde servicios de Windows.

## Paso 4: Actualizar Configuración de la Aplicación

Si es necesario, actualiza el archivo `.env`:

```env
APP_URL=https://esfero.local
PAYPAL_RETURN_URL=https://esfero.local/frontend/checkout.php?success=true
PAYPAL_CANCEL_URL=https://esfero.local/frontend/checkout.php?canceled=true
```

## Paso 5: Probar la Configuración

1. Abre tu navegador
2. Visita: `https://esfero.local`
3. Verás una advertencia de seguridad (esto es normal con certificados autofirmados)
4. Haz clic en "Avanzado" y luego "Continuar a esfero.local"

## Solución de Problemas

### Error: "No se puede acceder a este sitio"
- Verifica que Apache esté corriendo
- Verifica que el archivo `hosts` tenga la entrada correcta
- Reinicia el navegador después de editar `hosts`

### Error: "Certificado no válido"
- Esto es normal con certificados autofirmados
- Acepta la excepción en el navegador

### Error: "Apache no inicia"
- Verifica la sintaxis del archivo de configuración
- Revisa los logs de error de Apache
- Verifica que las rutas de los certificados sean correctas

## Notas Importantes

- El certificado autofirmado solo es válido para desarrollo local
- Los navegadores mostrarán advertencias de seguridad (esto es normal)
- El certificado expira en 365 días (puedes regenerarlo cuando sea necesario)
- Esta configuración NO es para producción

