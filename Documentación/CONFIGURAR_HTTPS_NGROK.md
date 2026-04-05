# Configurar HTTPS con Ngrok

## Pasos para configurar HTTPS correctamente

### 1. Verificar configuración actual

```bash
# Ver el VirtualHost de HTTPS
cat /etc/apache2/sites-enabled/esfero-ssl.conf

# Ver qué puerto está usando Ngrok actualmente
ps aux | grep ngrok | grep -o "localhost:[0-9]*"
```

### 2. Editar el VirtualHost de HTTPS

```bash
sudo nano /etc/apache2/sites-enabled/esfero-ssl.conf
```

Asegúrate de que tenga esta configuración:

```apache
<VirtualHost *:443>
    ServerName esfero.local
    ServerAlias localhost
    
    DocumentRoot /home/fer/Esfero/frontend
    
    <Directory /home/fer/Esfero/frontend>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Configuración SSL
    SSLEngine on
    SSLCertificateFile /home/fer/Esfero/ssl/esfero.crt
    SSLCertificateKeyFile /home/fer/Esfero/ssl/esfero.key
    
    # Python CGI Backend
    ScriptAlias /backend/cgi-bin/ /home/fer/Esfero/backend/cgi-bin/
    <Directory /home/fer/Esfero/backend/cgi-bin>
        Options +ExecCGI
        AddHandler cgi-script .py
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/esfero_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/esfero_ssl_access.log combined
</VirtualHost>
```

### 3. Hacer que esfero-ssl.conf sea el VirtualHost predeterminado para HTTPS

```bash
# Renombrar para que se cargue primero (orden alfabético)
sudo mv /etc/apache2/sites-enabled/esfero-ssl.conf /etc/apache2/sites-enabled/000-esfero-ssl.conf

# O si ya existe 000-default.conf para HTTPS, deshabilitarlo
sudo a2dissite 000-default-ssl.conf 2>/dev/null
```

### 4. Verificar que el puerto 443 esté escuchando

```bash
# Verificar que Apache esté escuchando en 443
sudo netstat -tulpn | grep :443

# Si no está escuchando, verificar que el módulo SSL esté habilitado
sudo a2enmod ssl
sudo systemctl restart apache2
```

### 5. Recargar Apache

```bash
# Verificar sintaxis de configuración
sudo apache2ctl configtest

# Si está bien, recargar
sudo systemctl reload apache2

# O reiniciar si hay cambios importantes
sudo systemctl restart apache2
```

### 6. Configurar Ngrok para puerto 443

```bash
# Detener Ngrok si está corriendo
pkill ngrok

# Iniciar Ngrok apuntando al puerto 443 (HTTPS)
ngrok http localhost:443
```

### 7. Verificar que funciona

```bash
# Probar directamente con curl (ignorar certificado autofirmado)
curl -k https://localhost/

# Debería mostrar tu aplicación, no la página por defecto
```

## Comandos completos (copia y pega)

```bash
# 1. Editar configuración de HTTPS
sudo nano /etc/apache2/sites-enabled/esfero-ssl.conf

# 2. Asegurar que tenga DocumentRoot correcto y ServerAlias localhost
# (Editar manualmente según la configuración de arriba)

# 3. Hacer que sea el predeterminado
sudo mv /etc/apache2/sites-enabled/esfero-ssl.conf /etc/apache2/sites-enabled/000-esfero-ssl.conf 2>/dev/null || true

# 4. Verificar módulo SSL
sudo a2enmod ssl

# 5. Verificar sintaxis
sudo apache2ctl configtest

# 6. Recargar Apache
sudo systemctl reload apache2

# 7. Verificar puerto 443
sudo netstat -tulpn | grep :443

# 8. Reiniciar Ngrok
pkill ngrok
ngrok http localhost:443
```

## Verificar configuración final

```bash
# Ver qué VirtualHost es el predeterminado para HTTPS
apache2ctl -S | grep ":443" | grep "default server"

# Probar con curl
curl -k https://localhost/ | head -20
```

## Notas importantes

- **Puerto 443**: Es el puerto estándar para HTTPS
- **Certificado autofirmado**: Ngrok aceptará el certificado autofirmado sin problemas
- **ServerAlias localhost**: Necesario para que funcione cuando Ngrok hace proxy
- **Orden de VirtualHosts**: Los archivos se cargan en orden alfabético, por eso renombramos a `000-esfero-ssl.conf`

