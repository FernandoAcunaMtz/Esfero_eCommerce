# 🔐 Autenticación Local con JWT RS256 + CGI

Este documento describe el nuevo flujo de autenticación **100% local** sin Auth0.  
El backend Python (CGI) emite tokens JWT firmados con **RS256** y el frontend PHP los consume para mantener sesiones con roles dinámicos.

---

## 1. Componentes principales

| Componente | Archivo / Ruta | Descripción |
|------------|----------------|-------------|
| API Login CGI | `backend/cgi-bin/usuarios.py` | Registra e inicia sesión contra MySQL |
| JWT Tools | `backend/cgi-bin/jwt_tools.py` | Genera y valida tokens RS256 |
| Login Web | `frontend/login.php` + `frontend/process_login.php` | Formulario local + bridge CGI |
| Registro Web | `frontend/registro.php` + `frontend/process_registro.php` | Alta de usuarios autoincremental |
| Helper PHP | `frontend/includes/api_helper.php` | Envía peticiones firmadas con token |
| Middleware | `frontend/includes/auth_middleware.php` | Protege rutas (roles/admin) |

---

## 2. Variables de entorno

Actualiza tu `.env` (en el servidor y en local) con las siguientes claves:

```
# API CGI
API_BASE_URL=https://tu-dominio.com/cgi-bin
API_TIMEOUT=30
API_VERIFY_SSL=false   # true si usas un certificado válido

# Base de datos (ya existente)
DB_HOST=localhost
DB_NAME=esfero
DB_USER=fer
DB_PASSWORD=******

# JWT / RSA
JWT_PRIVATE_KEY=/home/fer/Esfero/backend/keys/jwt_private.pem
JWT_PUBLIC_KEY=/home/fer/Esfero/backend/keys/jwt_public.pem
JWT_EXPIRATION_HOURS=24
```

> **Nota:** si no defines rutas personalizadas para las claves, el sistema usará automáticamente `backend/keys/`.

---

## 3. Generar claves RSA (RS256)

Ejecuta estos comandos en tu servidor (Linux):

```bash
mkdir -p backend/keys
cd backend/keys

# Clave privada 2048 bits
openssl genpkey -algorithm RSA -out jwt_private.pem -pkeyopt rsa_keygen_bits:2048

# Clave pública asociada
openssl rsa -in jwt_private.pem -pubout -out jwt_public.pem

# Permisos seguros
chmod 600 jwt_private.pem
chmod 644 jwt_public.pem
```

Si cambias las rutas, actualiza las variables `JWT_PRIVATE_KEY` y `JWT_PUBLIC_KEY`.

---

## 4. Preparar la tabla `usuarios`

El CGI utiliza la columna `password_hash` (preferida) y es compatible con `password`.  
Ejecuta esta sentencia si aún no existe `password_hash`:

```sql
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) AFTER password;

UPDATE usuarios 
SET password_hash = password 
WHERE password_hash IS NULL AND password IS NOT NULL;
```

Todas las nuevas altas se guardan con `SHA-256`. Se recomienda migrar gradualmente a un algoritmo más fuerte (bcrypt) en futuras iteraciones.

---

## 5. Flujo de Login

1. El usuario completa el formulario en `login.php`.
2. `process_login.php` envía las credenciales al endpoint CGI `usuarios.py/login`.
3. El CGI valida contra MySQL y genera un JWT RS256.
4. PHP guarda:
   - `$_SESSION['user']` → datos básicos + rol
   - `$_SESSION['auth_token']` → JWT para llamadas a la API
5. `get_redirect_by_role()` envía al dashboard correcto (`admin`, `vendedor`, `cliente`).

---

## 6. Flujo de Registro

1. `registro.php` envía el formulario a `process_registro.php`.
2. CGI `usuarios.py/register` crea el usuario autoincremental en MySQL.
3. Se retorna el JWT y se inicia sesión automáticamente.

---

## 7. Certificado SSL autofirmado

Para asegurar el dominio (`https://`), genera un certificado autofirmado:

```bash
mkdir -p certs
openssl req -x509 -nodes -days 365 \
  -newkey rsa:2048 \
  -keyout certs/esfero-selfsigned.key \
  -out certs/esfero-selfsigned.crt \
  -subj "/C=MX/ST=Michoacan/L=Morelia/O=Esfero/OU=IT/CN=tu-dominio.com"
```

Configura Apache/Nginx (ejemplo Apache):

```
SSLEngine on
SSLCertificateFile    /home/fer/Esfero/certs/esfero-selfsigned.crt
SSLCertificateKeyFile /home/fer/Esfero/certs/esfero-selfsigned.key

<Directory "/home/fer/Esfero/cgi-bin">
    Options +ExecCGI
    AddHandler cgi-script .py
</Directory>
```

> Como es autofirmado, deberás importar el `.crt` en tu navegador o establecer `API_VERIFY_SSL=false` para que cURL acepte el certificado.

---

## 8. Despliegue con FileZilla / SCP

1. Subir archivos modificados del frontend (`login.php`, `registro.php`, `process_*.php`, `logout.php`, `includes/api_helper.php`, `includes/auth_middleware.php`).
2. Subir `backend/cgi-bin/jwt_tools.py` y `backend/cgi-bin/usuarios.py`.
3. No subir las claves (`backend/keys/*`) al repositorio: ya están en `.gitignore`.
4. Crear/actualizar `.env` directamente en el servidor con las variables de la sección 2.

---

## 9. Verificaciones recomendadas

1. **Conexión MySQL:** `python3 backend/cgi-bin/db.py`.
2. **Claves RSA cargadas:** ejecutar `python3 backend/cgi-bin/jwt_tools.py` con un token válido (opcional).
3. **Registro/Login desde navegador:** probar con cuentas de cada rol.
4. **Token en sesión:** inspeccionar `$_SESSION['auth_token']` y probar llamadas a cualquier endpoint protegido (carrito, productos, admin).
5. **HTTPS:** acceder vía `https://tu-dominio.com/login.php` y confirmar que el candado aparece (aunque sea autofirmado).

---

## 10. Limpieza de Auth0

Los siguientes archivos ya no existen y no deben subirse:

- `frontend/auth0_login.php`
- `frontend/callback.php`
- `frontend/test_config_auth0.php`
- `frontend/debug_auth0.php`
- `frontend/test_auth0_flow.php`
- Documentación heredada de Auth0

El sistema completo opera con autenticación local; cualquier referencia previa a Auth0 es únicamente histórica.

---

## 11. Próximos pasos sugeridos

- Migrar hashes de contraseña a bcrypt/Argon2.
- Implementar recuperación de contraseña via correo.
- Integrar MFA opcional (OTP) después del login local.
- Reemplazar el certificado autofirmado por uno emitido (Let's Encrypt) cuando se tenga el dominio definitivo.

---

¿Dudas o incidentes? Documenta el error y revisa `backend/cgi-bin/error.log` (según configuración del servidor) o habilita `APP_DEBUG=true` temporalmente para obtener más trazas.


