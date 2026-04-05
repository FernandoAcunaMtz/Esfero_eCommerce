#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
API de Órdenes - Esfero Marketplace
Gestión de órdenes de compra desde el carrito
"""

import sys
import os
import json
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

try:
    import hashlib
    from datetime import datetime
    from decimal import Decimal
    from db import execute_query, get_db_connection
    from auth_validate import get_current_user, json_response, require_auth
except ImportError as e:
    print("Status: 500")
    print("Content-Type: application/json")
    print()
    print(json.dumps({'error': 'Error al importar módulos: ' + str(e)}, ensure_ascii=False))
    sys.exit(1)


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
    if 'application/x-www-form-urlencoded' in content_type:
        try:
            from urllib.parse import parse_qs
            parsed = parse_qs(body, keep_blank_values=True)
            # Convertir listas a valores únicos
            result = {}
            for key, value_list in parsed.items():
                result[key] = value_list[0] if value_list else ''
            return result
        except Exception:
            return {}
    
    # Si no tiene Content-Type específico pero hay body, intentar parsear como form-urlencoded
    if body and not content_type:
        try:
            from urllib.parse import parse_qs
            parsed = parse_qs(body, keep_blank_values=True)
            result = {}
            for key, value_list in parsed.items():
                result[key] = value_list[0] if value_list else ''
            return result
        except Exception:
            return {}
    
    return {}


def marcar_producto_vendido(producto_id, orden_id):
    """Marca un producto como vendido usando stored procedure"""
    try:
        with get_db_connection() as conn:
            cursor = conn.cursor()
            cursor.execute(
                "CALL marcar_producto_vendido(%s, %s)",
                (producto_id, orden_id)
            )
            conn.commit()
            return True
    except Exception as e:
        print(f"Error al marcar producto como vendido: {e}")
        return False


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


def generate_order_number():
    """Genera un número de orden único"""
    timestamp = datetime.now().strftime('%Y%m%d%H%M%S')
    random_hash = hashlib.md5(str(datetime.now().microsecond).encode()).hexdigest()[:6].upper()
    return f"ORD-{timestamp}-{random_hash}"


def create_order_from_cart():
    """Crea una orden desde el carrito del usuario usando stored procedure"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    user_id = current_user['user_id']
    data = get_request_data()
    
    # Obtener información de envío
    direccion_envio = data.get('direccion_envio', '')
    ciudad_envio = data.get('ciudad_envio', '')
    estado_envio = data.get('estado_envio', '')
    codigo_postal_envio = data.get('codigo_postal_envio', '')
    telefono_envio = data.get('telefono_envio', '')
    
    # Obtener vendedores únicos del carrito
    vendedores = execute_query(
        """SELECT DISTINCT p.vendedor_id
        FROM carrito c
        INNER JOIN productos p ON c.producto_id = p.id
        WHERE c.usuario_id = %s""",
        (user_id,),
        fetch_all=True
    )
    
    if not vendedores:
        return json_response({'error': 'El carrito está vacío'}, 400)
    
    # Validar disponibilidad de todos los productos
    items_carrito = execute_query(
        """SELECT 
            c.producto_id,
            c.cantidad,
            p.titulo,
            p.stock,
            p.vendido,
            p.activo,
            p.vendedor_id
        FROM carrito c
        INNER JOIN productos p ON c.producto_id = p.id
        WHERE c.usuario_id = %s""",
        (user_id,),
        fetch_all=True
    )
    
    for item in items_carrito:
        if not item['activo'] or item['vendido']:
            return json_response({
                'error': f'El producto "{item["titulo"]}" ya no está disponible'
            }, 400)
        if item['stock'] < item['cantidad']:
            return json_response({
                'error': f'Stock insuficiente para "{item["titulo"]}". Disponible: {item["stock"]}'
            }, 400)
    
    created_orders = []
    
    try:
        with get_db_connection() as conn:
            cursor = conn.cursor()
            
            # Crear una orden por cada vendedor usando stored procedure
            for vendedor_item in vendedores:
                vendedor_id = vendedor_item['vendedor_id']
                
                # Llamar stored procedure usando CALL con variables de sesión para OUT
                cursor.execute("""
                    CALL crear_orden_desde_carrito(
                        %s, %s, %s, %s, %s, %s, %s,
                        @out_orden_id, @out_numero_orden
                    )
                """, (
                    user_id,
                    vendedor_id,
                    direccion_envio,
                    ciudad_envio,
                    estado_envio,
                    codigo_postal_envio,
                    telefono_envio
                ))
                
                # Obtener valores de salida
                cursor.execute("SELECT @out_orden_id, @out_numero_orden")
                resultado = cursor.fetchone()
                
                if resultado and resultado[0]:
                    orden_id = resultado[0]
                    numero_orden = resultado[1]
                    
                    # Obtener total de la orden creada
                    cursor.execute("SELECT total FROM ordenes WHERE id = %s", (orden_id,))
                    orden_data = cursor.fetchone()
                    total = float(orden_data[0]) if orden_data else 0.0
                    
                    created_orders.append({
                        'orden_id': orden_id,
                        'numero_orden': numero_orden,
                        'vendedor_id': vendedor_id,
                        'total': total
                    })
            
            conn.commit()
        
        return json_response({
            'success': True,
            'message': f'{len(created_orders)} orden(es) creada(s)',
            'ordenes': created_orders
        }, 201)
        
    except Exception as e:
        return json_response({'error': f'Error al crear orden: {str(e)}'}, 500)


def get_order_details(orden_id):
    """Obtiene los detalles de una orden"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    user_id = current_user['user_id']
    rol = current_user['rol']
    
    # Obtener orden
    orden = execute_query(
        """SELECT o.*, 
                  uc.nombre as comprador_nombre, uc.email as comprador_email,
                  uv.nombre as vendedor_nombre, uv.email as vendedor_email
           FROM ordenes o
           INNER JOIN usuarios uc ON o.comprador_id = uc.id
           INNER JOIN usuarios uv ON o.vendedor_id = uv.id
           WHERE o.id = %s""",
        (orden_id,),
        fetch_one=True
    )
    
    if not orden:
        return json_response({'error': 'Orden no encontrada'}, 404)
    
    # Verificar permisos (solo comprador, vendedor o admin pueden ver)
    if rol != 'admin' and orden['comprador_id'] != user_id and orden['vendedor_id'] != user_id:
        return json_response({'error': 'No tienes permiso para ver esta orden'}, 403)
    
    # Obtener items de la orden
    items = execute_query(
        """SELECT * FROM orden_items WHERE orden_id = %s""",
        (orden_id,),
        fetch_all=True
    )

    # Normalizar tipos (Decimal, datetime) para que sean serializables a JSON
    def normalize_value(v):
        if isinstance(v, Decimal):
            return float(v)
        if isinstance(v, datetime):
            return v.isoformat()
        return v

    # Normalizar campos de la orden
    for key, value in list(orden.items()):
        orden[key] = normalize_value(value)

    # Normalizar campos de los items
    items_normalizados = []
    for it in items:
        nuevo = {}
        for k, v in it.items():
            nuevo[k] = normalize_value(v)
        items_normalizados.append(nuevo)

    orden['items'] = items_normalizados
    
    return json_response({'orden': orden})


def update_order_paypal(orden_id, paypal_order_id, paypal_payer_id, id_transaccion):
    """Actualiza una orden con información de PayPal y marca productos como vendidos"""
    current_user = get_current_user()
    
    if not current_user:
        return json_response({'error': 'No autorizado'}, 401)
    
    user_id = current_user['user_id']
    
    # Verificar que la orden pertenece al usuario
    orden = execute_query(
        "SELECT id, comprador_id FROM ordenes WHERE id = %s",
        (orden_id,),
        fetch_one=True
    )
    
    if not orden:
        return json_response({'error': 'Orden no encontrada'}, 404)
    
    if orden['comprador_id'] != user_id:
        return json_response({'error': 'No tienes permiso para actualizar esta orden'}, 403)
    
    try:
        with get_db_connection() as conn:
            cursor = conn.cursor()
            
            # Actualizar orden con datos de PayPal
            cursor.execute(
                """UPDATE ordenes 
                   SET paypal_order_id = %s,
                       paypal_payer_id = %s,
                       id_transaccion_paypal = %s,
                       estado_pago = 'completado',
                       estado = 'pago_confirmado',
                       fecha_pago = NOW(),
                       fecha_confirmacion = NOW()
                   WHERE id = %s""",
                (paypal_order_id, paypal_payer_id, id_transaccion, orden_id)
            )
            
            # Obtener productos de la orden y marcarlos como vendidos
            cursor.execute(
                "SELECT producto_id FROM orden_items WHERE orden_id = %s",
                (orden_id,)
            )
            productos = cursor.fetchall()
            
            # Marcar cada producto como vendido usando stored procedure
            for producto_row in productos:
                producto_id = producto_row[0]
                cursor.execute(
                    "CALL marcar_producto_vendido(%s, %s)",
                    (producto_id, orden_id)
                )
            
            conn.commit()
        
        return json_response({
            'success': True,
            'message': 'Orden actualizada con pago de PayPal'
        })
    except Exception as e:
        return json_response({'error': f'Error al actualizar orden: {str(e)}'}, 500)


def main():
    """Función principal - router de peticiones SIMPLIFICADO"""
    try:
        method = os.environ.get('REQUEST_METHOD', 'GET')
        path_info = os.environ.get('PATH_INFO', '')
        request_uri = os.environ.get('REQUEST_URI', '')
        
        # SIMPLIFICADO: Si es POST, asumir que es crear orden
        if method == 'POST':
            # Verificar si es create o cualquier POST a este script
            if '/create' in path_info or '/create' in request_uri or path_info == '' or 'create' in request_uri.lower():
                create_order_from_cart()
                return
        
        # GET para detalles
        if method == 'GET' and ('/details' in path_info or '/details' in request_uri):
            parts = path_info.split('/') if path_info else request_uri.split('/')
            orden_id = None
            for part in reversed(parts):
                if part.isdigit():
                    orden_id = int(part)
                    break
            if orden_id:
                get_order_details(orden_id)
                return
            else:
                json_response({'error': 'orden_id requerido'}, 400)
                return
        
        # PUT para actualizar PayPal
        if method == 'PUT' and ('/update_paypal' in path_info or '/update_paypal' in request_uri):
            data = get_request_data()
            orden_id = data.get('orden_id')
            paypal_order_id = data.get('paypal_order_id')
            paypal_payer_id = data.get('paypal_payer_id')
            id_transaccion = data.get('id_transaccion')
            
            if not all([orden_id, paypal_order_id]):
                json_response({'error': 'Datos incompletos'}, 400)
            else:
                update_order_paypal(orden_id, paypal_order_id, paypal_payer_id, id_transaccion)
            return
        
        # Por defecto: endpoint no encontrado
        json_response({'error': 'Endpoint no encontrado'}, 404)
        
    except Exception as e:
        import traceback
        error_details = traceback.format_exc()
        json_response({
            'error': 'Error interno del servidor: ' + str(e),
            'traceback': error_details
        }, 500)


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        import traceback
        # Asegurar que siempre devolvemos JSON válido
        try:
            error_details = traceback.format_exc()
            json_response({
                'success': False,
                'error': 'Error fatal: ' + str(e),
                'traceback': error_details
            }, 500)
        except:
            # Si incluso json_response falla, usar print básico
            print("Status: 500")
            print("Content-Type: application/json")
            print()
            print('{"success": false, "error": "Error fatal del servidor"}')
            sys.exit(1)
