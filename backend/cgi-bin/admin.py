#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
API de Administración
Esfero - Marketplace
"""

import sys
import os
import json
import cgi
from datetime import datetime, timedelta
from db import execute_query
from auth_validate import get_current_user, json_response

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))


def require_admin():
    """Verifica que el usuario sea administrador"""
    current_user = get_current_user()
    
    if not current_user:
        return None
    
    if current_user['rol'] != 'admin':
        return None
    
    return current_user


def get_dashboard_stats():
    """Obtiene estadísticas del dashboard de administración"""
    admin = require_admin()
    
    if not admin:
        return json_response({'error': 'Acceso denegado. Se requiere rol de administrador'}, 403)
    
    # Estadísticas generales
    total_usuarios = execute_query(
        "SELECT COUNT(*) as total FROM usuarios",
        fetch_one=True
    )['total']
    
    total_productos = execute_query(
        "SELECT COUNT(*) as total FROM productos WHERE activo = 1",
        fetch_one=True
    )['total']
    
    total_ordenes = execute_query(
        "SELECT COUNT(*) as total FROM ordenes",
        fetch_one=True
    )['total']
    
    total_ventas = execute_query(
        "SELECT COALESCE(SUM(total), 0) as total FROM ordenes WHERE estado IN ('confirmada', 'enviada', 'entregada')",
        fetch_one=True
    )['total']
    
    # Usuarios por rol
    usuarios_por_rol = execute_query(
        "SELECT rol, COUNT(*) as cantidad FROM usuarios GROUP BY rol",
        fetch_all=True
    )
    
    # Productos por categoría
    productos_por_categoria = execute_query(
        """SELECT c.nombre, COUNT(p.id) as cantidad
           FROM categorias c
           LEFT JOIN productos p ON c.id = p.categoria_id AND p.activo = 1
           GROUP BY c.id, c.nombre
           ORDER BY cantidad DESC""",
        fetch_all=True
    )
    
    # Órdenes por estado
    ordenes_por_estado = execute_query(
        "SELECT estado, COUNT(*) as cantidad FROM ordenes GROUP BY estado",
        fetch_all=True
    )
    
    # Ventas por mes (últimos 6 meses)
    ventas_por_mes = execute_query(
        """SELECT DATE_FORMAT(fecha_creacion, '%%Y-%%m') as mes, 
                  COUNT(*) as cantidad_ordenes,
                  SUM(total) as total_ventas
           FROM ordenes
           WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
           GROUP BY mes
           ORDER BY mes DESC""",
        fetch_all=True
    )
    
    # Usuarios nuevos (últimos 30 días)
    usuarios_nuevos = execute_query(
        "SELECT COUNT(*) as total FROM usuarios WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        fetch_one=True
    )['total']
    
    # Productos nuevos (últimos 30 días)
    productos_nuevos = execute_query(
        "SELECT COUNT(*) as total FROM productos WHERE fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        fetch_one=True
    )['total']
    
    return json_response({
        'stats': {
            'total_usuarios': total_usuarios,
            'total_productos': total_productos,
            'total_ordenes': total_ordenes,
            'total_ventas': float(total_ventas),
            'usuarios_nuevos': usuarios_nuevos,
            'productos_nuevos': productos_nuevos
        },
        'usuarios_por_rol': usuarios_por_rol or [],
        'productos_por_categoria': productos_por_categoria or [],
        'ordenes_por_estado': ordenes_por_estado or [],
        'ventas_por_mes': ventas_por_mes or []
    })


def get_usuarios():
    """Obtiene lista de usuarios (solo admin)"""
    admin = require_admin()
    
    if not admin:
        return json_response({'error': 'Acceso denegado'}, 403)
    
    usuarios = execute_query(
        """SELECT u.id, u.email, u.nombre, u.apellidos, u.rol, u.estado,
                  u.fecha_registro, u.ultimo_acceso,
                  COUNT(DISTINCT p.id) as total_productos,
                  COUNT(DISTINCT o.id) as total_ordenes
           FROM usuarios u
           LEFT JOIN productos p ON u.id = p.vendedor_id
           LEFT JOIN ordenes o ON u.id = o.comprador_id OR u.id = o.vendedor_id
           GROUP BY u.id
           ORDER BY u.fecha_registro DESC""",
        fetch_all=True
    )
    
    return json_response({'usuarios': usuarios or []})


def update_usuario_estado():
    """Actualiza el estado de un usuario"""
    admin = require_admin()
    
    if not admin:
        return json_response({'error': 'Acceso denegado'}, 403)
    
    form = cgi.FieldStorage()
    usuario_id = form.getvalue('usuario_id')
    nuevo_estado = form.getvalue('estado')
    
    if not usuario_id or not nuevo_estado:
        return json_response({'error': 'ID de usuario y estado requeridos'}, 400)
    
    if nuevo_estado not in ['activo', 'inactivo', 'suspendido']:
        return json_response({'error': 'Estado inválido'}, 400)
    
    execute_query(
        "UPDATE usuarios SET estado = %s WHERE id = %s",
        (nuevo_estado, int(usuario_id))
    )
    
    return json_response({'success': True, 'message': 'Estado de usuario actualizado'})


def update_usuario_rol():
    """Actualiza el rol de un usuario"""
    admin = require_admin()
    
    if not admin:
        return json_response({'error': 'Acceso denegado'}, 403)
    
    form = cgi.FieldStorage()
    usuario_id = form.getvalue('usuario_id')
    nuevo_rol = form.getvalue('rol')
    
    if not usuario_id or not nuevo_rol:
        return json_response({'error': 'ID de usuario y rol requeridos'}, 400)
    
    if nuevo_rol not in ['cliente', 'vendedor', 'admin']:
        return json_response({'error': 'Rol inválido'}, 400)
    
    # No permitir cambiar el rol del propio admin
    if int(usuario_id) == admin['user_id']:
        return json_response({'error': 'No puedes cambiar tu propio rol'}, 400)
    
    execute_query(
        "UPDATE usuarios SET rol = %s WHERE id = %s",
        (nuevo_rol, int(usuario_id))
    )
    
    return json_response({'success': True, 'message': 'Rol de usuario actualizado'})


def get_reportes():
    """Obtiene reportes del sistema"""
    admin = require_admin()
    
    if not admin:
        return json_response({'error': 'Acceso denegado'}, 403)
    
    # Productos más vendidos
    productos_mas_vendidos = execute_query(
        """SELECT p.id, p.titulo, p.precio,
                  COUNT(oi.id) as veces_vendido,
                  SUM(oi.cantidad) as unidades_vendidas,
                  SUM(oi.subtotal) as total_ventas
           FROM productos p
           JOIN orden_items oi ON p.id = oi.producto_id
           JOIN ordenes o ON oi.orden_id = o.id
           WHERE o.estado IN ('confirmada', 'enviada', 'entregada')
           GROUP BY p.id
           ORDER BY total_ventas DESC
           LIMIT 10""",
        fetch_all=True
    )
    
    # Vendedores top
    vendedores_top = execute_query(
        """SELECT u.id, u.nombre, u.email,
                  COUNT(DISTINCT o.id) as total_ventas,
                  SUM(o.total) as total_ingresos
           FROM usuarios u
           JOIN ordenes o ON u.id = o.vendedor_id
           WHERE o.estado IN ('confirmada', 'enviada', 'entregada')
           GROUP BY u.id
           ORDER BY total_ingresos DESC
           LIMIT 10""",
        fetch_all=True
    )
    
    # Categorías más populares
    categorias_populares = execute_query(
        """SELECT c.nombre, 
                  COUNT(DISTINCT p.id) as total_productos,
                  COUNT(DISTINCT oi.orden_id) as total_ventas
           FROM categorias c
           LEFT JOIN productos p ON c.id = p.categoria_id
           LEFT JOIN orden_items oi ON p.id = oi.producto_id
           GROUP BY c.id, c.nombre
           ORDER BY total_ventas DESC""",
        fetch_all=True
    )
    
    return json_response({
        'productos_mas_vendidos': productos_mas_vendidos or [],
        'vendedores_top': vendedores_top or [],
        'categorias_populares': categorias_populares or []
    })


def main():
    """Función principal - router de peticiones"""
    method = os.environ.get('REQUEST_METHOD', 'GET')
    path_info = os.environ.get('PATH_INFO', '')
    
    if method == 'GET' and '/dashboard' in path_info:
        get_dashboard_stats()
    elif method == 'GET' and '/usuarios' in path_info:
        get_usuarios()
    elif method == 'PUT' and '/usuario/estado' in path_info:
        update_usuario_estado()
    elif method == 'PUT' and '/usuario/rol' in path_info:
        update_usuario_rol()
    elif method == 'GET' and '/reportes' in path_info:
        get_reportes()
    else:
        json_response({'error': 'Endpoint no encontrado'}, 404)


if __name__ == "__main__":
    main()
