# Solución: Ngrok muestra página por defecto de Apache

## Problema

Ngrok está mostrando la página por defecto de Apache en lugar de tu aplicación Esfero.

## Causa

Ngrok está apuntando al puerto 443 (HTTPS), pero el VirtualHost de HTTPS (`esfero-ssl.conf`) puede no tener el `DocumentRoot` correcto o no estar configurado como predeterminado.

## Solución

### Opción 1: Configurar VirtualHost de HTTPS correctamente (RECOMENDADO)

```bash
# Ver la configuración actual de HTTPS
cat /etc/apache2/sites-enabled/esfero-ssl.conf

# Editar el VirtualHost de HTTPS
sudo nano /etc/apache2/sites-enabled/esfero-ssl.conf
```

Asegúrate de que tenga:
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
    
    # ... resto de configuración SSL ...
</VirtualHost>
```

### Opción 2: Usar Ngrok en puerto 80 (HTTP) en lugar de 443 (HTTPS)

```bash
# Detener Ngrok
pkill ngrok

# Reiniciar Ngrok en puerto 80
ngrok http localhost:80
```

### Opción 3: Hacer que esfero-ssl.conf sea el VirtualHost predeterminado

```bash
# Verificar orden actual
apache2ctl -S | grep "default server"

# Si el predeterminado es otro, renombrar archivos para cambiar el orden
# Los archivos se cargan en orden alfabético
sudo mv /etc/apache2/sites-enabled/000-default.conf /etc/apache2/sites-enabled/999-default.conf 2>/dev/null
sudo mv /etc/apache2/sites-enabled/esfero-ssl.conf /etc/apache2/sites-enabled/000-esfero-ssl.conf

# Recargar Apache
sudo systemctl reload apache2
```

## Verificar

```bash
# Ver qué VirtualHost es el predeterminado para HTTPS
apache2ctl -S | grep ":443"

# Probar directamente
curl -k https://localhost/
```

## Comandos rápidos

```bash
# Ver configuración de HTTPS
grep -A 5 "DocumentRoot" /etc/apache2/sites-enabled/esfero-ssl.conf

# Ver qué puerto usa Ngrok
ps aux | grep ngrok | grep -o "localhost:[0-9]*"

# Reiniciar Ngrok en puerto 80
pkill ngrok && ngrok http localhost:80
```

