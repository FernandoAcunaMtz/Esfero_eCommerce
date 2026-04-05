# Solución al Error 404 en process_cart.php

## Problema Identificado

Apache está devolviendo `404 Not Found` porque el `DocumentRoot` no apunta al directorio correcto donde está `process_cart.php`.

## Diagnóstico

### 1. Verificar configuración actual de Apache

```bash
# Ver DocumentRoot configurado
grep -r "DocumentRoot" /etc/apache2/sites-enabled/

# Ver VirtualHosts activos
apache2ctl -S

# Ver dónde está realmente process_cart.php
find /home/fer/Esfero -name "process_cart.php" -type f
```

### 2. Verificar VirtualHost por defecto

```bash
cat /etc/apache2/sites-enabled/000-default.conf
```

## Soluciones

### Opción 1: Configurar VirtualHost para Esfero (RECOMENDADO)

```bash
# Crear nuevo VirtualHost
sudo nano /etc/apache2/sites-available/esfero.conf
```

Pegar esta configuración:

```apache
<VirtualHost *:80>
    ServerName esfero.local
    DocumentRoot /home/fer/Esfero/frontend
    
    <Directory /home/fer/Esfero/frontend>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/esfero_error.log
    CustomLog ${APACHE_LOG_DIR}/esfero_access.log combined
</VirtualHost>
```

```bash
# Habilitar el sitio
sudo a2ensite esfero.conf

# Deshabilitar el sitio por defecto (opcional)
sudo a2dissite 000-default.conf

# Recargar Apache
sudo systemctl reload apache2
```

### Opción 2: Cambiar DocumentRoot del sitio por defecto

```bash
# Editar el VirtualHost por defecto
sudo nano /etc/apache2/sites-available/000-default.conf
```

Cambiar:
```apache
DocumentRoot /var/www/html
```

Por:
```apache
DocumentRoot /home/fer/Esfero/frontend
```

Y agregar:
```apache
<Directory /home/fer/Esfero/frontend>
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

```bash
# Recargar Apache
sudo systemctl reload apache2
```

### Opción 3: Crear symlink (SOLUCIÓN RÁPIDA)

```bash
# Crear symlink desde DocumentRoot a Esfero
sudo ln -s /home/fer/Esfero/frontend /var/www/html/esfero

# Luego acceder como: http://localhost/esfero/process_cart.php
```

## Verificar que funciona

```bash
# Probar directamente
curl -X POST "http://localhost/process_cart.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=add&producto_id=65&cantidad=1" \
  -i
```

Debería devolver JSON, no 404.

## Configurar Ngrok

Una vez que Apache esté configurado correctamente, reiniciar Ngrok:

```bash
# Detener Ngrok
pkill ngrok

# Reiniciar Ngrok apuntando al puerto correcto
ngrok http localhost:80
# O si usas HTTPS:
ngrok http localhost:443
```

## Verificar permisos

```bash
# Asegurar permisos correctos
sudo chown -R www-data:www-data /home/fer/Esfero/frontend
sudo chmod -R 755 /home/fer/Esfero/frontend
sudo find /home/fer/Esfero/frontend -type f -exec chmod 644 {} \;
```

