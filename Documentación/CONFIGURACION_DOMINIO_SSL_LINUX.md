# Configuración de Dominio Local y SSL Autofirmado en Ubuntu

## Objetivo
Configurar un dominio local (ej: `esfero.local`) con certificado SSL autofirmado en el servidor Ubuntu remoto.

## Requisitos Previos
- Apache instalado y funcionando
- OpenSSL instalado (generalmente viene preinstalado en Ubuntu)
- Permisos de sudo

## Paso 1: Verificar Apache y OpenSSL

```bash
# Verificar Apache
sudo systemctl status apache2

# Verificar OpenSSL
openssl version
```

## Paso 2: Generar Certificado SSL Autofirmado

### Opción A: Usando el Script Automático

```bash
cd /ruta/a/Esfero
chmod +x scripts/generar_certificado_ssl.sh
sudo ./scripts/generar_certificado_ssl.sh
```

### Opción B: Manual

```bash
# Navegar al directorio del proyecto
cd /ruta/a/Esfero

# Crear directorio para certificados
mkdir -p ssl
cd ssl

# Generar clave privada
sudo openssl genrsa -out esfero.key 2048

# Generar certificado autofirmado
sudo openssl req -new -x509 -key esfero.key -out esfero.crt -days 365 \
  -subj "/CN=esfero.local/O=Esfero/C=MX"

# Ajustar permisos
sudo chmod 600 esfero.key
sudo chmod 644 esfero.crt
sudo chown root:root esfero.key esfero.crt
```

## Paso 3: Habilitar Módulos de Apache

```bash
# Habilitar módulos necesarios
sudo a2enmod ssl
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod cgi

# Reiniciar Apache
sudo systemctl restart apache2
```

## Paso 4: Configurar VirtualHost

### 4.1 Crear Archivo de Configuración

```bash
sudo nano /etc/apache2/sites-available/esfero-ssl.conf
```

### 4.2 Contenido del Archivo

**IMPORTANTE:** Ajusta las rutas según la ubicación real de tu proyecto.

```apache
<VirtualHost *:443>
    ServerName esfero.local
    ServerAlias www.esfero.local
    
    # Ruta al DocumentRoot (ajustar según tu instalación)
    DocumentRoot /var/www/html/esfero/frontend
    
    # Configuración SSL
    SSLEngine on
    SSLCertificateFile /var/www/html/esfero/ssl/esfero.crt
    SSLCertificateKeyFile /var/www/html/esfero/ssl/esfero.key
    
    # Configuración del directorio principal
    <Directory /var/www/html/esfero/frontend>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Python CGI
    ScriptAlias /backend/cgi-bin/ /var/www/html/esfero/backend/cgi-bin/
    <Directory /var/www/html/esfero/backend/cgi-bin>
        Options +ExecCGI
        AddHandler cgi-script .py
        Require all granted
    </Directory>
    
    # Headers de seguridad
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    
    # Logs
    ErrorLog ${APACHE_LOG_DIR}/esfero_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/esfero_ssl_access.log combined
</VirtualHost>

# Redirección HTTP a HTTPS
<VirtualHost *:80>
    ServerName esfero.local
    ServerAlias www.esfero.local
    
    # Redirigir todo a HTTPS
    Redirect permanent / https://esfero.local/
</VirtualHost>
```

### 4.3 Habilitar el Sitio

```bash
# Habilitar el sitio
sudo a2ensite esfero-ssl.conf

# Verificar configuración
sudo apache2ctl configtest

# Si todo está bien, recargar Apache
sudo systemctl reload apache2
```

## Paso 5: Configurar /etc/hosts (Opcional para acceso local)

Si quieres acceder desde tu máquina local usando el dominio:

**En tu máquina local (Windows):**
1. Abre `C:\Windows\System32\drivers\etc\hosts` como Administrador
2. Agrega: `IP_DEL_SERVIDOR    esfero.local`
   Ejemplo: `192.168.26.128    esfero.local`

**En el servidor (si accedes desde el mismo servidor):**
```bash
sudo nano /etc/hosts
# Agrega: 127.0.0.1    esfero.local
```

## Paso 6: Actualizar Configuración de la Aplicación

Si es necesario, actualiza el archivo `.env`:

```bash
nano /var/www/html/esfero/.env
```

```env
APP_URL=https://esfero.local
PAYPAL_RETURN_URL=https://esfero.local/frontend/checkout.php?success=true
PAYPAL_CANCEL_URL=https://esfero.local/frontend/checkout.php?canceled=true
```

## Paso 7: Verificar Configuración

```bash
# Verificar que Apache esté escuchando en el puerto 443
sudo netstat -tlnp | grep :443

# Verificar logs si hay problemas
sudo tail -f /var/log/apache2/esfero_ssl_error.log
```

## Paso 8: Probar Acceso

1. Desde el navegador, visita: `https://esfero.local` o `https://IP_DEL_SERVIDOR`
2. Verás una advertencia de seguridad (normal con certificados autofirmados)
3. Haz clic en "Avanzado" y luego "Continuar al sitio"

## Solución de Problemas

### Error: "Apache no inicia"
```bash
# Verificar sintaxis
sudo apache2ctl configtest

# Ver logs detallados
sudo journalctl -u apache2 -n 50
```

### Error: "Certificado no encontrado"
- Verifica las rutas en el archivo de configuración
- Asegúrate de que los archivos existan: `ls -la /var/www/html/esfero/ssl/`

### Error: "Permission denied"
```bash
# Ajustar permisos del directorio del proyecto
sudo chown -R www-data:www-data /var/www/html/esfero
sudo chmod -R 755 /var/www/html/esfero
```

### Error: "Module ssl not found"
```bash
# Instalar módulo SSL
sudo apt-get update
sudo apt-get install apache2
sudo a2enmod ssl
```

## Notas Importantes

- El certificado autofirmado solo es válido para desarrollo/pruebas
- Los navegadores mostrarán advertencias de seguridad (esto es normal)
- El certificado expira en 365 días
- Esta configuración NO es para producción (usa Let's Encrypt para producción)

## Firewall

Si tienes firewall activo, asegúrate de permitir los puertos:

```bash
# UFW (si está activo)
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw status

# O firewalld (CentOS/RHEL)
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

