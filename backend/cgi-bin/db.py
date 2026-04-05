#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Módulo de conexión a base de datos
Esfero - Marketplace
"""

import mysql.connector
from mysql.connector import Error
import os
import json
from contextlib import contextmanager

# Configuración de base de datos
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'database': os.getenv('DB_NAME', 'esfero'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'charset': 'utf8mb4',
    'collation': 'utf8mb4_unicode_ci',
    'autocommit': False,
    # Desactivar SSL explícitamente para evitar problemas con ssl.wrap_socket
    'ssl_disabled': True,
}


def get_connection():
    """
    Obtiene una conexión a la base de datos
    """
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        return connection
    except Error as e:
        print(f"Error conectando a la base de datos: {e}")
        return None


@contextmanager
def get_db_connection():
    """
    Context manager para manejar conexiones de base de datos
    """
    connection = None
    try:
        connection = get_connection()
        if connection:
            yield connection
            connection.commit()
        else:
            raise Exception("No se pudo establecer conexión a la base de datos")
    except Error as e:
        if connection:
            connection.rollback()
        raise e
    finally:
        if connection and connection.is_connected():
            connection.close()


def execute_query(query, params=None, fetch_one=False, fetch_all=False):
    """
    Ejecuta una consulta SQL
    
    Args:
        query: Consulta SQL
        params: Parámetros para la consulta
        fetch_one: Si es True, retorna una sola fila
        fetch_all: Si es True, retorna todas las filas
    
    Returns:
        Resultado de la consulta
    """
    with get_db_connection() as conn:
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(query, params or ())
            
            if fetch_one:
                result = cursor.fetchone()
            elif fetch_all:
                result = cursor.fetchall()
            else:
                result = cursor.rowcount
            
            return result
        finally:
            cursor.close()


def execute_many(query, params_list):
    """
    Ejecuta una consulta múltiple (INSERT/UPDATE masivo)
    
    Args:
        query: Consulta SQL
        params_list: Lista de tuplas con parámetros
    
    Returns:
        Número de filas afectadas
    """
    with get_db_connection() as conn:
        cursor = conn.cursor()
        try:
            cursor.executemany(query, params_list)
            return cursor.rowcount
        finally:
            cursor.close()


def test_connection():
    """
    Prueba la conexión a la base de datos
    """
    try:
        with get_db_connection() as conn:
            cursor = conn.cursor()
            cursor.execute("SELECT 1")
            cursor.fetchone()
            cursor.close()
            return True
    except Error as e:
        print(f"Error en test de conexión: {e}")
        return False


if __name__ == "__main__":
    # Test de conexión
    if test_connection():
        print("Conexión a la base de datos exitosa")
    else:
        print("Error al conectar a la base de datos")
