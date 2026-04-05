#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
API de Calificaciones - Esfero Marketplace
Gestión de calificaciones y reseñas de productos y vendedores
"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import json
from decimal import Decimal
from datetime import datetime
from db import execute_query, get_db_connection
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
    
    return {}


def actualizar_calificacion_vendedor(vendedor_id):
    """Actualiza la calificación promedio de un vendedor usando stored procedure"""
    try:
        with get_db_connection() as conn:
            cursor = conn.cursor()
            cursor.execute(
                "CALL actualizar_calificacion_vendedor(%s)",
                (vendedor_id,)
            )
            conn.commit()
            return True
    except Exception as e:
        print(f"Error al actualizar calificación del vendedor: {e}")
        return False


def crear_calificacion():
    """Crea una nueva calificación y actualiza el promedio del vendedor"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    calificador_id = current_user['user_id']
    data = get_request_data()
    
    orden_id = data.get('orden_id')
    producto_id = data.get('producto_id')
    calificado_id = data.get('calificado_id')
    tipo = data.get('tipo', 'vendedor')  # 'vendedor', 'comprador', 'producto'
    calificacion = data.get('calificacion')
    titulo = data.get('titulo', '')
    comentario = data.get('comentario', '')
    
    # Validaciones
    if not all([orden_id, producto_id, calificado_id, calificacion]):
        return json_response({'error': 'Datos incompletos'}, 400)
    
    if tipo not in ['vendedor', 'comprador', 'producto']:
        return json_response({'error': 'Tipo de calificación inválido'}, 400)
    
    if not (1 <= calificacion <= 5):
        return json_response({'error': 'La calificación debe estar entre 1 y 5'}, 400)
    
    try:
        with get_db_connection() as conn:
            cursor = conn.cursor()
            
            # Verificar que la orden existe y pertenece al usuario
            orden = execute_query(
                "SELECT comprador_id, vendedor_id FROM ordenes WHERE id = %s",
                (orden_id,),
                fetch_one=True
            )
            
            if not orden:
                return json_response({'error': 'Orden no encontrada'}, 404)
            
            # Verificar que el usuario puede calificar (debe ser comprador o vendedor de la orden)
            if tipo == 'vendedor' and orden['comprador_id'] != calificador_id:
                return json_response({'error': 'Solo el comprador puede calificar al vendedor'}, 403)
            
            # Crear la calificación
            cursor.execute(
                """INSERT INTO calificaciones (
                    orden_id, producto_id, calificador_id, calificado_id,
                    tipo, calificacion, titulo, comentario, aprobada, visible
                ) VALUES (
                    %s, %s, %s, %s, %s, %s, %s, %s, TRUE, TRUE
                )""",
                (orden_id, producto_id, calificador_id, calificado_id,
                 tipo, calificacion, titulo, comentario)
            )
            
            # Si la calificación es para un vendedor, actualizar su promedio
            if tipo == 'vendedor':
                actualizar_calificacion_vendedor(calificado_id)
            
            conn.commit()
        
        return json_response({
            'success': True,
            'message': 'Calificación creada exitosamente'
        }, 201)
        
    except Exception as e:
        return json_response({'error': f'Error al crear calificación: {str(e)}'}, 500)


def main():
    """Función principal - router de peticiones"""
    method = os.environ.get('REQUEST_METHOD', 'GET')
    path_info = os.environ.get('PATH_INFO', '')
    
    if method == 'POST' and path_info.endswith('/create'):
        crear_calificacion()
    else:
        json_response({'error': 'Endpoint no encontrado'}, 404)


if __name__ == "__main__":
    main()

