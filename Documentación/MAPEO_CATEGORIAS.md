# Mapeo de IDs de Categorías - Esfero

## 📋 Tabla de Referencia

| ID | Nombre | Slug | Descripción |
|---|---|---|---|
| **1** | **Electrónica** | `electronica` | Dispositivos electrónicos, computadoras, celulares, cámaras, audífonos, etc. |
| **2** | **Hogar y Jardín** | `hogar-jardin` | Muebles, decoración, electrodomésticos, artículos para el hogar |
| **3** | **Ropa y Accesorios** | `ropa-accesorios` | Ropa, calzado, bolsas, accesorios de moda |
| **4** | **Deportes** | `deportes` | Equipamiento deportivo, fitness, bicicletas, etc. |
| **5** | **Vehículos** | `vehiculos` | Autos, motos, scooters, accesorios para vehículos |
| **6** | **Libros y Música** | `libros-musica` | Libros, CDs, vinilos, instrumentos musicales |
| **7** | **Juguetes y Juegos** | `juguetes-juegos` | Consolas, videojuegos, juguetes, juegos de mesa |
| **8** | **Salud y Belleza** | `salud-belleza` | Perfumes, cosméticos, productos de cuidado personal |
| **9** | **Otros** | `otros` | Productos que no encajan en otras categorías |

---

## 🔧 Comandos Útiles

### Ver todas las categorías con sus IDs:
```bash
mysql -u fer -p esfero < sql/mapeo_categorias.sql
```

### Corregir productos mal clasificados en Deportes:
```bash
mysql -u fer -p esfero < sql/corregir_productos_deportes.sql
```

---

## 📝 Ejemplos de Productos por Categoría

### ID 1 - Electrónica
- Disco Duro, Router, Smartwatch, Cámaras, Bocinas, Audífonos, iPhone, Laptop, etc.

### ID 2 - Hogar y Jardín
- Escritorio, Sartenes, Licuadora, Centro de Entretenimiento, Refrigerador, Mesa, Silla, etc.

### ID 3 - Ropa y Accesorios
- Zapatos, Tenis, Camisa, Pantalón, Bolsa, Mochila, Reloj, Gafas, etc.

### ID 4 - Deportes
- Bicicleta, Pelota, Pesas, Gimnasio, Yoga, Running, etc.

### ID 5 - Vehículos
- Auto, Moto, Scooter Eléctrico, Llantas, Aceite, etc.

### ID 6 - Libros y Música
- Libro, Novela, CD, Vinilo, Guitarra, Piano, etc.

### ID 7 - Juguetes y Juegos
- Nintendo Switch, PS5, Xbox, Controles, Videojuegos, etc.

### ID 8 - Salud y Belleza
- Perfume, Crema, Shampoo, Maquillaje, Vitamina, etc.

### ID 9 - Otros
- Productos que no encajan en las categorías anteriores

