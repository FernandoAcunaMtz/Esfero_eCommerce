/**
 * Integración de PayPal para Esfero Marketplace
 * Maneja la creación y captura de pagos con PayPal
 */

// Configuración
const PAYPAL_API_BASE = '/backend/cgi-bin/paypal.py';

/**
 * Crea una orden de pago en PayPal
 * @param {number} ordenId - ID de la orden en la base de datos
 * @param {number} total - Monto total a pagar
 * @returns {Promise<Object>} - Objeto con approval_url o error
 */
async function crearOrdenPayPal(ordenId, total) {
    try {
        // Obtener token de autenticación
        const token = localStorage.getItem('access_token') || sessionStorage.getItem('access_token');
        
        if (!token) {
            throw new Error('No hay sesión activa. Por favor, inicia sesión.');
        }

        // Mostrar indicador de carga
        mostrarCargandoPayPal(true);

        const formData = new FormData();
        formData.append('orden_id', ordenId);
        formData.append('token', token);
        
        // Obtener URLs de retorno desde la configuración
        const returnUrl = window.location.origin + '/esfero/frontend/checkout.php?success=true';
        const cancelUrl = window.location.origin + '/esfero/frontend/checkout.php?canceled=true';
        
        formData.append('return_url', returnUrl);
        formData.append('cancel_url', cancelUrl);
        
        const response = await fetch(`${PAYPAL_API_BASE}/create`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        mostrarCargandoPayPal(false);
        
        if (data.success && data.approval_url) {
            // Guardar información de la orden para después
            sessionStorage.setItem('paypal_order_id', data.paypal_order_id);
            sessionStorage.setItem('orden_id', ordenId);
            
            // Redirigir a PayPal para que el usuario complete el pago
            window.location.href = data.approval_url;
        } else {
            console.error('Error creando orden:', data.error || data);
            mostrarErrorPayPal(data.error || 'Error al crear la orden de pago');
            return { error: data.error || 'Error desconocido' };
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarCargandoPayPal(false);
        mostrarErrorPayPal('Error de conexión. Por favor, intenta de nuevo.');
        return { error: error.message };
    }
}

/**
 * Captura el pago de una orden de PayPal
 * @param {string} paypalOrderId - ID de la orden de PayPal
 * @returns {Promise<Object>} - Resultado de la captura
 */
async function capturarPagoPayPal(paypalOrderId) {
    try {
        const token = localStorage.getItem('access_token') || sessionStorage.getItem('access_token');
        
        if (!token) {
            throw new Error('No hay sesión activa');
        }

        mostrarCargandoPayPal(true);

        const formData = new FormData();
        formData.append('paypal_order_id', paypalOrderId);
        formData.append('token', token);
        
        const response = await fetch(`${PAYPAL_API_BASE}/capture`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        mostrarCargandoPayPal(false);
        
        if (data.success) {
            // Limpiar datos de sesión
            sessionStorage.removeItem('paypal_order_id');
            
            // Mostrar mensaje de éxito
            mostrarExitoPayPal('¡Pago completado exitosamente!');
            
            // Redirigir a página de confirmación después de 2 segundos
            setTimeout(() => {
                const ordenId = sessionStorage.getItem('orden_id');
                if (ordenId) {
                    window.location.href = `orden.php?id=${ordenId}`;
                } else {
                    window.location.href = 'compras.php';
                }
            }, 2000);
            
            return data;
        } else {
            console.error('Error capturando pago:', data.error || data);
            mostrarErrorPayPal(data.error || 'Error al procesar el pago');
            return { error: data.error || 'Error desconocido' };
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarCargandoPayPal(false);
        mostrarErrorPayPal('Error al procesar el pago. Por favor, intenta de nuevo.');
        return { error: error.message };
    }
}

/**
 * Verifica el estado de una orden de PayPal
 * @param {string} paypalOrderId - ID de la orden de PayPal
 * @returns {Promise<Object>} - Estado de la orden
 */
async function verificarOrdenPayPal(paypalOrderId) {
    try {
        const token = localStorage.getItem('access_token') || sessionStorage.getItem('access_token');
        
        if (!token) {
            throw new Error('No hay sesión activa');
        }

        const formData = new FormData();
        formData.append('paypal_order_id', paypalOrderId);
        formData.append('token', token);
        
        const response = await fetch(`${PAYPAL_API_BASE}/verify?paypal_order_id=${paypalOrderId}`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error verificando orden:', error);
        return { error: error.message };
    }
}

/**
 * Maneja el retorno desde PayPal después del pago
 */
function manejarRetornoPayPal() {
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const token = urlParams.get('token'); // PayPal order ID
    const payerId = urlParams.get('PayerID');
    
    if (success === 'true' && token) {
        // Capturar el pago automáticamente
        capturarPagoPayPal(token);
    } else if (urlParams.get('canceled') === 'true') {
        mostrarErrorPayPal('El pago fue cancelado. Puedes intentar de nuevo cuando estés listo.');
    }
}

/**
 * Muestra/oculta el indicador de carga
 */
function mostrarCargandoPayPal(mostrar) {
    let loader = document.getElementById('paypal-loader');
    
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'paypal-loader';
        loader.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            color: white;
            font-size: 1.2rem;
        `;
        loader.innerHTML = `
            <div style="text-align: center;">
                <div style="border: 4px solid #f3f3f3; border-top: 4px solid #0070ba; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 1rem;"></div>
                <p>Procesando pago con PayPal...</p>
            </div>
        `;
        document.body.appendChild(loader);
        
        // Agregar animación de spin
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }
    
    loader.style.display = mostrar ? 'flex' : 'none';
}

/**
 * Muestra un mensaje de error
 */
function mostrarErrorPayPal(mensaje) {
    const alert = document.createElement('div');
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #dc3545;
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10001;
        max-width: 400px;
        animation: slideIn 0.3s ease;
    `;
    alert.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-exclamation-circle"></i>
            <span>${mensaje}</span>
        </div>
    `;
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}

/**
 * Muestra un mensaje de éxito
 */
function mostrarExitoPayPal(mensaje) {
    const alert = document.createElement('div');
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10001;
        max-width: 400px;
        animation: slideIn 0.3s ease;
    `;
    alert.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-check-circle"></i>
            <span>${mensaje}</span>
        </div>
    `;
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => alert.remove(), 300);
    }, 3000);
}

// Ejecutar automáticamente al cargar si viene de PayPal
if (window.location.search.includes('success=true') || window.location.search.includes('canceled=true')) {
    document.addEventListener('DOMContentLoaded', manejarRetornoPayPal);
}

// Exportar funciones para uso global
window.crearOrdenPayPal = crearOrdenPayPal;
window.capturarPagoPayPal = capturarPagoPayPal;
window.verificarOrdenPayPal = verificarOrdenPayPal;

console.log('✅ PayPal JS cargado correctamente');

