# Stored Procedures - Esfero Marketplace

Este directorio contiene los stored procedures utilizados en el sistema Esfero Marketplace.

## 📋 Stored Procedures Disponibles

### 1. `crear_orden_desde_carrito`
**Archivo:** `01_crear_orden_desde_carrito.sql`

Crea una orden de compra desde el carrito del usuario.

**Parámetros de entrada:**
- `p_comprador_id` (INT): ID del usuario comprador
- `p_vendedor_id` (INT): ID del vendedor
- `p_direccion_envio` (TEXT): Dirección de envío
- `p_ciudad_envio` (VARCHAR): Ciudad de envío
- `p_estado_envio` (VARCHAR): Estado de envío
- `p_codigo_postal_envio` (VARCHAR): Código postal
- `p_telefono_envio` (VARCHAR): Teléfono de contacto

**Parámetros de salida:**
- `p_orden_id` (INT): ID de la orden creada
- `p_numero_orden` (VARCHAR): Número de orden generado (formato: ESF-YYYY-XXXXXX)

**Funcionalidad:**
- Calcula el subtotal del carrito
- Añade costo de envío (100.00 MXN por defecto)
- Genera número de orden único
- Crea la orden en la tabla `ordenes`
- Copia items del carrito a `orden_items`
- Limpia el carrito del usuario

---

### 2. `marcar_producto_vendido`
**Archivo:** `02_marcar_producto_vendido.sql`

Marca un producto como vendido y actualiza todas las métricas relacionadas.

**Parámetros de entrada:**
- `p_producto_id` (INT): ID del producto a marcar como vendido
- `p_orden_id` (INT): ID de la orden asociada (para referencia)

**Funcionalidad:**
- Marca el producto como vendido (`vendido = TRUE`)
- Desactiva el producto (`activo = FALSE`)
- Establece stock en 0
- Registra fecha de venta
- Incrementa contador de ventas del producto (`ventas_count`)
- Incrementa contador de ventas del vendedor (`total_ventas`)

---

### 3. `actualizar_calificacion_vendedor`
**Archivo:** `03_actualizar_calificacion_vendedor.sql`

Recalcula y actualiza la calificación promedio de un vendedor.

**Parámetros de entrada:**
- `p_vendedor_id` (INT): ID del vendedor a actualizar

**Funcionalidad:**
- Calcula el promedio de todas las calificaciones del vendedor
- Solo cuenta calificaciones aprobadas y visibles
- Actualiza el campo `calificacion_vendedor` en la tabla `usuarios`

---

## 🚀 Instalación

### Opción 1: Instalar todos los stored procedures de una vez

```bash
mysql -u tu_usuario -p esfero < sql/stored_procedures/00_instalar_todos_los_stored_procedures.sql
```

### Opción 2: Instalar cada stored procedure individualmente

```bash
# 1. Crear orden desde carrito
mysql -u tu_usuario -p esfero < sql/stored_procedures/01_crear_orden_desde_carrito.sql

# 2. Marcar producto como vendido
mysql -u tu_usuario -p esfero < sql/stored_procedures/02_marcar_producto_vendido.sql

# 3. Actualizar calificación de vendedor
mysql -u tu_usuario -p esfero < sql/stored_procedures/03_actualizar_calificacion_vendedor.sql
```

### Opción 3: Desde MySQL Workbench o cliente SQL

1. Abre el archivo SQL deseado
2. Ejecuta todo el contenido
3. Verifica que el stored procedure se haya creado correctamente

---

## ✅ Verificación

Para verificar que los stored procedures están instalados:

```sql
USE esfero;

SELECT 
    ROUTINE_NAME as 'Stored Procedure',
    ROUTINE_TYPE as 'Tipo',
    CREATED as 'Creado'
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = 'esfero'
AND ROUTINE_TYPE = 'PROCEDURE'
ORDER BY ROUTINE_NAME;
```

---

## 📝 Uso desde Python

Los stored procedures están integrados en el código Python:

- **crear_orden_desde_carrito**: Usado en `backend/cgi-bin/ordenes.py` en la función `create_order_from_cart()`
- **marcar_producto_vendido**: Usado en `backend/cgi-bin/ordenes.py` en la función `update_order_paypal()`
- **actualizar_calificacion_vendedor**: Usado en `backend/cgi-bin/calificaciones.py` al crear una calificación

---

## 🔄 Reinstalación

Si necesitas reinstalar un stored procedure (por ejemplo, después de modificarlo):

1. El archivo SQL ya incluye `DROP PROCEDURE IF EXISTS` al inicio
2. Simplemente ejecuta el archivo nuevamente
3. El stored procedure se eliminará y recreará automáticamente

---

## 📚 Notas Importantes

- Todos los stored procedures requieren que la base de datos `esfero` exista
- Asegúrate de tener los permisos necesarios para crear stored procedures
- Los stored procedures están definidos en `SCHEMA_FINAL_COMPLETO.sql` pero estos archivos individuales facilitan su instalación y mantenimiento

