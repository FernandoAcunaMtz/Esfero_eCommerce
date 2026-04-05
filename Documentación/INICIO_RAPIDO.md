# 🚀 Guía de Inicio Rápido – Esfero Marketplace

> ⚠️ **Nota (2025-11):** Este documento conserva la configuración histórica con Auth0.  
> La autenticación actual es 100% local (CGI + JWT RS256). Consulta `AUTENTICACION_LOCAL_RS256.md`.
# ⚡ INICIO RÁPIDO - ESFERO MARKETPLACE

## 🎯 Guía de 5 Minutos para Poner en Marcha tu Sistema

---

## ✅ Requisitos Previos

Asegúrate de tener instalado:

- ✅ **XAMPP** (o Apache + PHP + MySQL)
- ✅ **Python 3.7+**
- ✅ **MySQL Server**
- ✅ **Navegador Web** (Chrome, Firefox, Edge)

---

## 🚀 PASO 1: Instalar Dependencias Python

### **Windows:**
```cmd
# Opción A: Doble clic en el instalador
install_python_dependencies.bat

# Opción B: Desde CMD
cd C:\Users\Fernando Acuña\OneDrive\Escritorio\Esfero
install_python_dependencies.bat
```

### **Linux/Mac:**
```bash
# Dar permisos de ejecución
chmod +x install_python_dependencies.sh

# Ejecutar instalador
./install_python_dependencies.sh
```

### **Manual (si los scripts no funcionan):**
```bash
pip install mysql-connector-python PyJWT requests python-dotenv cryptography
```

---

## 🗄️ PASO 2: Crear Base de Datos

### **Opción A: Desde Línea de Comandos (Recomendado)**

```bash
# Abrir terminal/CMD en la carpeta del proyecto
cd C:\Users\Fernando Acuña\OneDrive\Escritorio\Esfero

# Ejecutar script SQL
mysql -u root -p < sql/SCHEMA_FINAL_COMPLETO.sql

# Te pedirá tu contraseña de MySQL
```

### **Opción B: Desde phpMyAdmin**

1. Abrir: `http://localhost/phpmyadmin`
2. Crear base de datos `esfero` (si no existe)
3. Seleccionar base de datos `esfero`
4. Ir a pestaña **"Importar"**
5. Seleccionar archivo: `sql/SCHEMA_FINAL_COMPLETO.sql`
6. Clic en **"Continuar"**
7. ✅ Esperar que termine (tarda ~10 segundos)

### **Opción C: Desde MySQL Workbench**

1. Abrir MySQL Workbench
2. Conectar a tu servidor local
3. File → Run SQL Script
4. Seleccionar: `sql/SCHEMA_FINAL_COMPLETO.sql`
5. Ejecutar

---

## ⚙️ PASO 3: Configurar Credenciales

### **1. Crear/Verificar archivo .env**

**Opción A: Si YA tienes un archivo `.env` con todos los datos correctos**
- ✅ **NO necesitas hacer nada**, puedes saltar al **Paso 4** (Verificar Instalación)

**Opción B: Si tienes un archivo `.env` pero necesitas verificar/editarlo**
- Ve al **Paso 2** para revisarlo y editarlo si es necesario

**Opción C: Si NO tienes un archivo `.env`**
- Crea uno copiando desde el ejemplo:

```bash
# Windows
copy env.example.txt .env

# Linux/Mac (Ubuntu)
cp env.example.txt .env
```

### **2. Editar archivo .env** *(Solo si necesitas configurarlo o cambiarlo)*

Abre el archivo `.env` con un editor de texto y configura:

```env
# MYSQL (LO MÁS IMPORTANTE)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=esfero
DB_USER=root
DB_PASSWORD=TU_PASSWORD_AQUI    # ⚠️ CAMBIAR ESTO

# JWT / AUTENTICACIÓN LOCAL
JWT_PRIVATE_KEY=/ruta/a/jwt_private.pem
JWT_PUBLIC_KEY=/ruta/a/jwt_public.pem
JWT_EXPIRATION_HOURS=24

# PayPal (Opcional por ahora)
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=tu_paypal_client_id
PAYPAL_CLIENT_SECRET=tu_paypal_secret
```

**⚠️ IMPORTANTE:** Cambia `DB_PASSWORD` por tu contraseña real de MySQL.

---

## ✅ PASO 4: Verificar Instalación

### **Abrir en tu navegador:**

```
http://localhost/verificar_instalacion.php
```

Deberías ver:

✅ **Checks Exitosos** (todo en verde)  
📊 **16 tablas** en la base de datos  
👥 **5 usuarios** de prueba  
🛍️ **5 productos** de ejemplo  

Si ves errores, la página te dirá exactamente qué falta.

---

## 🎮 PASO 5: ¡Probar el Sistema!

### **1. Ir al Login:**

```
http://localhost/frontend/login.php
```

### **2. Usar Credenciales de Prueba:**

#### **👑 Como Administrador:**
```
Email: admin@esfero.com
Password: password123
```
Te redirige a: `/admin_dashboard.php`

#### **🏪 Como Vendedor:**
```
Email: vendedor@esfero.com
Password: password123
```
Te redirige a: `/vendedor_dashboard.php`

#### **🛒 Como Cliente:**
```
Email: carlos.mendez@example.com
Password: password123
```
Te redirige a: `/catalogo.php`

---

## 📋 CHECKLIST DE VERIFICACIÓN

Marca lo que ya hiciste:

- [ ] Python instalado y funcionando
- [ ] Dependencias Python instaladas
- [ ] MySQL corriendo
- [ ] Base de datos `esfero` creada
- [ ] 16 tablas en la base de datos
- [ ] Archivo `.env` creado y configurado
- [ ] Página de verificación muestra todo en verde
- [ ] Login funcional con usuarios de prueba

---

## 🎯 PRÓXIMOS PASOS

Una vez que todo funcione:

### **1. Explorar el Sistema**

- 🏪 **Como Vendedor:** Publicar un producto
- 🛒 **Como Cliente:** Agregar al carrito, comprar
- 👑 **Como Admin:** Ver estadísticas, gestionar usuarios

### **2. Personalizar**

- Cambiar colores en `frontend/assets/css/styles.css`
- Personalizar logos e imágenes
- Modificar textos

### **3. Configurar Auth0 (Opcional)**

Sigue la guía en: `GUIA_CONEXION_AUTH0.txt`

### **4. Configurar PayPal (Opcional)**

Sigue la guía en: `CONFIGURACION_PAYPAL_PASO_A_PASO.txt`

---

## 🐛 SOLUCIÓN DE PROBLEMAS COMUNES

### **❌ Error: "Access denied for user"**
**Solución:** Verifica tu contraseña de MySQL en `.env`

```env
DB_PASSWORD=tu_password_correcto
```

### **❌ Error: "Table 'esfero.usuarios' doesn't exist"**
**Solución:** No se ejecutó el SQL correctamente

```bash
# Volver a ejecutar
mysql -u root -p < sql/SCHEMA_FINAL_COMPLETO.sql
```

### **❌ Error: "No module named 'mysql.connector'"**
**Solución:** Instalar dependencias Python

```bash
pip install mysql-connector-python
```

### **❌ Error: "Call to undefined function mysqli_connect()"**
**Solución:** Habilitar extensión mysqli en PHP

1. Abrir: `C:\xampp\php\php.ini`
2. Buscar: `;extension=mysqli`
3. Quitar el `;` para descomentar: `extension=mysqli`
4. Reiniciar Apache

### **❌ Error 404 al abrir páginas**
**Solución:** Verificar que Apache esté corriendo

```bash
# En XAMPP: Iniciar Apache
# En línea de comandos:
sudo service apache2 start  # Linux
apachectl start             # Mac
```

---

## 📞 ¿NECESITAS AYUDA?

### **1. Script de Verificación**
```
http://localhost/verificar_instalacion.php
```

### **2. Documentación Completa**
```
GUIA_IMPLEMENTACION_FINAL.md
```

### **3. Resumen Ejecutivo**
```
RESUMEN_EJECUTIVO_FINAL.md
```

### **4. Ver Logs**

#### **PHP Errors:**
```
C:\xampp\apache\logs\error.log
```

#### **MySQL Errors:**
```
C:\xampp\mysql\data\mysql_error.log
```

---

## 🎉 ¡LISTO!

Si completaste todos los pasos, tu sistema **Esfero Marketplace** está funcionando al 100%.

### **URLs Importantes:**

| Página | URL |
|--------|-----|
| Verificación | `http://localhost/verificar_instalacion.php` |
| Login | `http://localhost/frontend/login.php` |
| Catálogo | `http://localhost/frontend/catalogo.php` |
| Admin | `http://localhost/frontend/admin_dashboard.php` |
| Vendedor | `http://localhost/frontend/vendedor_dashboard.php` |

---

**¡Disfruta tu marketplace! 🚀**

---

## 📚 Archivos de Referencia Rápida

- **Base de Datos:** `sql/SCHEMA_FINAL_COMPLETO.sql`
- **API Helper:** `frontend/includes/api_helper.php`
- **Auth Middleware:** `frontend/includes/auth_middleware.php`
- **Configuración:** `env.example.txt` → copiar a `.env`

---

**Tiempo estimado de configuración:** ⏱️ 5-10 minutos

**Nivel de dificultad:** 🟢 Fácil (solo seguir los pasos)


