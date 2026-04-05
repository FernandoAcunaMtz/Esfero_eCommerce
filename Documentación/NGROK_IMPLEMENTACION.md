# Implementación de Ngrok en Esfero

## ¿Qué es Ngrok?

Ngrok es una herramienta que crea túneles seguros desde internet hacia tu servidor local. Permite exponer tu aplicación local (que corre en `localhost` o `esfero.local`) a través de una URL pública temporal o permanente.

## Casos de Uso

1. **Demostraciones en vivo**: Compartir tu aplicación con clientes o profesores sin necesidad de un servidor en la nube
2. **Pruebas de integración**: Probar webhooks de PayPal, notificaciones, etc.
3. **Desarrollo colaborativo**: Permitir que otros desarrolladores accedan a tu entorno local
4. **Pruebas en dispositivos móviles**: Acceder a tu aplicación desde tu teléfono usando la URL pública

## Limitaciones

- **Plan Gratuito**: URLs temporales que cambian cada vez que reinicias Ngrok
- **Plan de Pago**: URLs permanentes y más ancho de banda
- **Rendimiento**: Depende de la conexión a internet
- **Seguridad**: Las URLs públicas son accesibles por cualquiera que tenga el enlace

## Instalación

### Opción 1: Descarga Directa (Recomendado)

1. Visita: https://ngrok.com/download
2. Descarga la versión para Windows
3. Extrae `ngrok.exe` a una carpeta (ej: `C:\ngrok\`)
4. Agrega la carpeta al PATH del sistema (opcional pero recomendado)

### Opción 2: Usando Chocolatey

```powershell
choco install ngrok
```

## Configuración Inicial

1. **Crear cuenta en Ngrok** (opcional pero recomendado):
   - Visita: https://dashboard.ngrok.com/signup
   - Crea una cuenta gratuita
   - Obtén tu authtoken del dashboard

2. **Configurar authtoken**:
   ```powershell
   ngrok config add-authtoken TU_AUTHTOKEN_AQUI
   ```

## Uso Básico

### Exponer Puerto HTTP (80)

```powershell
ngrok http 80
```

### Exponer Puerto HTTPS (443)

```powershell
ngrok http 443
```

### Exponer con Dominio Personalizado (Requiere plan de pago)

```powershell
ngrok http esfero.local:443 --hostname=tu-dominio.ngrok.io
```

## Integración con Esfero

### Script de Inicio Automático

Se ha creado el script `scripts/start_ngrok.bat` que:
- Inicia Ngrok apuntando al puerto 443 (HTTPS)
- Muestra la URL pública generada
- Mantiene el túnel activo

### Uso del Script

1. Asegúrate de que tu aplicación esté corriendo en `https://esfero.local`
2. Ejecuta `scripts/start_ngrok.bat`
3. Copia la URL pública que se muestra (ej: `https://abc123.ngrok.io`)
4. Comparte esta URL con quien necesite acceder

### Actualizar URLs de PayPal (si es necesario)

Si necesitas que PayPal llame de vuelta a tu aplicación a través de Ngrok:

1. Obtén la URL pública de Ngrok (ej: `https://abc123.ngrok.io`)
2. Actualiza temporalmente tu `.env`:
   ```env
   PAYPAL_RETURN_URL=https://abc123.ngrok.io/frontend/checkout.php?success=true
   PAYPAL_CANCEL_URL=https://abc123.ngrok.io/frontend/checkout.php?canceled=true
   ```
3. **IMPORTANTE**: Recuerda revertir estos cambios después de las pruebas

## Configuración Avanzada

### Archivo de Configuración de Ngrok

Crea `ngrok.yml` en tu directorio de usuario (`C:\Users\Fernando Acuña\.ngrok2\ngrok.yml`):

```yaml
version: "2"
authtoken: TU_AUTHTOKEN_AQUI
tunnels:
  esfero:
    proto: http
    addr: 443
    hostname: esfero.local
    inspect: true
```

Luego puedes iniciar con:
```powershell
ngrok start esfero
```

### Inspección de Tráfico

Ngrok incluye una interfaz web para inspeccionar el tráfico:
- Visita: http://127.0.0.1:4040
- Verás todas las peticiones HTTP/HTTPS que pasan por el túnel

## Consideraciones de Seguridad

1. **URLs Públicas**: Cualquiera con la URL puede acceder a tu aplicación
2. **Datos Sensibles**: No uses Ngrok con datos de producción reales
3. **Autenticación**: Asegúrate de que tu aplicación tenga autenticación activa
4. **Rate Limiting**: El plan gratuito tiene límites de peticiones

## Solución de Problemas

### Error: "authtoken required"
- Configura tu authtoken: `ngrok config add-authtoken TU_TOKEN`

### Error: "bind: address already in use"
- Otro proceso está usando el puerto. Cierra otras instancias de Ngrok

### La URL no funciona
- Verifica que tu aplicación local esté corriendo
- Verifica que el puerto sea correcto (80 para HTTP, 443 para HTTPS)
- Revisa los logs de Ngrok en http://127.0.0.1:4040

### PayPal no puede hacer callback
- Asegúrate de usar HTTPS en la URL de Ngrok
- Verifica que la URL esté actualizada en tu `.env`
- Revisa que PayPal acepte la URL (algunos servicios bloquean dominios .ngrok.io)

## Alternativas a Ngrok

Si Ngrok no funciona para tus necesidades:

1. **localtunnel**: Alternativa open-source
   ```powershell
   npm install -g localtunnel
   lt --port 443
   ```

2. **serveo**: No requiere instalación
   ```powershell
   ssh -R 80:localhost:443 serveo.net
   ```

3. **Cloudflare Tunnel**: Más robusto pero más complejo

## Recursos Adicionales

- Documentación oficial: https://ngrok.com/docs
- Dashboard: https://dashboard.ngrok.com
- Comunidad: https://ngrok.com/community

