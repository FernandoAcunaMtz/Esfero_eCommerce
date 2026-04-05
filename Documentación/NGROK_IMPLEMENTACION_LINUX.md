# Implementación de Ngrok en Esfero - Servidor Ubuntu

## ¿Qué es Ngrok?

Ngrok crea túneles seguros desde internet hacia tu servidor local/remoto. Permite exponer tu aplicación que corre en el servidor Ubuntu a través de una URL pública.

## Casos de Uso para Servidor Remoto

1. **Demostraciones en vivo**: Compartir tu aplicación con clientes o profesores sin necesidad de configurar DNS
2. **Pruebas de integración**: Probar webhooks de PayPal, notificaciones, etc.
3. **Desarrollo colaborativo**: Permitir que otros accedan a tu servidor de desarrollo
4. **Acceso desde cualquier lugar**: Acceder a tu aplicación desde cualquier dispositivo

## Instalación en Ubuntu

### Opción 1: Descarga Directa (Recomendado)

```bash
# Descargar Ngrok
cd /tmp
wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.tgz

# Extraer
tar xvzf ngrok-v3-stable-linux-amd64.tgz

# Mover a /usr/local/bin (accesible globalmente)
sudo mv ngrok /usr/local/bin/

# Verificar instalación
ngrok version
```

### Opción 2: Usando Snap

```bash
sudo snap install ngrok
```

## Configuración Inicial

1. **Crear cuenta en Ngrok** (opcional pero recomendado):
   - Visita: https://dashboard.ngrok.com/signup
   - Crea una cuenta gratuita
   - Obtén tu authtoken del dashboard

2. **Configurar authtoken**:
   ```bash
   ngrok config add-authtoken TU_AUTHTOKEN_AQUI
   ```

## Uso Básico

### Exponer Puerto HTTPS (443)

```bash
ngrok http 443 --host-header=esfero.local
```

### Exponer Puerto HTTP (80)

```bash
ngrok http 80
```

### Exponer con Dominio Personalizado (Requiere plan de pago)

```bash
ngrok http 443 --hostname=tu-dominio.ngrok.io --host-header=esfero.local
```

## Integración con Esfero en Servidor Remoto

### IP del Servidor

Según la información del servidor:
- **IP Privada**: `192.168.26.128`
- **IP Pública**: (depende de tu configuración de red)

### Script de Inicio Automático

Se ha creado el script `scripts/start_ngrok.sh` que:
- Inicia Ngrok apuntando al puerto 443 (HTTPS)
- Muestra la URL pública generada
- Mantiene el túnel activo

### Uso del Script

```bash
# Dar permisos de ejecución
chmod +x scripts/start_ngrok.sh

# Ejecutar
./scripts/start_ngrok.sh
```

O ejecutar directamente:
```bash
ngrok http 443 --host-header=esfero.local
```

### Ejecutar en Background (Recomendado para servidor)

```bash
# Usar nohup para ejecutar en background
nohup ngrok http 443 --host-header=esfero.local > /tmp/ngrok.log 2>&1 &

# Ver el proceso
ps aux | grep ngrok

# Ver la URL (Ngrok también tiene una API)
curl http://localhost:4040/api/tunnels
```

### Crear Servicio Systemd (Para iniciar automáticamente)

```bash
sudo nano /etc/systemd/system/ngrok-esfero.service
```

Contenido:
```ini
[Unit]
Description=Ngrok tunnel for Esfero
After=network.target

[Service]
Type=simple
User=fer
ExecStart=/usr/local/bin/ngrok http 443 --host-header=esfero.local
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Activar el servicio:
```bash
sudo systemctl daemon-reload
sudo systemctl enable ngrok-esfero.service
sudo systemctl start ngrok-esfero.service
sudo systemctl status ngrok-esfero.service
```

## Actualizar URLs de PayPal

Si necesitas que PayPal llame de vuelta a través de Ngrok:

1. Obtén la URL pública de Ngrok:
   ```bash
   curl http://localhost:4040/api/tunnels | jq -r '.tunnels[0].public_url'
   ```

2. Actualiza temporalmente tu `.env`:
   ```env
   PAYPAL_RETURN_URL=https://abc123.ngrok.io/frontend/checkout.php?success=true
   PAYPAL_CANCEL_URL=https://abc123.ngrok.io/frontend/checkout.php?canceled=true
   ```

3. **IMPORTANTE**: Recuerda revertir estos cambios después de las pruebas

## Configuración Avanzada

### Archivo de Configuración de Ngrok

Crea `~/.ngrok2/ngrok.yml`:

```yaml
version: "2"
authtoken: TU_AUTHTOKEN_AQUI
tunnels:
  esfero:
    proto: http
    addr: 443
    host_header: esfero.local
    inspect: true
```

Luego puedes iniciar con:
```bash
ngrok start esfero
```

### Inspección de Tráfico

Ngrok incluye una interfaz web para inspeccionar el tráfico:
- Visita: http://localhost:4040 (desde el servidor)
- O desde tu máquina local: http://IP_DEL_SERVIDOR:4040 (si el firewall lo permite)

### Exponer la Interfaz Web de Ngrok

Si quieres acceder a la interfaz web desde fuera del servidor:

```bash
ngrok http 4040
```

## Consideraciones de Seguridad

1. **URLs Públicas**: Cualquiera con la URL puede acceder a tu aplicación
2. **Datos Sensibles**: No uses Ngrok con datos de producción reales
3. **Autenticación**: Asegúrate de que tu aplicación tenga autenticación activa
4. **Rate Limiting**: El plan gratuito tiene límites de peticiones
5. **Firewall**: Considera restringir el acceso a la interfaz web de Ngrok (puerto 4040)

## Solución de Problemas

### Error: "authtoken required"
```bash
ngrok config add-authtoken TU_TOKEN
```

### Error: "bind: address already in use"
```bash
# Encontrar proceso usando el puerto
sudo lsof -i :443
# O
sudo netstat -tlnp | grep :443

# Matar proceso si es necesario
sudo kill -9 PID
```

### La URL no funciona
```bash
# Verificar que Apache esté corriendo
sudo systemctl status apache2

# Verificar que el puerto esté abierto
sudo netstat -tlnp | grep :443

# Ver logs de Ngrok
tail -f /tmp/ngrok.log
```

### Ngrok se desconecta frecuentemente
- Usa `nohup` o un servicio systemd para mantenerlo corriendo
- Considera el plan de pago para conexiones más estables

### Verificar Estado del Túnel
```bash
# Ver información del túnel
curl http://localhost:4040/api/tunnels

# Ver estadísticas
curl http://localhost:4040/api/requests/http
```

## Alternativas a Ngrok

Si Ngrok no funciona para tus necesidades:

1. **localtunnel**: Alternativa open-source
   ```bash
   npm install -g localtunnel
   lt --port 443
   ```

2. **Cloudflare Tunnel**: Más robusto pero más complejo
   ```bash
   # Instalar cloudflared
   wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64
   chmod +x cloudflared-linux-amd64
   sudo mv cloudflared-linux-amd64 /usr/local/bin/cloudflared
   ```

3. **SSH Tunnel**: Si tienes acceso SSH a otro servidor
   ```bash
   ssh -R 80:localhost:443 usuario@servidor-remoto
   ```

## Recursos Adicionales

- Documentación oficial: https://ngrok.com/docs
- Dashboard: https://dashboard.ngrok.com
- API de Ngrok: http://localhost:4040/api

