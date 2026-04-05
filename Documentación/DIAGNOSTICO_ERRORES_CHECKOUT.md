# Diagnóstico de Errores en Checkout y Carrito

## Problema Común: "Error de conexión"

Si ves el mensaje "Error de conexión" o "No se pudo conectar con el servidor", significa que el frontend PHP no puede comunicarse con el backend Python.

## Pasos de Diagnóstico

### 1. Verificar que el Backend Python esté corriendo

```bash
# Verificar procesos de Python relacionados con el backend
ps aux | grep python | grep -E "(carrito|ordenes|paypal)"

# Verificar que Apache esté corriendo
sudo systemctl status apache2

# Verificar que el directorio del backend existe
ls -la /home/fer/Esfero/backend/cgi-bin/
```

### 2. Verificar que el Backend responda

```bash
# Probar conexión directa al backend
curl -X GET "http://10.241.109.37/backend/cgi-bin/carrito.py/items" \
  -H "Authorization: Bearer TU_TOKEN_AQUI" \
  -H "Accept: application/json"

# Si no tienes token, prueba un endpoint público
curl -X GET "http://10.241.109.37/backend/cgi-bin/productos.py"
```

### 3. Verificar logs del Backend

```bash
# Ver logs de Apache (errores del backend)
sudo tail -50 /var/log/apache2/error.log

# Ver logs específicos del backend si existen
ls -la /home/fer/Esfero/backend/logs/
```

### 4. Verificar configuración de Apache para CGI

```bash
# Verificar que el módulo CGI esté habilitado
sudo a2enmod cgi
sudo systemctl restart apache2

# Verificar configuración del VirtualHost
sudo cat /etc/apache2/sites-enabled/000-default.conf | grep -A 20 "ScriptAlias"
```

### 5. Verificar permisos del Backend

```bash
# Verificar permisos de ejecución
ls -la /home/fer/Esfero/backend/cgi-bin/*.py

# Dar permisos de ejecución si es necesario
chmod +x /home/fer/Esfero/backend/cgi-bin/*.py
```

### 6. Verificar que la URL base sea correcta

En `frontend/includes/api_helper.php`, línea 10:

```php
define('API_BASE_URL', 'http://10.241.109.37/backend/cgi-bin');
```

Verifica que esta IP sea la correcta de tu servidor:

```bash
hostname -I
```

## Soluciones Comunes

### Error: "No se pudo conectar con el servidor"

**Causa:** El backend Python no está corriendo o Apache no está configurado correctamente.

**Solución:**

1. Reiniciar Apache:
```bash
sudo systemctl restart apache2
sudo systemctl status apache2
```

2. Verificar que los scripts Python tengan permisos de ejecución:
```bash
chmod +x /home/fer/Esfero/backend/cgi-bin/*.py
```

3. Verificar que Apache pueda ejecutar scripts Python:
```bash
sudo cat /etc/apache2/sites-enabled/000-default.conf
```

Debe tener algo como:
```apache
ScriptAlias /backend/cgi-bin/ /home/fer/Esfero/backend/cgi-bin/
<Directory "/home/fer/Esfero/backend/cgi-bin">
    Options +ExecCGI
    AddHandler cgi-script .py
    Require all granted
</Directory>
```

### Error: "Error del servidor (500)"

**Causa:** Error en el código Python del backend.

**Solución:**

1. Ver logs de Apache:
```bash
sudo tail -100 /var/log/apache2/error.log
```

2. Verificar sintaxis de los scripts Python:
```bash
python3 -m py_compile /home/fer/Esfero/backend/cgi-bin/carrito.py
python3 -m py_compile /home/fer/Esfero/backend/cgi-bin/ordenes.py
```

### Error: "Error del servidor (401)" o "Tu sesión ha expirado"

**Causa:** El token JWT ha expirado o es inválido.

**Solución:**

1. Cerrar sesión y volver a iniciar sesión
2. Verificar que el token se esté enviando correctamente en los headers

### Error: "La solicitud tardó demasiado"

**Causa:** Timeout de la conexión (más de 10-15 segundos).

**Solución:**

1. Verificar que el backend no esté sobrecargado
2. Verificar la conexión a la base de datos:
```bash
mysql -u tu_usuario -p -e "SELECT 1"
```

3. Aumentar el timeout en `api_helper.php` si es necesario (línea 11):
```php
define('API_TIMEOUT', 30); // Aumentar a 30 segundos
```

## Comandos de Verificación Rápida

```bash
# Script completo de verificación
#!/bin/bash
echo "=== Verificando Backend ==="
echo "1. Apache status:"
sudo systemctl status apache2 --no-pager | head -5

echo -e "\n2. Procesos Python:"
ps aux | grep python | grep -E "(carrito|ordenes|paypal)" || echo "No hay procesos Python del backend corriendo"

echo -e "\n3. Permisos de scripts:"
ls -la /home/fer/Esfero/backend/cgi-bin/*.py | head -3

echo -e "\n4. Últimos errores de Apache:"
sudo tail -5 /var/log/apache2/error.log

echo -e "\n5. Test de conexión:"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://10.241.109.37/backend/cgi-bin/productos.py || echo "No se pudo conectar"
```

## Próximos Pasos

Si después de seguir estos pasos el problema persiste:

1. Revisar los logs detallados de Apache
2. Verificar la configuración de la base de datos
3. Probar los endpoints del backend directamente con `curl`
4. Verificar que todas las dependencias de Python estén instaladas

