# Cómo Iniciar tu Sitio Esfero

## Pasos Rápidos

### 1. Verificar que Apache esté corriendo

```bash
sudo systemctl status apache2
```

Si no está corriendo, inícialo:

```bash
sudo systemctl start apache2
```

### 2. Iniciar Ngrok

**Opción A: Comando simple (recomendado)**

```bash
ngrok http localhost:443
```

**Opción B: Con host-header (si es necesario)**

```bash
ngrok http 443 --host-header=esfero.local
```

**Opción C: Usar el script rápido**

```bash
cd ~/Esfero
./scripts/quick_restart_ngrok.sh
```

### 3. Obtener la URL pública

Una vez iniciado Ngrok, verás algo como:

```
Forwarding  https://xxxx-xxxx-xxxx.ngrok-free.app -> http://localhost:443
```

**Copia esa URL HTTPS** y úsala para acceder a tu sitio.

También puedes ver la URL en: **http://localhost:4040** (panel de Ngrok)

### 4. Detener Ngrok

Presiona `Ctrl+C` en la terminal donde está corriendo Ngrok.

---

## Comandos Útiles

### Verificar estado de Apache
```bash
sudo systemctl status apache2
```

### Verificar que Apache está escuchando en el puerto 443
```bash
sudo netstat -tulpn | grep :443
```

### Ver si Ngrok está corriendo
```bash
ps aux | grep ngrok
```

### Ver la URL de Ngrok (si está corriendo)
```bash
curl -s http://localhost:4040/api/tunnels | grep -o '"public_url":"[^"]*' | grep -o 'https://[^"]*' | head -1
```

### Detener Ngrok
```bash
pkill ngrok
```

---

## Solución de Problemas

### Si Apache no inicia:
```bash
sudo apache2ctl configtest
sudo systemctl restart apache2
```

### Si Ngrok muestra error:
1. Verifica que Apache esté corriendo
2. Verifica que el puerto 443 esté escuchando
3. Prueba con el puerto 80: `ngrok http localhost:80`

### Si ves la página por defecto de Apache:
- Verifica que el VirtualHost correcto esté habilitado
- Verifica que `DocumentRoot` apunte a `/home/fer/Esfero/frontend`

