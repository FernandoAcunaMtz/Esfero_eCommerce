#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Carga variables de entorno desde archivo .env
Esfero - Marketplace
"""

import os
import sys

def load_env_file(env_path=None):
    """
    Carga variables de entorno desde un archivo .env
    
    Args:
        env_path: Ruta al archivo .env. Si es None, busca en la raíz del proyecto.
    """
    if env_path is None:
        # Buscar .env en la raíz del proyecto (2 niveles arriba desde cgi-bin)
        script_dir = os.path.dirname(os.path.abspath(__file__))
        project_root = os.path.dirname(os.path.dirname(script_dir))
        env_path = os.path.join(project_root, '.env')
    
    if not os.path.exists(env_path):
        # Si no existe, no hacer nada (las variables pueden estar en el sistema)
        return False
    
    try:
        with open(env_path, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                
                # Ignorar líneas vacías y comentarios
                if not line or line.startswith('#'):
                    continue
                
                # Dividir por el primer =
                if '=' in line:
                    key, value = line.split('=', 1)
                    key = key.strip()
                    value = value.strip()
                    
                    # Remover comillas si existen
                    value = value.strip('"\'')
                    
                    # Establecer la variable de entorno si no existe
                    if key and not os.getenv(key):
                        os.environ[key] = value
        
        return True
    except Exception as e:
        # Si hay error, no fallar silenciosamente en producción
        # pero permitir que continúe con variables del sistema
        print(f"Warning: Error loading .env file: {e}", file=sys.stderr)
        return False

# Cargar automáticamente al importar este módulo
load_env_file()

