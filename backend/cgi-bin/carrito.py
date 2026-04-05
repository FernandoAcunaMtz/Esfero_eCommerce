#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
API de Carrito de Compras - Esfero Marketplace
Gestión completa del carrito: agregar, actualizar, eliminar, listar
"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import json
from datetime import datetime
from db import execute_query
from auth_validate import get_current_user, json_response, require_auth


def get_request_data():
    """Obtiene datos del request soportando JSON o formulario"""
    content_type = os.environ.get('CONTENT_TYPE', '')
    
    if 'application/json' in content_type:
        try:
            content_length = int(os.environ.get('CONTENT_LENGTH') or 0)
        except ValueError:
            content_length = 0
        
        body = sys.stdin.read(content_length) if content_length > 0 else sys.stdin.read()
        
        try:
            return json.loads(body) if body else {}
        except json.JSONDecodeError:
            return {}

    # Soporte para application/x-www-form-urlencoded (formularios clásicos)
    try:
        from urllib.parse import parse_qs
        content_length = int(os.environ.get('CONTENT_LENGTH') or 0)
    except ValueError:
        content_length = 0

    if content_length > 0:
        body = sys.stdin.read(content_length)
        parsed = parse_qs(body)
        data = {}
        for key, values in parsed.items():
            if values:
                data[key] = values[0]
        return data

    return {}


def get_cart_items():
    """Obtiene todos los items del carrito del usuario actual"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    user_id = current_user['user_id']
    
    # Obtener items del carrito con información del producto
    items = execute_query(
        """SELECT 
            c.id as carrito_id,
            c.producto_id,
            c.cantidad,
            c.precio_momento,
            c.fecha_agregado,
            p.titulo,
            p.descripcion,
            p.precio as precio_actual,
            p.stock,
            p.vendido,
            p.activo,
            p.estado_producto,
            p.vendedor_id,
            u.nombre as vendedor_nombre,
            (SELECT url_imagen FROM imagenes_productos 
             WHERE producto_id = p.id AND es_principal = TRUE 
             LIMIT 1) as imagen_principal
        FROM carrito c
        INNER JOIN productos p ON c.producto_id = p.id
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        WHERE c.usuario_id = %s
        ORDER BY c.fecha_agregado DESC""",
        (user_id,),
        fetch_all=True
    )
    
    # Calcular totales
    subtotal = 0
    items_validos = []
    
    for item in items:
        # Verificar disponibilidad
        if not item['activo'] or item['vendido'] or item['stock'] < item['cantidad']:
            item['disponible'] = False
            item['mensaje_error'] = 'Producto no disponible'
        else:
            item['disponible'] = True
            subtotal += item['precio_actual'] * item['cantidad']

        # Normalizar tipos para que sean serializables a JSON
        # Convertir Decimals a float donde aplique
        try:
            if item.get('precio_actual') is not None:
                item['precio_actual'] = float(item['precio_actual'])
        except Exception:
            pass

        try:
            if item.get('precio_momento') is not None:
                item['precio_momento'] = float(item['precio_momento'])
        except Exception:
            pass
        
        try:
            if item.get('fecha_agregado') is not None:
                from datetime import datetime
                if isinstance(item['fecha_agregado'], datetime):
                    item['fecha_agregado'] = item['fecha_agregado'].isoformat()
        except Exception:
            pass
        
        items_validos.append(item)
    
    return json_response({
        'success': True,
        'items': items_validos,
        'resumen': {
            'subtotal': float(subtotal),
            'envio': 0.00,  # Calcular según lógica de negocio
            'total': float(subtotal),
            'cantidad_items': len(items_validos)
        }
    })


def add_to_cart():
    """Agrega un producto al carrito"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    data = get_request_data()
    user_id = current_user['user_id']
    producto_id = data.get('producto_id')
    cantidad = int(data.get('cantidad', 1))
    
    if not producto_id:
        return json_response({'error': 'producto_id requerido'}, 400)
    
    if cantidad < 1:
        return json_response({'error': 'Cantidad debe ser mayor a 0'}, 400)
    
    # Verificar que el producto existe y está disponible
    producto = execute_query(
        """SELECT id, precio, stock, vendido, activo, vendedor_id 
           FROM productos 
           WHERE id = %s""",
        (producto_id,),
        fetch_one=True
    )
    
    if not producto:
        return json_response({'error': 'Producto no encontrado'}, 404)
    
    if not producto['activo'] or producto['vendido']:
        return json_response({'error': 'Producto no disponible'}, 400)
    
    if producto['stock'] < cantidad:
        return json_response({'error': f'Stock insuficiente. Disponible: {producto["stock"]}'}, 400)
    
    # No permitir que el vendedor compre su propio producto
    if producto['vendedor_id'] == user_id:
        return json_response({'error': 'No puedes comprar tu propio producto'}, 400)
    
    # Verificar si ya existe en el carrito
    existing = execute_query(
        "SELECT id, cantidad FROM carrito WHERE usuario_id = %s AND producto_id = %s",
        (user_id, producto_id),
        fetch_one=True
    )
    
    if existing:
        # Actualizar cantidad
        nueva_cantidad = existing['cantidad'] + cantidad
        
        if nueva_cantidad > producto['stock']:
            return json_response({'error': f'Stock insuficiente. Disponible: {producto["stock"]}'}, 400)
        
        execute_query(
            "UPDATE carrito SET cantidad = %s, fecha_actualizacion = NOW() WHERE id = %s",
            (nueva_cantidad, existing['id'])
        )
        
        return json_response({
            'success': True,
            'message': 'Cantidad actualizada en el carrito',
            'carrito_id': existing['id']
        })
    else:
        # Insertar nuevo item
        execute_query(
            """INSERT INTO carrito (usuario_id, producto_id, cantidad, precio_momento, fecha_agregado)
               VALUES (%s, %s, %s, %s, NOW())""",
            (user_id, producto_id, cantidad, producto['precio'])
        )
        
        return json_response({
            'success': True,
            'message': 'Producto agregado al carrito'
        }, 201)


def update_cart_item():
    """Actualiza la cantidad de un item en el carrito"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    data = get_request_data()
    user_id = current_user['user_id']
    carrito_id = data.get('carrito_id')
    cantidad = int(data.get('cantidad', 1))
    
    if not carrito_id:
        return json_response({'error': 'carrito_id requerido'}, 400)
    
    if cantidad < 1:
        return json_response({'error': 'Cantidad debe ser mayor a 0'}, 400)
    
    # Verificar que el item pertenece al usuario
    item = execute_query(
        """SELECT c.id, c.producto_id, p.stock 
           FROM carrito c
           INNER JOIN productos p ON c.producto_id = p.id
           WHERE c.id = %s AND c.usuario_id = %s""",
        (carrito_id, user_id),
        fetch_one=True
    )
    
    if not item:
        return json_response({'error': 'Item no encontrado en tu carrito'}, 404)
    
    if cantidad > item['stock']:
        return json_response({'error': f'Stock insuficiente. Disponible: {item["stock"]}'}, 400)
    
    execute_query(
        "UPDATE carrito SET cantidad = %s, fecha_actualizacion = NOW() WHERE id = %s",
        (cantidad, carrito_id)
    )
    
    return json_response({
        'success': True,
        'message': 'Cantidad actualizada'
    })


def remove_from_cart():
    """Elimina un item del carrito"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    data = get_request_data()
    user_id = current_user['user_id']

    # DEBUG: ver qué llega en el cuerpo
    import sys
    print("DEBUG REMOVE_FROM_CART data:", data, file=sys.stderr)

    carrito_id = data.get('carrito_id')
    
    if not carrito_id:
        return json_response({'error': 'carrito_id requerido'}, 400)
    
    # Verificar que el item pertenece al usuario
    item = execute_query(
        "SELECT id FROM carrito WHERE id = %s AND usuario_id = %s",
        (carrito_id, user_id),
        fetch_one=True
    )
    
    if not item:
        return json_response({'error': 'Item no encontrado en tu carrito'}, 404)
    
    execute_query("DELETE FROM carrito WHERE id = %s", (carrito_id,))
    
    return json_response({
        'success': True,
        'message': 'Producto eliminado del carrito'
    })


def clear_cart():
    """Vacía completamente el carrito del usuario"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    user_id = current_user['user_id']
    
    execute_query("DELETE FROM carrito WHERE usuario_id = %s", (user_id,))
    
    return json_response({
        'success': True,
        'message': 'Carrito vaciado'
    })


def main():
    """Función principal - router de peticiones"""
    method = os.environ.get('REQUEST_METHOD', 'GET')
    path_info = os.environ.get('PATH_INFO', '')
    
    if method == 'GET' and path_info.endswith('/items'):
        get_cart_items()
    elif method == 'POST' and path_info.endswith('/add'):
        add_to_cart()
    # Aceptar tanto PUT como POST para actualizar
    elif method in ('PUT', 'POST') and path_info.endswith('/update'):
        update_cart_item()
    # Aceptar tanto DELETE como POST para eliminar
    elif method in ('DELETE', 'POST') and path_info.endswith('/remove'):
        remove_from_cart()
    # Aceptar tanto DELETE como POST para vaciar
    elif method in ('DELETE', 'POST') and path_info.endswith('/clear'):
        clear_cart()
    else:
        json_response({'error': 'Endpoint no encontrado'}, 404)


if __name__ == "__main__":
    main()
