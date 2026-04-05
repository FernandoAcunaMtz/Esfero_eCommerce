#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
API de Usuarios
Esfero - Marketplace
"""

import sys
import os
import json
import cgi
import bcrypt
from datetime import datetime

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import load_env  # Carga variables de entorno desde .env antes de conectar a la BD
from db import execute_query, get_db_connection
from auth_validate import get_current_user, json_response, require_auth
from jwt_tools import generate_token
from rate_limit import check_rate_limit, record_attempt

PASSWORD_COLUMNS_CACHE = None


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
    
    form = cgi.FieldStorage()
    data = {}
    for key in form.keys():
        data[key] = form.getvalue(key)
    return data


def get_password_columns():
    """Obtiene las columnas disponibles para almacenar contraseñas"""
    global PASSWORD_COLUMNS_CACHE
    if PASSWORD_COLUMNS_CACHE is not None:
        return PASSWORD_COLUMNS_CACHE
    
    columns = []
    if execute_query("SHOW COLUMNS FROM usuarios LIKE %s", ('password_hash',), fetch_one=True):
        columns.append('password_hash')
    if execute_query("SHOW COLUMNS FROM usuarios LIKE %s", ('password',), fetch_one=True):
        columns.append('password')
    
    if not columns:
        raise Exception("La tabla usuarios no tiene columnas de contraseña (password o password_hash)")
    
    PASSWORD_COLUMNS_CACHE = columns
    return PASSWORD_COLUMNS_CACHE

def hash_password(password):
    """Genera hash seguro de contraseña usando bcrypt"""
    return bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')


def verify_password(password, stored_hash):
    """Verifica una contraseña contra su hash bcrypt almacenado"""
    try:
        return bcrypt.checkpw(password.encode('utf-8'), stored_hash.encode('utf-8'))
    except Exception:
        return False


def register_user():
    """Registra un nuevo usuario"""
    data = get_request_data()
    
    email = data.get('email')
    password = data.get('password')
    nombre = data.get('nombre')
    apellidos = data.get('apellidos', '')
    telefono = data.get('telefono', '')
    rol = data.get('rol', 'cliente')
    
    if not email or not password or not nombre:
        return json_response({'error': 'Faltan campos requeridos'}, 400)
    
    # Verificar si el email ya existe
    existing = execute_query(
        "SELECT id FROM usuarios WHERE email = %s",
        (email,),
        fetch_one=True
    )
    
    if existing:
        return json_response({'error': 'El email ya está registrado'}, 409)
    
    # Crear usuario
    password_hash = hash_password(password)
    password_columns = get_password_columns()
    primary_column = password_columns[0]
    
    try:
        execute_query(
            f"""INSERT INTO usuarios (email, {primary_column}, nombre, apellidos, telefono, rol, estado, fecha_registro, ultimo_acceso)
               VALUES (%s, %s, %s, %s, %s, %s, 'activo', NOW(), NOW())""",
            (email, password_hash, nombre, apellidos, telefono, rol)
        )
        
        # Mantener columnas adicionales sincronizadas
        if len(password_columns) > 1:
            extra_cols = password_columns[1:]
            for col in extra_cols:
                execute_query(
                    f"UPDATE usuarios SET {col} = %s WHERE email = %s",
                    (password_hash, email)
                )
        
        # Obtener el usuario recién creado
        new_user = execute_query(
            "SELECT id, email, nombre, apellidos, rol FROM usuarios WHERE email = %s",
            (email,),
            fetch_one=True
        )
        
        token = generate_token(new_user['id'], new_user['email'], new_user['rol'])
        
        return json_response({
            'success': True,
            'user': new_user,
            'token': token
        }, 201)
    except Exception as e:
        return json_response({'error': str(e)}, 500)


def login_user():
    """Inicia sesión de usuario"""
    allowed, remaining = check_rate_limit('login')
    if not allowed:
        return json_response(
            {'error': 'Demasiados intentos fallidos. Espera 5 minutos antes de intentar nuevamente.'},
            429
        )

    data = get_request_data()

    email = data.get('email')
    password = data.get('password')

    if not email or not password:
        return json_response({'error': 'Email y contraseña requeridos'}, 400)

    password_columns = get_password_columns()
    primary_column = password_columns[0]

    # Obtener el usuario por email para verificar con bcrypt
    user = execute_query(
        f"""SELECT id, email, nombre, apellidos, rol, estado, {primary_column} as stored_hash
            FROM usuarios
            WHERE email = %s""",
        (email,),
        fetch_one=True
    )

    if not user or not verify_password(password, user.get('stored_hash', '')):
        record_attempt('login')
        return json_response({'error': 'Credenciales inválidas'}, 401)
    
    if user['estado'] != 'activo':
        return json_response({'error': 'Usuario inactivo'}, 403)
    
    # Actualizar último acceso
    execute_query(
        "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = %s",
        (user['id'],)
    )
    
    # Generar token
    token = generate_token(user['id'], user['email'], user['rol'])
    
    return json_response({
        'success': True,
        'user': {
            'id': user['id'],
            'email': user['email'],
            'nombre': user['nombre'],
            'apellidos': user['apellidos'],
            'rol': user['rol']
        },
        'token': token
    })


def get_user_profile(user_id=None):
    """Obtiene el perfil de un usuario"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    # Si no se especifica user_id, usar el usuario actual
    if not user_id:
        user_id = current_user['user_id']
    
    # Solo permitir ver otros perfiles si es admin o el mismo usuario
    if current_user['user_id'] != user_id and current_user['rol'] != 'admin':
        return json_response({'error': 'Acceso denegado'}, 403)
    
    user = execute_query(
        """SELECT u.id, u.email, u.nombre, u.apellidos, u.telefono, u.rol, 
                  u.fecha_registro, u.ultimo_acceso,
                  p.foto_perfil, p.descripcion, p.calificacion_promedio,
                  p.ubicacion_estado, p.ubicacion_ciudad, p.codigo_postal
           FROM usuarios u
           LEFT JOIN perfiles p ON u.id = p.usuario_id
           WHERE u.id = %s""",
        (user_id,),
        fetch_one=True
    )
    
    if not user:
        return json_response({'error': 'Usuario no encontrado'}, 404)
    
    return json_response({'user': user})


def update_user_profile():
    """Actualiza el perfil de un usuario"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    form = cgi.FieldStorage()
    user_id = current_user['user_id']
    
    # Campos actualizables en tabla usuarios
    updates = {}
    if form.getvalue('nombre'):
        updates['nombre'] = form.getvalue('nombre')
    if form.getvalue('apellidos'):
        updates['apellidos'] = form.getvalue('apellidos')
    if form.getvalue('telefono'):
        updates['telefono'] = form.getvalue('telefono')
    if form.getvalue('email'):
        updates['email'] = form.getvalue('email')

    if updates:
        set_clause = ", ".join([f"{k} = %s" for k in updates.keys()])
        values = list(updates.values()) + [user_id]

        execute_query(
            f"UPDATE usuarios SET {set_clause} WHERE id = %s",
            tuple(values)
        )

    # Actualizar perfil (tabla perfiles) si hay datos relacionados
    if (form.getvalue('descripcion') or form.getvalue('ubicacion_estado') or
            form.getvalue('ubicacion_ciudad') or form.getvalue('foto_perfil') or
            form.getvalue('codigo_postal')):
        profile = execute_query(
            "SELECT id FROM perfiles WHERE usuario_id = %s",
            (user_id,),
            fetch_one=True
        )
        
        if profile:
            # Actualizar perfil existente
            profile_updates = {}
            if form.getvalue('descripcion'):
                profile_updates['descripcion'] = form.getvalue('descripcion')
            if form.getvalue('ubicacion_estado'):
                profile_updates['ubicacion_estado'] = form.getvalue('ubicacion_estado')
            if form.getvalue('ubicacion_ciudad'):
                profile_updates['ubicacion_ciudad'] = form.getvalue('ubicacion_ciudad')
            if form.getvalue('foto_perfil'):
                profile_updates['foto_perfil'] = form.getvalue('foto_perfil')
            if form.getvalue('codigo_postal'):
                profile_updates['codigo_postal'] = form.getvalue('codigo_postal')

            if profile_updates:
                set_clause = ", ".join([f"{k} = %s" for k in profile_updates.keys()])
                values = list(profile_updates.values()) + [user_id]
                execute_query(
                    f"UPDATE perfiles SET {set_clause} WHERE usuario_id = %s",
                    tuple(values)
                )
        else:
            # Crear nuevo perfil
            execute_query(
                """INSERT INTO perfiles (usuario_id, descripcion, ubicacion_estado, ubicacion_ciudad, foto_perfil, codigo_postal)
                   VALUES (%s, %s, %s, %s, %s, %s)""",
                (user_id,
                 form.getvalue('descripcion', ''),
                 form.getvalue('ubicacion_estado', ''),
                 form.getvalue('ubicacion_ciudad', ''),
                 form.getvalue('foto_perfil', ''),
                 form.getvalue('codigo_postal', ''))
            )
    
    return json_response({'success': True, 'message': 'Perfil actualizado'})


def main():
    """Función principal - router de peticiones"""
    method = os.environ.get('REQUEST_METHOD', 'GET')
    path_info = os.environ.get('PATH_INFO', '')
    
    if method == 'POST' and path_info.endswith('/register'):
        register_user()
    elif method == 'POST' and path_info.endswith('/login'):
        login_user()
    elif method == 'GET' and '/profile' in path_info:
        # Extraer user_id de la URL si existe
        parts = path_info.split('/')
        user_id = int(parts[-1]) if parts[-1].isdigit() else None
        get_user_profile(user_id)
    elif method == 'PUT' and '/profile' in path_info:
        update_user_profile()
    else:
        json_response({'error': 'Endpoint no encontrado'}, 404)


if __name__ == "__main__":
    main()
