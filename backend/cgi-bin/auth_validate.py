#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Validación de autenticación y autorización
Esfero - Marketplace
"""

import sys
import os
import json
from db import execute_query
from jwt_tools import verify_token, get_user_from_token

# Agregar el directorio actual al path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))


def get_auth_header():
    """
    Obtiene el token del header Authorization o encabezado alternativo
    """
    auth_header = os.environ.get('HTTP_AUTHORIZATION', '')
    if auth_header.startswith('Bearer '):
        return auth_header[7:]

    # Algunos servidores/proxies pueden no reenviar HTTP_AUTHORIZATION
    # para scripts CGI. Como alternativa, también aceptamos un header
    # personalizado X-Auth-Token que sí se propaga como HTTP_X_AUTH_TOKEN.
    x_auth = os.environ.get('HTTP_X_AUTH_TOKEN')
    if x_auth:
        return x_auth

    return None


def get_token_from_request():
    """
    Obtiene el token de la petición (header o POST)
    """
    # Intentar obtener del header Authorization (Bearer)
    token = get_auth_header()

    # En nuestra arquitectura actual, TODAS las llamadas autenticadas
    # envían el token únicamente por header. Evitamos usar cgi.FieldStorage
    # porque en Python 3.12 está dando problemas al manejar streams binarios.
    # Si en el futuro se necesita soportar token por query string, se podría
    # extender aquí usando QUERY_STRING, pero por ahora no es necesario.

    return token


def validate_token(token):
    """
    Valida un token JWT
    
    Args:
        token: Token JWT a validar
    
    Returns:
        Diccionario con datos del usuario si es válido, None si no
    """
    if not token:
        return None
    
    payload = verify_token(token)
    if not payload:
        return None
    
    # Verificar que el usuario existe y está activo
    user_id = payload.get('user_id')
    user = execute_query(
        "SELECT id, email, rol, estado FROM usuarios WHERE id = %s AND estado = 'activo'",
        (user_id,),
        fetch_one=True
    )
    
    if user:
        return {
            'user_id': user['id'],
            'email': user['email'],
            'rol': user['rol']
        }
    
    return None


def require_auth(required_roles=None):
    """
    Decorador para requerir autenticación
    
    Args:
        required_roles: Lista de roles permitidos (None = cualquier rol autenticado)
    
    Returns:
        Función decoradora
    """
    def decorator(func):
        def wrapper(*args, **kwargs):
            token = get_token_from_request()
            user = validate_token(token)
            
            if not user:
                return json_response({'error': 'No autorizado'}, 401)
            
            if required_roles and user['rol'] not in required_roles:
                return json_response({'error': 'Acceso denegado'}, 403)
            
            kwargs['current_user'] = user
            return func(*args, **kwargs)
        
        return wrapper
    return decorator


def get_current_user():
    """
    Obtiene el usuario actual desde el token
    
    Returns:
        Diccionario con datos del usuario o None
    """
    token = get_token_from_request()
    return validate_token(token)


def json_response(data, status_code=200):
    """
    Genera una respuesta JSON con headers CORS
    
    Args:
        data: Datos a enviar
        status_code: Código de estado HTTP
    
    Returns:
        String con la respuesta JSON
    """
    print(f"Status: {status_code}")
    print("Content-Type: application/json; charset=utf-8")
    print("Access-Control-Allow-Origin: *")
    print("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS")
    print("Access-Control-Allow-Headers: Content-Type, Authorization")
    print()
    print(json.dumps(data, ensure_ascii=False, indent=2))
    return json.dumps(data, ensure_ascii=False)


if __name__ == "__main__":
    # Test de validación
    token = get_token_from_request()
    user = validate_token(token)
    
    if user:
        json_response({'valid': True, 'user': user})
    else:
        json_response({'valid': False, 'error': 'Token inválido'}, 401)
