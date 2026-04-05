#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Herramientas para manejo de JWT (JSON Web Tokens)
Esfero - Marketplace
"""

import jwt
import hashlib
import secrets
import time
from datetime import datetime, timedelta
import os
from pathlib import Path

# Configuración de claves RSA
BASE_DIR = Path(__file__).resolve().parent
DEFAULT_KEYS_DIR = BASE_DIR.parent / 'keys'
DEFAULT_KEYS_DIR.mkdir(parents=True, exist_ok=True)

PRIVATE_KEY_PATH = Path(os.getenv('JWT_PRIVATE_KEY', DEFAULT_KEYS_DIR / 'jwt_private.pem'))
PUBLIC_KEY_PATH = Path(os.getenv('JWT_PUBLIC_KEY', DEFAULT_KEYS_DIR / 'jwt_public.pem'))

JWT_ALGORITHM = 'RS256'
TOKEN_EXPIRATION_HOURS = int(os.getenv('JWT_EXPIRATION_HOURS', '24'))

_PRIVATE_KEY_CACHE = None
_PUBLIC_KEY_CACHE = None


def _load_private_key():
    """
    Carga la clave privada para firmar tokens
    """
    global _PRIVATE_KEY_CACHE
    if _PRIVATE_KEY_CACHE:
        return _PRIVATE_KEY_CACHE
    
    if not PRIVATE_KEY_PATH.exists():
        raise FileNotFoundError(
            f"No se encontró la clave privada en {PRIVATE_KEY_PATH}. "
            "Genera un par de claves RSA (openssl genrsa -out jwt_private.pem 2048)."
        )
    
    _PRIVATE_KEY_CACHE = PRIVATE_KEY_PATH.read_text()
    return _PRIVATE_KEY_CACHE


def _load_public_key():
    """
    Carga la clave pública para verificar tokens
    """
    global _PUBLIC_KEY_CACHE
    if _PUBLIC_KEY_CACHE:
        return _PUBLIC_KEY_CACHE
    
    if not PUBLIC_KEY_PATH.exists():
        raise FileNotFoundError(
            f"No se encontró la clave pública en {PUBLIC_KEY_PATH}. "
            "Genera un par de claves RSA y distribuye la clave pública."
        )
    
    _PUBLIC_KEY_CACHE = PUBLIC_KEY_PATH.read_text()
    return _PUBLIC_KEY_CACHE


def generate_token(user_id, email, rol, additional_data=None):
    """
    Genera un token JWT para un usuario
    
    Args:
        user_id: ID del usuario
        email: Email del usuario
        rol: Rol del usuario (cliente, vendedor, admin)
        additional_data: Diccionario con datos adicionales
    
    Returns:
        Token JWT codificado
    """
    payload = {
        'user_id': user_id,
        'email': email,
        'rol': rol,
        'iat': datetime.utcnow(),
        'exp': datetime.utcnow() + timedelta(hours=TOKEN_EXPIRATION_HOURS)
    }
    
    if additional_data:
        payload.update(additional_data)
    
    private_key = _load_private_key()
    token = jwt.encode(payload, private_key, algorithm=JWT_ALGORITHM)
    return token


def verify_token(token):
    """
    Verifica y decodifica un token JWT
    
    Args:
        token: Token JWT a verificar
    
    Returns:
        Diccionario con los datos del payload si es válido, None si no lo es
    """
    try:
        public_key = _load_public_key()
        payload = jwt.decode(token, public_key, algorithms=[JWT_ALGORITHM])
        return payload
    except jwt.ExpiredSignatureError:
        return None
    except jwt.InvalidTokenError:
        return None


def generate_refresh_token():
    """
    Genera un token de refresco aleatorio
    
    Returns:
        String con el token de refresco
    """
    return secrets.token_urlsafe(32)


def hash_token(token):
    """
    Genera un hash de un token para almacenarlo en la base de datos
    
    Args:
        token: Token a hashear
    
    Returns:
        Hash SHA-256 del token
    """
    return hashlib.sha256(token.encode()).hexdigest()


def get_user_from_token(token):
    """
    Extrae la información del usuario desde un token
    
    Args:
        token: Token JWT
    
    Returns:
        Diccionario con user_id, email, rol o None si el token es inválido
    """
    payload = verify_token(token)
    if payload:
        return {
            'user_id': payload.get('user_id'),
            'email': payload.get('email'),
            'rol': payload.get('rol')
        }
    return None


def is_token_expired(token):
    """
    Verifica si un token ha expirado

    Args:
        token: Token JWT

    Returns:
        True si está expirado, False si es válido y vigente
    """
    try:
        public_key = _load_public_key()
        jwt.decode(token, public_key, algorithms=[JWT_ALGORITHM])
        return False
    except jwt.ExpiredSignatureError:
        return True
    except jwt.InvalidTokenError:
        return True
