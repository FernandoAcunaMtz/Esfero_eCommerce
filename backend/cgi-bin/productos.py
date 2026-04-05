#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
API de Productos
Esfero - Marketplace
"""

import sys
import os
import json
import cgi
from urllib.parse import parse_qs
from db import execute_query
from auth_validate import get_current_user, json_response

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))


def get_productos():
    """Obtiene lista de productos con filtros"""
    query_string = os.environ.get('QUERY_STRING', '')
    params = parse_qs(query_string)

    # Validación estricta de parámetros numéricos
    raw_categoria = params.get('categoria_id', [None])[0]
    if raw_categoria is not None:
        try:
            categoria_id = int(raw_categoria)
            if categoria_id <= 0:
                categoria_id = None
        except (ValueError, TypeError):
            categoria_id = None
    else:
        categoria_id = None

    destacados = params.get('destacados', ['false'])[0].lower() == 'true'
    recientes = params.get('recientes', ['false'])[0].lower() == 'true'
    busqueda = params.get('busqueda', [None])[0]

    try:
        limit = max(1, min(int(params.get('limit', ['20'])[0]), 100))
    except (ValueError, TypeError):
        limit = 20

    try:
        offset = max(0, int(params.get('offset', ['0'])[0]))
    except (ValueError, TypeError):
        offset = 0
    
    # Construir query
    where_clauses = ["p.activo = 1"]
    query_params = []
    
    if categoria_id:
        where_clauses.append("p.categoria_id = %s")
        query_params.append(int(categoria_id))
    
    if destacados:
        where_clauses.append("p.destacado = 1")
    
    if busqueda:
        where_clauses.append("(p.titulo LIKE %s OR p.descripcion LIKE %s)")
        search_term = f"%{busqueda}%"
        query_params.extend([search_term, search_term])
    
    where_sql = " AND ".join(where_clauses)
    order_sql = "p.fecha_publicacion DESC"
    
    if recientes:
        order_sql = "p.fecha_publicacion DESC"
    elif destacados:
        order_sql = "p.vistas DESC, p.fecha_publicacion DESC"
    
    query = f"""
        SELECT p.id, p.titulo, p.descripcion, p.precio, p.estado_producto,
               p.fecha_publicacion, p.ubicacion_estado, p.ubicacion_ciudad,
               p.vistas, p.destacado,
               c.nombre as categoria_nombre,
               u.nombre as vendedor_nombre,
               (SELECT url_imagen FROM imagenes_productos 
                WHERE producto_id = p.id AND es_principal = 1 
                LIMIT 1) as imagen_principal
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        WHERE {where_sql}
        ORDER BY {order_sql}
        LIMIT %s OFFSET %s
    """
    
    query_params.extend([limit, offset])
    
    productos = execute_query(query, tuple(query_params), fetch_all=True)
    
    # Contar total
    count_query = f"""
        SELECT COUNT(*) as total
        FROM productos p
        WHERE {where_sql}
    """
    total = execute_query(count_query, tuple(query_params[:-2]), fetch_one=True)['total']
    
    return json_response({
        'productos': productos or [],
        'total': total,
        'limit': limit,
        'offset': offset
    })


def get_producto(id):
    """Obtiene un producto por ID"""
    producto = execute_query(
        """SELECT p.*, 
                  c.nombre as categoria_nombre,
                  u.id as vendedor_id, u.nombre as vendedor_nombre,
                  u.email as vendedor_email,
                  p.calificacion_promedio
           FROM productos p
           LEFT JOIN categorias c ON p.categoria_id = c.id
           LEFT JOIN usuarios u ON p.vendedor_id = u.id
           LEFT JOIN perfiles p ON u.id = p.usuario_id
           WHERE p.id = %s AND p.activo = 1""",
        (id,),
        fetch_one=True
    )
    
    if not producto:
        return json_response({'error': 'Producto no encontrado'}, 404)
    
    # Obtener imágenes
    imagenes = execute_query(
        "SELECT id, url_imagen, orden, es_principal FROM imagenes_productos WHERE producto_id = %s ORDER BY orden",
        (id,),
        fetch_all=True
    )
    
    producto['imagenes'] = imagenes or []
    
    # Incrementar vistas
    execute_query("UPDATE productos SET vistas = vistas + 1 WHERE id = %s", (id,))
    
    return json_response({'producto': producto})


def create_producto():
    """Crea un nuevo producto"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    if current_user['rol'] not in ['vendedor', 'admin']:
        return json_response({'error': 'Solo vendedores pueden publicar productos'}, 403)
    
    form = cgi.FieldStorage()
    
    titulo = form.getvalue('titulo')
    descripcion = form.getvalue('descripcion', '')
    precio = form.getvalue('precio')
    categoria_id = form.getvalue('categoria_id')
    estado_producto = form.getvalue('estado_producto', 'bueno')
    stock = form.getvalue('stock', '1')
    ubicacion_estado = form.getvalue('ubicacion_estado', '')
    ubicacion_ciudad = form.getvalue('ubicacion_ciudad', '')
    
    if not titulo or not precio:
        return json_response({'error': 'Título y precio son requeridos'}, 400)
    
    try:
        producto_id = execute_query(
            """INSERT INTO productos 
               (vendedor_id, categoria_id, titulo, descripcion, precio, estado_producto, stock, ubicacion_estado, ubicacion_ciudad)
               VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)""",
            (current_user['user_id'], categoria_id, titulo, descripcion, 
             float(precio), estado_producto, int(stock), ubicacion_estado, ubicacion_ciudad)
        )
        
        # Obtener producto creado
        producto = execute_query(
            "SELECT * FROM productos WHERE id = LAST_INSERT_ID()",
            fetch_one=True
        )
        
        return json_response({'success': True, 'producto': producto}, 201)
    except Exception as e:
        return json_response({'error': str(e)}, 500)


def update_producto(id):
    """Actualiza un producto"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    # Verificar que el producto existe y pertenece al usuario
    producto = execute_query(
        "SELECT vendedor_id FROM productos WHERE id = %s",
        (id,),
        fetch_one=True
    )
    
    if not producto:
        return json_response({'error': 'Producto no encontrado'}, 404)
    
    if producto['vendedor_id'] != current_user['user_id'] and current_user['rol'] != 'admin':
        return json_response({'error': 'No tienes permiso para editar este producto'}, 403)
    
    form = cgi.FieldStorage()
    
    updates = {}
    if form.getvalue('titulo'):
        updates['titulo'] = form.getvalue('titulo')
    if form.getvalue('descripcion'):
        updates['descripcion'] = form.getvalue('descripcion')
    if form.getvalue('precio'):
        updates['precio'] = float(form.getvalue('precio'))
    if form.getvalue('categoria_id'):
        updates['categoria_id'] = int(form.getvalue('categoria_id'))
    if form.getvalue('estado_producto'):
        updates['estado_producto'] = form.getvalue('estado_producto')
    if form.getvalue('stock'):
        updates['stock'] = int(form.getvalue('stock'))
    
    if not updates:
        return json_response({'error': 'No hay campos para actualizar'}, 400)
    
    set_clause = ", ".join([f"{k} = %s" for k in updates.keys()])
    values = list(updates.values()) + [id]
    
    execute_query(
        f"UPDATE productos SET {set_clause} WHERE id = %s",
        tuple(values)
    )
    
    producto = execute_query("SELECT * FROM productos WHERE id = %s", (id,), fetch_one=True)
    
    return json_response({'success': True, 'producto': producto})


def delete_producto(id):
    """Elimina (desactiva) un producto"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    producto = execute_query(
        "SELECT vendedor_id FROM productos WHERE id = %s",
        (id,),
        fetch_one=True
    )
    
    if not producto:
        return json_response({'error': 'Producto no encontrado'}, 404)
    
    if producto['vendedor_id'] != current_user['user_id'] and current_user['rol'] != 'admin':
        return json_response({'error': 'No tienes permiso para eliminar este producto'}, 403)
    
    # Desactivar en lugar de eliminar
    execute_query("UPDATE productos SET activo = 0 WHERE id = %s", (id,))
    
    return json_response({'success': True, 'message': 'Producto eliminado'})


def main():
    """Función principal - router de peticiones"""
    method = os.environ.get('REQUEST_METHOD', 'GET')
    path_info = os.environ.get('PATH_INFO', '')
    
    if method == 'GET' and not path_info or path_info == '/':
        get_productos()
    elif method == 'GET' and '/producto' in path_info:
        # Extraer ID de la URL
        parts = path_info.split('/')
        producto_id = int(parts[-1]) if parts[-1].isdigit() else None
        if producto_id:
            get_producto(producto_id)
        else:
            json_response({'error': 'ID de producto requerido'}, 400)
    elif method == 'POST':
        create_producto()
    elif method == 'PUT' and '/producto' in path_info:
        parts = path_info.split('/')
        producto_id = int(parts[-1]) if parts[-1].isdigit() else None
        if producto_id:
            update_producto(producto_id)
        else:
            json_response({'error': 'ID de producto requerido'}, 400)
    elif method == 'DELETE' and '/producto' in path_info:
        parts = path_info.split('/')
        producto_id = int(parts[-1]) if parts[-1].isdigit() else None
        if producto_id:
            delete_producto(producto_id)
        else:
            json_response({'error': 'ID de producto requerido'}, 400)
    else:
        json_response({'error': 'Endpoint no encontrado'}, 404)


if __name__ == "__main__":
    main()
