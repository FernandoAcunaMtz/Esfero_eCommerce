#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
API de PayPal - Esfero Marketplace
Integración con PayPal Sandbox para procesar pagos
"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import load_env
import json
import requests
from datetime import datetime
from db import execute_query
from auth_validate import get_current_user, json_response


# Configuración de PayPal desde .env
PAYPAL_MODE = os.getenv('PAYPAL_MODE', 'sandbox')
PAYPAL_CLIENT_ID = os.getenv('PAYPAL_CLIENT_ID')
PAYPAL_CLIENT_SECRET = os.getenv('PAYPAL_CLIENT_SECRET')

if PAYPAL_MODE == 'sandbox':
    PAYPAL_API_BASE = 'https://api-m.sandbox.paypal.com'
else:
    PAYPAL_API_BASE = 'https://api-m.paypal.com'


def get_request_data():
    """Obtiene datos del request soportando JSON o formulario"""
    content_type = os.environ.get('CONTENT_TYPE', '')
    
    try:
        content_length = int(os.environ.get('CONTENT_LENGTH') or 0)
    except ValueError:
        content_length = 0
    
    if content_length > 0:
        body = sys.stdin.read(content_length)
    else:
        body = sys.stdin.read()
    
    if not body:
        return {}
    
    # Si es JSON, parsear JSON
    if 'application/json' in content_type:
        try:
            return json.loads(body) if body else {}
        except json.JSONDecodeError:
            return {}
    
    # Si es form-urlencoded, parsear como query string
    if 'application/x-www-form-urlencoded' in content_type or 'application/x-www-form-urlencoded' not in content_type:
        try:
            from urllib.parse import parse_qs
            parsed = parse_qs(body, keep_blank_values=True)
            # Convertir listas - mantener arrays si hay múltiples valores
            result = {}
            for key, value_list in parsed.items():
                # Si es orden_ids, intentar parsear como JSON array
                if key == 'orden_ids' and len(value_list) == 1:
                    try:
                        result[key] = json.loads(value_list[0])
                    except:
                        result[key] = value_list
                elif len(value_list) == 1:
                    result[key] = value_list[0]
                else:
                    result[key] = value_list
            return result
        except Exception:
            return {}
    
    return {}


def get_paypal_access_token():
    """Obtiene un token de acceso de PayPal"""
    if not PAYPAL_CLIENT_ID or not PAYPAL_CLIENT_SECRET:
        raise Exception("Credenciales de PayPal no configuradas. Verifica las variables PAYPAL_CLIENT_ID y PAYPAL_CLIENT_SECRET.")

    url = f"{PAYPAL_API_BASE}/v1/oauth2/token"
    headers = {
        "Accept": "application/json",
        "Accept-Language": "en_US",
    }

    try:
        response = requests.post(
            url,
            headers=headers,
            data={"grant_type": "client_credentials"},
            auth=(PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET),
            timeout=15
        )
    except requests.exceptions.Timeout:
        raise Exception("PayPal no respondió a tiempo. Por favor intenta nuevamente.")
    except requests.exceptions.ConnectionError:
        raise Exception("No se pudo conectar con PayPal. Verifica tu conexión a internet.")

    if response.status_code == 401:
        raise Exception("Credenciales de PayPal inválidas. Verifica tu Client ID y Secret.")
    if response.status_code != 200:
        raise Exception(f"Error al autenticar con PayPal (HTTP {response.status_code}).")

    return response.json()['access_token']


def create_paypal_order():
    """Crea una orden de pago en PayPal desde las órdenes del usuario"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    data = get_request_data()
    orden_ids = data.get('orden_ids', [])  # Lista de IDs de órdenes a pagar
    
    if not orden_ids:
        return json_response({'error': 'No se especificaron órdenes'}, 400)
    
    user_id = current_user['user_id']
    
    # Obtener las órdenes
    placeholders = ','.join(['%s'] * len(orden_ids))
    ordenes = execute_query(
        f"""SELECT id, numero_orden, total, moneda, comprador_id
            FROM ordenes 
            WHERE id IN ({placeholders}) AND comprador_id = %s AND estado_pago = 'pendiente'""",
        tuple(orden_ids) + (user_id,),
        fetch_all=True
    )
    
    if not ordenes:
        return json_response({'error': 'No se encontraron órdenes válidas'}, 404)
    
    # Calcular total a pagar
    total_amount = sum(orden['total'] for orden in ordenes)
    currency = ordenes[0]['moneda']
    
    # Crear orden en PayPal
    try:
        access_token = get_paypal_access_token()
        
        url = f"{PAYPAL_API_BASE}/v2/checkout/orders"
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {access_token}"
        }
        
        # Construir descripción
        orden_numbers = ', '.join([o['numero_orden'] for o in ordenes])
        
        payload = {
            "intent": "CAPTURE",
            "purchase_units": [{
                "reference_id": orden_numbers,
                "description": f"Compra en Esfero Marketplace - Órdenes: {orden_numbers}",
                "amount": {
                    "currency_code": currency,
                    "value": f"{total_amount:.2f}"
                }
            }],
            "application_context": {
                "brand_name": "Esfero Marketplace",
                "landing_page": "BILLING",
                "user_action": "PAY_NOW",
                "return_url": data.get('return_url') or os.getenv('PAYPAL_RETURN_URL', 'http://localhost/paypal_return.php'),
                "cancel_url": data.get('cancel_url') or os.getenv('PAYPAL_CANCEL_URL', 'http://localhost/paypal_cancel.php')
            }
        }
        
        response = requests.post(url, headers=headers, json=payload, timeout=15)

        if response.status_code not in [200, 201]:
            error_detail = ''
            try:
                error_body = response.json()
                error_detail = error_body.get('message', '') or error_body.get('error_description', '')
            except Exception:
                pass
            msg = f"No se pudo crear la orden de pago en PayPal."
            if error_detail:
                msg += f" Detalle: {error_detail}"
            return json_response({'error': msg}, 500)
        
        paypal_order = response.json()
        
        # Actualizar órdenes con el paypal_order_id
        for orden in ordenes:
            execute_query(
                "UPDATE ordenes SET paypal_order_id = %s, estado_pago = 'procesando' WHERE id = %s",
                (paypal_order['id'], orden['id'])
            )
        
        # Extraer approve URL
        approve_url = None
        for link in paypal_order.get('links', []):
            if link['rel'] == 'approve':
                approve_url = link['href']
                break
        
        return json_response({
            'success': True,
            'paypal_order_id': paypal_order['id'],
            'approve_url': approve_url,
            'orden_ids': orden_ids
        })
        
    except Exception as e:
        return json_response({'error': f'Error al procesar pago: {str(e)}'}, 500)


def capture_paypal_order():
    """Captura el pago de una orden de PayPal (después de que el usuario apruebe)"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    data = get_request_data()
    paypal_order_id = data.get('paypal_order_id')
    
    if not paypal_order_id:
        return json_response({'error': 'paypal_order_id requerido'}, 400)
    
    try:
        access_token = get_paypal_access_token()
        
        url = f"{PAYPAL_API_BASE}/v2/checkout/orders/{paypal_order_id}/capture"
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {access_token}"
        }
        
        response = requests.post(url, headers=headers)
        
        if response.status_code not in [200, 201]:
            return json_response({
                'error': 'Error al capturar pago en PayPal',
                'details': response.json()
            }, 500)
        
        capture_data = response.json()
        
        # Verificar que el pago fue completado
        if capture_data['status'] != 'COMPLETED':
            return json_response({
                'error': 'El pago no fue completado',
                'status': capture_data['status']
            }, 400)
        
        # Extraer información de la transacción
        payer_id = capture_data.get('payer', {}).get('payer_id')
        capture_id = capture_data['purchase_units'][0]['payments']['captures'][0]['id']
        
        # Actualizar órdenes en la base de datos
        execute_query(
            """UPDATE ordenes 
               SET estado_pago = 'completado',
                   estado = 'pago_confirmado',
                   paypal_payer_id = %s,
                   id_transaccion_paypal = %s,
                   fecha_pago = NOW(),
                   fecha_confirmacion = NOW()
               WHERE paypal_order_id = %s""",
            (payer_id, capture_id, paypal_order_id)
        )
        
        return json_response({
            'success': True,
            'message': 'Pago procesado exitosamente',
            'transaction_id': capture_id,
            'paypal_order_id': paypal_order_id
        })
        
    except Exception as e:
        return json_response({'error': f'Error al capturar pago: {str(e)}'}, 500)


def get_paypal_order_status():
    """Obtiene el estado de una orden de PayPal"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    data = get_request_data()
    paypal_order_id = data.get('paypal_order_id')
    
    if not paypal_order_id:
        return json_response({'error': 'paypal_order_id requerido'}, 400)
    
    try:
        access_token = get_paypal_access_token()
        
        url = f"{PAYPAL_API_BASE}/v2/checkout/orders/{paypal_order_id}"
        headers = {
            "Authorization": f"Bearer {access_token}"
        }
        
        response = requests.get(url, headers=headers)
        
        if response.status_code != 200:
            return json_response({
                'error': 'Error al consultar orden en PayPal',
                'details': response.json()
            }, 500)
        
        order_data = response.json()
        
        return json_response({
            'success': True,
            'paypal_order': order_data
        })
        
    except Exception as e:
        return json_response({'error': f'Error al consultar orden: {str(e)}'}, 500)


def main():
    """Función principal - router de peticiones"""
    method = os.environ.get('REQUEST_METHOD', 'GET')
    path_info = os.environ.get('PATH_INFO', '')
    
    if method == 'POST' and path_info.endswith('/create_order'):
        create_paypal_order()
    elif method == 'POST' and path_info.endswith('/capture_order'):
        capture_paypal_order()
    elif method == 'GET' and path_info.endswith('/order_status'):
        get_paypal_order_status()
    else:
        json_response({'error': 'Endpoint no encontrado'}, 404)


if __name__ == "__main__":
    main()
