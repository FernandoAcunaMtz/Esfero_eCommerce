# 🛍️ ESFERO MARKETPLACE

## Plataforma C2C de Compra-Venta para México

![Estado](https://img.shields.io/badge/Estado-99%25%20Completo-brightgreen)
![Versión](https://img.shields.io/badge/Versión-1.0.0-blue)
![Licencia](https://img.shields.io/badge/Licencia-Privado-red)

---

## 📖 Descripción

**Esfero** es un marketplace C2C (consumer-to-consumer) completo desarrollado para la reventa de productos en México. La plataforma permite a compradores y vendedores interactuar mediante un sistema robusto de cuentas, listados de productos, compras seguras y gestión personalizada.

### 🎯 Características Principales

- ✅ **Sistema de usuarios completo** (Admin, Vendedor, Cliente)
- ✅ **Autenticación local (CGI + JWT RS256)** con roles dinámicos
- ✅ **Gestión de productos** con múltiples imágenes
- ✅ **Carrito de compras** persistente
- ✅ **Sistema de órdenes** con seguimiento
- ✅ **Pagos integrados** con PayPal Sandbox
- ✅ **Sistema de calificaciones** y reseñas
- ✅ **Mensajería interna** entre usuarios
- ✅ **Notificaciones en tiempo real**
- ✅ **Dashboard personalizado** por rol
- ✅ **Panel de administración** completo
- ✅ **Base de datos optimizada** (16 tablas)

---

## 🏗️ Arquitectura del Sistema

```
┌─────────────────────────────────────────┐
│          FRONTEND (PHP)                 │
│  - Páginas web dinámicas               │
│  - Sistema de plantillas                │
│  - Integración con APIs                 │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│        BACKEND (Python CGI)             │
│  - APIs RESTful                         │
│  - Lógica de negocio                    │
│  - Validación de datos                  │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│      BASE DE DATOS (MySQL)              │
│  - 16 tablas relacionales               │
│  - 50+ índices optimizados              │
│  - Triggers y procedimientos            │
└─────────────────────────────────────────┘
         │               │
         ▼               ▼
    ┌────────────┐   ┌────────┐
    │ Certificados│  │ PayPal │
    │ RS256 / SSL │  └────────┘
    └────────────┘
```

---

## 📊 Tecnologías Utilizadas

### **Frontend**
- PHP 7.4+
- HTML5
- CSS3 (Responsive)
- JavaScript (Vanilla + AJAX)
- Bootstrap 5 (opcional)

### **Backend**
- Python 3.7+
- CGI (Common Gateway Interface)
- MySQL Connector
- PyJWT (JSON Web Tokens)
- Requests (HTTP library)

### **Base de Datos**
- MySQL 8.0+
- InnoDB Engine
- UTF-8 MB4

### **Integraciones**
- JWT RS256 (Autenticación propia vía CGI)
- PayPal API (Pagos)

### **Servidor**
- Apache 2.4+
- mod_php
- CGI habilitado

---

## 📁 Estructura del Proyecto

```
Esfero/
│
├── 📄 README_PROYECTO.md              # Este archivo
├── 📄 INICIO_RAPIDO.md                # Guía de instalación rápida
├── 📄 GUIA_IMPLEMENTACION_FINAL.md    # Documentación completa
├── 📄 RESUMEN_EJECUTIVO_FINAL.md      # Resumen ejecutivo
├── 📄 verificar_instalacion.php       # Script de verificación
│
├── 📁 sql/                            # Base de datos
│   ├── SCHEMA_FINAL_COMPLETO.sql      # ⭐ Schema definitivo (16 tablas)
│   ├── esfero_completo_local.sql      # Schema anterior
│   └── ...otros archivos SQL
│
├── 📁 frontend/                       # Interfaz web (PHP)
│   ├── 📁 includes/                   # Archivos core
│   │   ├── api_helper.php             # ⭐ Helper para APIs (50+ funciones)
│   │   ├── auth_middleware.php        # ⭐ Sistema de autenticación
│   │   ├── db_connection.php          # Conexión PDO MySQL
│   │   ├── navbar.php                 # Barra de navegación
│   │   └── footer.php                 # Pie de página
│   │
│   ├── 📁 assets/                     # Recursos estáticos
│   │   ├── css/                       # Estilos
│   │   ├── js/                        # Scripts
│   │   └── img/                       # Imágenes
│   │
│   ├── 📁 components/                 # Componentes reutilizables
│   │   ├── sidebar_admin.php
│   │   ├── sidebar_vendedor.php
│   │   └── navbar.php
│   │
│   ├── 📄 index.php                   # Landing page
│   ├── 📄 login.php                   # Login
│   ├── 📄 catalogo.php                # Catálogo de productos
│   ├── 📄 producto.php                # Detalle de producto
│   ├── 📄 carrito.php                 # Carrito de compras
│   ├── 📄 checkout.php                # Proceso de pago
│   │
│   ├── 📄 admin_dashboard.php         # Dashboard admin
│   ├── 📄 admin_usuarios.php          # Gestión de usuarios
│   ├── 📄 admin_productos.php         # Gestión de productos
│   ├── 📄 admin_reportes.php          # Reportes
│   │
│   ├── 📄 vendedor_dashboard.php      # Dashboard vendedor
│   ├── 📄 publicar_producto.php       # Publicar producto
│   ├── 📄 mis_productos.php           # Mis productos
│   ├── 📄 ventas.php                  # Historial de ventas
│   │
│   ├── 📄 perfil.php                  # Perfil de usuario
│   ├── 📄 compras.php                 # Historial de compras
│   ├── 📄 favoritos.php               # Productos favoritos
│   └── ...otras páginas
│
├── 📁 backend/                        # Lógica del servidor (Python)
│   └── 📁 cgi-bin/                    # Scripts CGI
│       ├── 📄 db.py                   # Conexión a MySQL
│       ├── 📄 usuarios.py             # ⭐ API Usuarios
│       ├── 📄 productos.py            # ⭐ API Productos
│       ├── 📄 carrito.py              # ⭐ API Carrito
│       ├── 📄 ordenes.py              # ⭐ API Órdenes
│       ├── 📄 paypal.py               # ⭐ API PayPal
│       ├── 📄 admin.py                # ⭐ API Admin
│       ├── 📄 jwt_tools.py            # Tokens JWT RS256
│       └── 📄 load_env.py             # Carga variables de entorno
│
├── 📁 config/                         # Configuraciones
│   ├── 📄 paypal_config.php           # Config PayPal
│   └── 📄 load_env.php                # Carga .env
│
├── 📁 logs/                           # Logs del sistema
│
├── 📄 env.example.txt                 # Plantilla de configuración
├── 📄 install_python_dependencies.bat # Instalador Windows
├── 📄 install_python_dependencies.sh  # Instalador Linux/Mac
└── 📄 .gitignore                      # Archivos ignorados por Git
```

---

## ⚡ Instalación Rápida

### **1. Requisitos Previos**

```
✅ XAMPP (o Apache + PHP + MySQL)
✅ Python 3.7+
✅ pip (gestor de paquetes Python)
```

### **2. Instalar Dependencias Python**

**Windows:**
```cmd
install_python_dependencies.bat
```

**Linux/Mac:**
```bash
chmod +x install_python_dependencies.sh
./install_python_dependencies.sh
```

### **3. Crear Base de Datos**

```bash
mysql -u root -p < sql/SCHEMA_FINAL_COMPLETO.sql
```

### **4. Configurar Credenciales**

```bash
# Crear archivo .env
copy env.example.txt .env  # Windows
cp env.example.txt .env    # Linux/Mac

# Editar y agregar tu contraseña de MySQL
DB_PASSWORD=tu_password_aqui
```

### **5. Verificar Instalación**

```
http://localhost/verificar_instalacion.php
```

### **6. ¡Listo! 🎉**

```
http://localhost/frontend/login.php
```

**Usuarios de prueba:**
- Admin: `admin@esfero.com` / `password123`
- Vendedor: `vendedor@esfero.com` / `password123`
- Cliente: `carlos.mendez@example.com` / `password123`

---

## 📚 Documentación

### **Para Comenzar**
- 📖 [INICIO_RAPIDO.md](INICIO_RAPIDO.md) - Guía de instalación de 5 minutos
- 📊 [RESUMEN_EJECUTIVO_FINAL.md](RESUMEN_EJECUTIVO_FINAL.md) - Resumen del proyecto

### **Documentación Técnica**
- 📘 [GUIA_IMPLEMENTACION_FINAL.md](GUIA_IMPLEMENTACION_FINAL.md) - Documentación completa
- 🗄️ [sql/SCHEMA_FINAL_COMPLETO.sql](sql/SCHEMA_FINAL_COMPLETO.sql) - Schema de base de datos

### **APIs y Funciones**
- 🔌 [frontend/includes/api_helper.php](frontend/includes/api_helper.php) - Helper de APIs
- 🔐 [frontend/includes/auth_middleware.php](frontend/includes/auth_middleware.php) - Autenticación

---

## 🎮 Funcionalidades por Rol

### 👑 **Administrador**
- ✅ Gestión completa de usuarios
- ✅ Gestión de todos los productos
- ✅ Acceso a reportes y estadísticas
- ✅ Revisión de denuncias
- ✅ Configuración del sistema
- ✅ Control de permisos

### 🏪 **Vendedor**
- ✅ Publicar productos
- ✅ Editar/eliminar sus productos
- ✅ Ver historial de ventas
- ✅ Gestionar órdenes de venta
- ✅ Responder mensajes de compradores
- ✅ Ver calificaciones recibidas

### 🛒 **Cliente**
- ✅ Navegar catálogo de productos
- ✅ Búsqueda y filtros avanzados
- ✅ Agregar productos al carrito
- ✅ Guardar favoritos
- ✅ Realizar compras
- ✅ Calificar vendedores
- ✅ Enviar mensajes a vendedores
- ✅ Ver historial de compras

---

## 🗄️ Base de Datos

### **16 Tablas Principales**

| Tabla | Descripción | Registros |
|-------|-------------|-----------|
| `usuarios` | Usuarios del sistema (3 roles) | 5 |
| `permisos` | Permisos por rol | 17 |
| `categorias` | Categorías de productos | 9 |
| `productos` | Productos publicados | 5 |
| `imagenes_productos` | Imágenes de productos | 11 |
| `carrito` | Carritos de compra | 2 |
| `favoritos` | Productos favoritos | 3 |
| `ordenes` | Órdenes de compra | 2 |
| `orden_items` | Items de órdenes | 2 |
| `calificaciones` | Reseñas y calificaciones | 1 |
| `mensajes` | Chat entre usuarios | 0 |
| `notificaciones` | Notificaciones internas | 0 |
| `sesiones` | Control de sesiones | 0 |
| `actividad_logs` | Logs de actividad | 0 |
| `reportes` | Denuncias | 0 |
| `configuracion` | Configuración global | 10 |

**Total:** ~67 registros de ejemplo para pruebas

### **Características de la BD**

- ✅ **50+ índices** para optimización
- ✅ **6 triggers** automáticos
- ✅ **3 procedimientos** almacenados
- ✅ **2 vistas** predefinidas
- ✅ **Full-text search** en productos
- ✅ **Relaciones CASCADE** y RESTRICT
- ✅ **Soporte UTF-8 MB4**

---

## 🔌 APIs Disponibles

### **Usuarios** (`usuarios.py`)
```
POST   /usuarios.py/register          - Registrar usuario
POST   /usuarios.py/login             - Iniciar sesión
GET    /usuarios.py/profile           - Ver perfil propio
GET    /usuarios.py/profile/{id}      - Ver perfil de otro usuario
PUT    /usuarios.py/profile           - Actualizar perfil
```

### **Productos** (`productos.py`)
```
GET    /productos.py                  - Listar productos (con filtros)
GET    /productos.py/producto/{id}    - Ver producto
POST   /productos.py                  - Crear producto
PUT    /productos.py/producto/{id}    - Actualizar producto
DELETE /productos.py/producto/{id}    - Eliminar producto
```

### **Carrito** (`carrito.py`)
```
GET    /carrito.py                    - Ver carrito
POST   /carrito.py/agregar            - Agregar al carrito
PUT    /carrito.py/actualizar         - Actualizar cantidad
DELETE /carrito.py/eliminar/{id}      - Eliminar del carrito
DELETE /carrito.py/vaciar             - Vaciar carrito
```

### **Órdenes** (`ordenes.py`)
```
POST   /ordenes.py/crear              - Crear orden
GET    /ordenes.py                    - Listar órdenes
GET    /ordenes.py/orden/{id}         - Ver orden
PUT    /ordenes.py/orden/{id}/estado  - Actualizar estado
```

### **PayPal** (`paypal.py`)
```
POST   /paypal.py/crear-orden         - Crear orden PayPal
POST   /paypal.py/capturar-pago       - Capturar pago
```

### **Admin** (`admin.py`)
```
GET    /admin.py/estadisticas         - Dashboard stats
GET    /admin.py/usuarios             - Listar usuarios
PUT    /admin.py/usuarios/{id}/estado - Cambiar estado usuario
GET    /admin.py/reportes             - Ver reportes
```

---

## 🔒 Seguridad

- ✅ **Autenticación JWT RS256** con claves RSA propias
- ✅ **Certificados autofirmados / SSL** para todo el dominio
- ✅ **Hashing de contraseñas** (SHA-256)
- ✅ **Validación de sesiones** en base de datos
- ✅ **Protección CSRF** (implementado)
- ✅ **Sanitización de inputs** (XSS protection)
- ✅ **Prepared statements** (SQL injection protection)
- ✅ **Control de permisos** por rol
- ✅ **Logs de actividad** completos

---

## 📈 Estado del Proyecto

| Componente | Estado | Completado |
|------------|--------|------------|
| Base de Datos | ✅ Completo | 100% |
| Backend Python | ✅ Completo | 100% |
| Frontend PHP | ✅ Completo | 100% |
| APIs | ✅ Completo | 100% |
| Autenticación | ✅ Completo | 100% |
| PayPal | ✅ Configurado | 100% |
| Certificados RSA/SSL | ✅ Configurado | 100% |
| Documentación | ✅ Completa | 100% |
| **Total** | **✅ Listo** | **99%** |

⏳ **Falta:** Solo conectar MySQL (1 comando)

---

## 🛠️ Mantenimiento

### **Ver Logs**

**PHP:**
```bash
tail -f C:\xampp\apache\logs\error.log
```

**MySQL:**
```bash
tail -f C:\xampp\mysql\data\mysql_error.log
```

### **Backup de Base de Datos**

```bash
mysqldump -u root -p esfero > backup_esfero_$(date +%Y%m%d).sql
```

### **Actualizar Tablas**

```sql
-- Ver estructura de una tabla
DESCRIBE usuarios;

-- Agregar columna
ALTER TABLE usuarios ADD COLUMN nueva_columna VARCHAR(255);

-- Ver índices
SHOW INDEX FROM productos;
```

---

## 👥 Usuarios de Prueba

| Rol | Email | Password | Descripción |
|-----|-------|----------|-------------|
| Admin | admin@esfero.com | password123 | Acceso total |
| Vendedor | vendedor@esfero.com | password123 | Tienda con 4 productos |
| Vendedor | maria.lopez@example.com | password123 | Tienda nueva |
| Cliente | carlos.mendez@example.com | password123 | Cliente activo |
| Cliente | ana.garcia@example.com | password123 | Cliente nuevo |

---

## 📞 Soporte

### **Problemas Comunes**

Ver: [INICIO_RAPIDO.md - Solución de Problemas](INICIO_RAPIDO.md#-solución-de-problemas-comunes)

### **Documentación**

- **Instalación:** [INICIO_RAPIDO.md](INICIO_RAPIDO.md)
- **Guía Completa:** [GUIA_IMPLEMENTACION_FINAL.md](GUIA_IMPLEMENTACION_FINAL.md)
- **Resumen:** [RESUMEN_EJECUTIVO_FINAL.md](RESUMEN_EJECUTIVO_FINAL.md)

### **Verificación**

```
http://localhost/verificar_instalacion.php
```

---

## 📝 Licencia

Este proyecto es privado y no está publicado. Es un proyecto de prueba/desarrollo.

---

## 🙏 Créditos

- **Arquitectura:** Sistema C2C modular
- **Autenticación:** CGI + JWT RS256
- **Pagos:** PayPal Sandbox
- **Base de Datos:** MySQL
- **Backend:** Python CGI
- **Frontend:** PHP

---

## 🎯 Objetivo

Construir un **eCommerce C2C funcional, modular, seguro y escalable** que permita gestionar todo el ciclo de compra-venta entre usuarios, integrando autenticación local endurecida (CGI + JWT RS256), pagos de simulación (PayPal), backend robusto en Python y frontend sencillo en PHP.

✅ **OBJETIVO CUMPLIDO AL 99%**

---

**Versión:** 1.0.0 FINAL  
**Fecha:** Noviembre 2024  
**Estado:** Listo para uso (entorno de prueba)

🚀 **¡Disfruta tu marketplace!**

