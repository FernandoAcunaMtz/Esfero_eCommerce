# 📋 Comandos para Ver Logs de PHP en el Servidor Remoto

## 📍 Ubicación del Log
```
/home/fer/Esfero/logs/php_errors.log
```

## 🔍 Comandos Útiles

### Ver las últimas líneas del log
```bash
tail -n 50 /home/fer/Esfero/logs/php_errors.log
```

### Ver el log en tiempo real (seguir nuevas entradas)
```bash
tail -f /home/fer/Esfero/logs/php_errors.log
```

### Ver todo el contenido del log
```bash
cat /home/fer/Esfero/logs/php_errors.log
```

### Buscar errores específicos
```bash
# Buscar todas las líneas con "error" (sin distinguir mayúsculas)
grep -i error /home/fer/Esfero/logs/php_errors.log

# Buscar errores del checkout
grep -i checkout /home/fer/Esfero/logs/php_errors.log

# Buscar errores de PayPal
grep -i paypal /home/fer/Esfero/logs/php_errors.log
```

### Ver errores más recientes (última hora)
```bash
# Si el log tiene timestamps
grep "$(date '+%Y-%m-%d %H')" /home/fer/Esfero/logs/php_errors.log
```

### Limpiar el log (¡cuidado!)
```bash
# Hacer backup primero
cp /home/fer/Esfero/logs/php_errors.log /home/fer/Esfero/logs/php_errors.log.backup

# Limpiar el log
> /home/fer/Esfero/logs/php_errors.log
```

### Ver tamaño del archivo
```bash
ls -lh /home/fer/Esfero/logs/php_errors.log
```

### Crear el directorio si no existe
```bash
mkdir -p /home/fer/Esfero/logs
chmod 755 /home/fer/Esfero/logs
```

## 📝 Desde el Cliente FTP

Si estás usando un cliente FTP:
1. Navega a: `/home/fer/Esfero/logs/`
2. Descarga el archivo: `php_errors.log`
3. Ábrelo con un editor de texto

## 🌐 Desde el Navegador (si tienes acceso)

Si tienes acceso al servidor web, puedes usar:
```
http://tu-dominio/frontend/ver_logs.php
```

## ⚠️ Nota Importante

Los logs pueden crecer mucho. Considera:
- Monitorear el tamaño del archivo periódicamente
- Hacer rotación de logs
- Limpiar logs antiguos cuando sea necesario

