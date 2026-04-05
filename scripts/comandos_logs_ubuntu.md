# Comandos para Revisar Logs en Ubuntu/Apache

## 1. Ver el log de errores de Apache
```bash
# Ver las últimas 50 líneas
sudo tail -n 50 /var/log/apache2/error.log

# Ver en tiempo real (seguir el log)
sudo tail -f /var/log/apache2/error.log

# Ver las últimas 100 líneas y buscar errores de PHP
sudo tail -n 100 /var/log/apache2/error.log | grep -i php

# Buscar errores específicos de checkout
sudo grep -i "checkout\|process_checkout" /var/log/apache2/error.log | tail -n 50
```

## 2. Ver el log de acceso de Apache
```bash
# Ver las últimas líneas
sudo tail -n 50 /var/log/apache2/access.log

# Ver en tiempo real
sudo tail -f /var/log/apache2/access.log
```

## 3. Ver logs de PHP (si están configurados)
```bash
# Buscar el log de PHP configurado
php -i | grep error_log

# Ver el log si está en ubicación personalizada
tail -n 50 /ruta/al/php_error.log
```

## 4. Buscar errores específicos
```bash
# Buscar errores de JSON
sudo grep -i "json\|decode" /var/log/apache2/error.log | tail -n 20

# Buscar errores de las últimas 2 horas
sudo grep "$(date -d '2 hours ago' '+%Y-%m-%d %H')" /var/log/apache2/error.log

# Buscar errores de hoy
sudo grep "$(date '+%Y-%m-%d')" /var/log/apache2/error.log | tail -n 50
```

## 5. Ver errores en tiempo real mientras pruebas
```bash
# En una terminal SSH, ejecuta esto y luego intenta hacer checkout
sudo tail -f /var/log/apache2/error.log | grep -i --line-buffered "error\|warning\|fatal"
```

## 6. Ver el tamaño y estadísticas del log
```bash
# Ver tamaño del log
ls -lh /var/log/apache2/error.log

# Contar líneas de error
sudo wc -l /var/log/apache2/error.log

# Ver los últimos errores con contexto (5 líneas antes y después)
sudo grep -A 5 -B 5 -i "error" /var/log/apache2/error.log | tail -n 50
```

## 7. Limpiar el log (si es muy grande)
```bash
# Hacer backup primero
sudo cp /var/log/apache2/error.log /var/log/apache2/error.log.backup

# Limpiar el log (mantener las últimas 1000 líneas)
sudo tail -n 1000 /var/log/apache2/error.log > /tmp/error.log.tmp
sudo mv /tmp/error.log.tmp /var/log/apache2/error.log
```

## 8. Verificar configuración de PHP
```bash
# Ver dónde está configurado el error_log
php -i | grep error_log

# Ver configuración de logging
php -i | grep -E "log_errors|display_errors|error_reporting"
```

## Comando rápido para el problema de checkout
```bash
# Ver errores recientes relacionados con checkout/JSON
sudo tail -n 100 /var/log/apache2/error.log | grep -E "checkout|json|process_checkout|PHP" -i -A 3 -B 3
```

