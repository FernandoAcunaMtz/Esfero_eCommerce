# Esfero Marketplace

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php&logoColor=white)
![Python](https://img.shields.io/badge/Python-3.10+-3776AB?style=flat-square&logo=python&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker&logoColor=white)
![PayPal](https://img.shields.io/badge/PayPal-Sandbox-003087?style=flat-square&logo=paypal&logoColor=white)
![JWT](https://img.shields.io/badge/JWT-RS256-000000?style=flat-square&logo=jsonwebtokens&logoColor=white)

Marketplace C2C (consumer-to-consumer) desarrollado desde cero, donde compradores y vendedores pueden publicar, buscar y adquirir productos con pagos integrados vía PayPal. El entorno completo corre en Docker con un solo comando.

---

## Características

### Para compradores
- Catálogo con búsqueda, filtros por categoría, precio y ubicación
- Skeleton loaders y paginación AJAX sin recarga de página
- Carrito de compras persistente
- Checkout con pago real a través de PayPal Sandbox
- Historial de compras y seguimiento de órdenes
- Mensajería interna con vendedores
- Sistema de favoritos

### Para vendedores
- Registro y gestión de productos con galería de imágenes
- Dashboard con estadísticas de ventas
- Gestión de órdenes recibidas
- Sistema de reseñas y calificaciones con posibilidad de respuesta

### Para administradores
- Panel completo con estadísticas globales
- Gestión de usuarios, productos y reportes
- Control de estados y verificación de cuentas

### Seguridad
- Autenticación con JWT RS256 (clave privada/pública RSA)
- Contraseñas hasheadas con **bcrypt**
- Protección CSRF en todos los formularios POST
- Rate limiting en endpoint de login (10 intentos / 5 min)
- Consultas parametrizadas en toda la capa de datos (sin SQL injection)
- Output encoding con `htmlspecialchars()` en toda la vista

---

## Stack Técnico

| Capa | Tecnología |
|------|-----------|
| Frontend / Vistas | PHP 8+ con Bootstrap 5 |
| Backend / API | Python 3 CGI (REST) |
| Base de datos | MySQL 8 — 16 tablas, stored procedures |
| Autenticación | JWT RS256 local |
| Pagos | PayPal REST API v2 (Sandbox) |
| Seguridad | bcrypt, CSRF tokens, rate limiting |

---

## Instalación

### Opción A — Docker (recomendada)

El único requisito es tener [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado.

```bash
# 1. Clonar el repositorio
git clone https://github.com/FernandoAcunaMtz/Esfero_eCommerce.git
cd Esfero_eCommerce

# 2. Copiar variables de entorno
cp .env.example .env

# 3. Levantar los contenedores (MySQL + Apache/PHP/Python)
docker compose up -d

# 4. Poblar la base de datos con productos de demostración (194 productos, 7 vendedores)
docker exec esfero_app php /var/www/esfero/scripts/seed_productos.php
```

La aplicación queda disponible en `http://localhost:8080`

### Credenciales de demostración

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@esfero.com | Admin2024! |
| Vendedor demo | lucia@demo.esfero | Esfero2024! |
| Comprador | Crear cuenta nueva desde el registro | — |

---

### Opción B — Instalación manual

#### Requisitos
- Apache con `mod_cgi` habilitado
- PHP 8.0+
- Python 3.10+ con pip
- MySQL 8.0+

```bash
# Instalar dependencias Python
pip install PyJWT bcrypt requests

# Configurar variables de entorno
cp .env.example .env

# Generar claves RSA para JWT
mkdir -p backend/keys
openssl genrsa -out backend/keys/jwt_private.pem 2048
openssl rsa -in backend/keys/jwt_private.pem -pubout -out backend/keys/jwt_public.pem

# Importar esquema y stored procedures
mysql -u root -p < sql/schema.sql
mysql -u root -p esfero < sql/stored_procedures/00_instalar_todos_los_stored_procedures.sql
```

Habilitar CGI en Apache:

```apache
<Directory "/var/www/html/esfero/backend/cgi-bin">
    Options +ExecCGI
    AddHandler cgi-script .py
</Directory>
```

Ver [.env.example](.env.example) para la lista completa de variables.

---

## Estructura del proyecto

```
esfero/
├── frontend/               # Vistas PHP + assets
│   ├── includes/           # Helpers: api_helper, auth_middleware, csrf
│   ├── components/         # navbar, sidebars
│   └── assets/             # CSS, JS, imágenes
├── backend/
│   └── cgi-bin/            # APIs Python: usuarios, productos, carrito,
│                           # ordenes, paypal, admin, calificaciones
├── sql/
│   ├── schema.sql           # Esquema de 16 tablas
│   └── stored_procedures/  # 6 stored procedures para operaciones críticas
├── config/                 # Configuración PayPal
├── .env.example            # Plantilla de variables de entorno
└── Documentación/          # Guías técnicas detalladas
```

---

## Decisiones técnicas destacadas

### Arquitectura PHP + Python CGI
Se optó por separar completamente la capa de presentación (PHP) de la lógica de negocio (Python), exponiendo la lógica como una API REST interna. Esto permite que el frontend sea agnóstico al motor de backend — el `api_helper.php` abstrae todas las llamadas HTTP con fallback automático entre cURL y `file_get_contents`. Esta separación facilita migrar el backend a FastAPI o Django sin tocar las vistas.

### JWT RS256 con clave asimétrica
A diferencia de HMAC-SHA256 (donde el mismo secreto firma y verifica), RS256 permite distribuir la clave pública para verificación sin exponer la clave privada. Las claves RSA se generan localmente y nunca se versionan en el repositorio. El par de claves vive en `backend/keys/` que está en `.gitignore`.

### Seguridad de contraseñas con bcrypt
Se migró de SHA-256 (determinístico, sin salt, vulnerable a rainbow tables) a bcrypt, que incorpora salt automático y un factor de costo configurable. El login verifica con `bcrypt.checkpw()` en lugar de comparar hashes directamente.

---

## Autor

**Fernando Acuña**  
[GitHub](https://github.com/FernandoAcunaMtz) · [LinkedIn](https://linkedin.com/in/fernandoacunamtz)

---

## Licencia

MIT — consulta el archivo [LICENSE](LICENSE) para más detalles.
