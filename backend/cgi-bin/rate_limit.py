#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Rate Limiting en memoria para endpoints sensibles.
Esfero - Marketplace
"""

import os
import time
from collections import defaultdict

# { ip: [(timestamp, endpoint), ...] }
_attempts: dict = defaultdict(list)

MAX_LOGIN_ATTEMPTS = int(os.getenv('RATE_LIMIT_LOGIN_MAX', '10'))
LOGIN_WINDOW_SECONDS = int(os.getenv('RATE_LIMIT_LOGIN_WINDOW', '300'))  # 5 min


def _get_client_ip() -> str:
    """Obtiene la IP del cliente desde las variables de entorno CGI"""
    return (
        os.environ.get('HTTP_X_FORWARDED_FOR', '').split(',')[0].strip()
        or os.environ.get('REMOTE_ADDR', 'unknown')
    )


def _clean_old_attempts(ip: str, window: int) -> None:
    """Elimina intentos fuera de la ventana de tiempo"""
    cutoff = time.time() - window
    _attempts[ip] = [(ts, ep) for ts, ep in _attempts[ip] if ts > cutoff]


def check_rate_limit(endpoint: str = 'login', max_attempts: int = MAX_LOGIN_ATTEMPTS,
                     window: int = LOGIN_WINDOW_SECONDS) -> tuple[bool, int]:
    """
    Verifica si el IP actual superó el límite de intentos.

    Returns:
        (allowed: bool, remaining: int) — si está permitido y cuántos intentos quedan
    """
    ip = _get_client_ip()
    _clean_old_attempts(ip, window)

    recent = [ts for ts, ep in _attempts[ip] if ep == endpoint]
    remaining = max(0, max_attempts - len(recent))

    if len(recent) >= max_attempts:
        return False, 0

    return True, remaining


def record_attempt(endpoint: str = 'login') -> None:
    """Registra un intento fallido para el IP actual"""
    ip = _get_client_ip()
    _attempts[ip].append((time.time(), endpoint))


def rate_limit_headers(endpoint: str = 'login', max_attempts: int = MAX_LOGIN_ATTEMPTS,
                       window: int = LOGIN_WINDOW_SECONDS) -> dict:
    """Retorna headers HTTP estándar de rate limit"""
    ip = _get_client_ip()
    _clean_old_attempts(ip, window)
    recent = [ts for ts, ep in _attempts[ip] if ep == endpoint]
    remaining = max(0, max_attempts - len(recent))
    reset_ts = int(time.time()) + window

    return {
        'X-RateLimit-Limit': str(max_attempts),
        'X-RateLimit-Remaining': str(remaining),
        'X-RateLimit-Reset': str(reset_ts),
    }
