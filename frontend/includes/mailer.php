<?php
/**
 * Mailer — wrapper sobre PHPMailer con Mailtrap SMTP
 * Esfero Marketplace
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Crea y configura una instancia de PHPMailer con las credenciales
 * de Mailtrap leídas desde variables de entorno.
 */
function crear_mailer(): PHPMailer {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = getenv('MAIL_HOST')     ?: 'sandbox.smtp.mailtrap.io';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('MAIL_USERNAME') ?: '';
    $mail->Password   = getenv('MAIL_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)(getenv('MAIL_PORT') ?: 2525);
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 5;

    $mail->setFrom(
        getenv('MAIL_FROM_ADDRESS') ?: 'noreply@esfero.com',
        getenv('MAIL_FROM_NAME')    ?: 'Esfero Marketplace'
    );

    return $mail;
}

/**
 * Envía el correo de bienvenida al nuevo usuario.
 *
 * @param string $email    Dirección destino
 * @param string $nombre   Nombre del usuario
 * @return bool
 */
function enviar_bienvenida(string $email, string $nombre): bool {
    try {
        $mail = crear_mailer();
        $mail->addAddress($email, $nombre);
        $mail->isHTML(true);
        $mail->Subject = '¡Bienvenido a Esfero!';
        $mail->Body    = plantilla_bienvenida($nombre);
        $mail->AltBody = "Hola $nombre, gracias por registrarte en Esfero Marketplace. Ya puedes explorar miles de productos o activar tu cuenta de vendedor.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('mailer::bienvenida — ' . $e->getMessage());
        return false;
    }
}

/**
 * Envía la confirmación de compra al comprador.
 *
 * @param string $email          Dirección destino
 * @param string $nombre         Nombre del comprador
 * @param string $numero_orden   Número de orden (ej. ESF-ABC123-20260405)
 * @param float  $total          Total pagado
 * @param array  $items          Lista de productos [ ['titulo', 'cantidad', 'precio'] ]
 * @return bool
 */
function enviar_confirmacion_orden(string $email, string $nombre, string $numero_orden, float $total, array $items): bool {
    try {
        $mail = crear_mailer();
        $mail->addAddress($email, $nombre);
        $mail->isHTML(true);
        $mail->Subject = "Confirmación de tu orden $numero_orden — Esfero";
        $mail->Body    = plantilla_confirmacion_orden($nombre, $numero_orden, $total, $items);
        $mail->AltBody = "Hola $nombre, tu orden $numero_orden por $" . number_format($total, 2) . " MXN ha sido confirmada. Gracias por comprar en Esfero.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('mailer::confirmacion_orden — ' . $e->getMessage());
        return false;
    }
}

// ── Plantillas HTML ───────────────────────────────────────────────────────────

function plantilla_bienvenida(string $nombre): string {
    $nombre_safe = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    return <<<HTML
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <style>
        @media only screen and (max-width: 620px) {
          .email-wrapper { padding: 16px 0 !important; }
          .email-card { border-radius: 0 !important; }
          .email-body { padding: 24px 20px !important; }
          .email-footer { padding: 16px 20px !important; }
          .email-header { padding: 28px 20px !important; }
          .btn-cta { display: block !important; text-align: center !important; }
        }
      </style>
    </head>
    <body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
      <table width="100%" cellpadding="0" cellspacing="0" class="email-wrapper" style="background:#f4f6f9;padding:40px 0;">
        <tr><td align="center" style="padding:0 16px;">
          <table width="100%" cellpadding="0" cellspacing="0" class="email-card" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
            <!-- Header -->
            <tr>
              <td class="email-header" style="background:linear-gradient(135deg,#044E65,#0D87A8,#0C9268);padding:40px 40px 30px;text-align:center;">
                <h1 style="margin:0;color:#ffffff;font-size:28px;font-weight:800;letter-spacing:-0.5px;">Esfero</h1>
                <p style="margin:6px 0 0;color:rgba(255,255,255,0.85);font-size:14px;">Marketplace</p>
              </td>
            </tr>
            <!-- Body -->
            <tr>
              <td class="email-body" style="padding:40px;">
                <h2 style="margin:0 0 16px;color:#1a1a1a;font-size:22px;">¡Hola, {$nombre_safe}!</h2>
                <p style="margin:0 0 16px;color:#555;font-size:15px;line-height:1.7;">
                  Tu cuenta en Esfero ha sido creada correctamente. A partir de ahora puedes explorar
                  el catálogo, agregar productos a tu carrito y comprar de forma segura con PayPal.
                </p>
                <p style="margin:0 0 28px;color:#555;font-size:15px;line-height:1.7;">
                  Si quieres vender, activa tu cuenta de vendedor desde tu perfil en cualquier momento.
                </p>
                <a href="http://localhost:8080" class="btn-cta" style="display:inline-block;background:linear-gradient(135deg,#0D87A8,#0C9268);color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;">
                  Explorar el catálogo →
                </a>
              </td>
            </tr>
            <!-- Footer -->
            <tr>
              <td class="email-footer" style="background:#f8f9fa;padding:24px 40px;text-align:center;border-top:1px solid #eee;">
                <p style="margin:0;color:#999;font-size:12px;">
                  Esfero Marketplace · Este correo fue generado automáticamente, por favor no respondas a este mensaje.
                </p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}

function plantilla_confirmacion_orden(string $nombre, string $numero_orden, float $total, array $items): string {
    $nombre_safe = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    $orden_safe  = htmlspecialchars($numero_orden, ENT_QUOTES, 'UTF-8');

    $filas_items = '';
    foreach ($items as $item) {
        $titulo   = htmlspecialchars($item['titulo']   ?? '', ENT_QUOTES, 'UTF-8');
        $cantidad = (int)($item['cantidad'] ?? 1);
        $precio   = number_format((float)($item['precio'] ?? 0), 2);
        $filas_items .= <<<ROW
        <tr>
          <td style="padding:10px 0;color:#333;font-size:14px;border-bottom:1px solid #f0f0f0;">{$titulo}</td>
          <td style="padding:10px 0;color:#666;font-size:14px;text-align:center;border-bottom:1px solid #f0f0f0;">{$cantidad}</td>
          <td style="padding:10px 0;color:#333;font-size:14px;text-align:right;border-bottom:1px solid #f0f0f0;">\${$precio} MXN</td>
        </tr>
        ROW;
    }

    $total_fmt = number_format($total, 2);

    return <<<HTML
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <style>
        @media only screen and (max-width: 620px) {
          .email-wrapper { padding: 16px 0 !important; }
          .email-card { border-radius: 0 !important; }
          .email-body { padding: 24px 20px !important; }
          .email-footer { padding: 16px 20px !important; }
          .email-header { padding: 28px 20px !important; }
          .btn-cta { display: block !important; text-align: center !important; }
        }
      </style>
    </head>
    <body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
      <table width="100%" cellpadding="0" cellspacing="0" class="email-wrapper" style="background:#f4f6f9;padding:40px 0;">
        <tr><td align="center" style="padding:0 16px;">
          <table width="100%" cellpadding="0" cellspacing="0" class="email-card" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
            <!-- Header -->
            <tr>
              <td class="email-header" style="background:linear-gradient(135deg,#044E65,#0D87A8,#0C9268);padding:40px 40px 30px;text-align:center;">
                <h1 style="margin:0;color:#ffffff;font-size:28px;font-weight:800;letter-spacing:-0.5px;">Esfero</h1>
                <p style="margin:6px 0 0;color:rgba(255,255,255,0.85);font-size:14px;">Confirmación de compra</p>
              </td>
            </tr>
            <!-- Body -->
            <tr>
              <td class="email-body" style="padding:40px;">
                <h2 style="margin:0 0 8px;color:#1a1a1a;font-size:22px;">¡Gracias por tu compra, {$nombre_safe}!</h2>
                <p style="margin:0 0 24px;color:#666;font-size:14px;">Orden: <strong style="color:#0D87A8;">{$orden_safe}</strong></p>

                <!-- Tabla de productos -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                  <thead>
                    <tr>
                      <th style="text-align:left;font-size:12px;color:#999;text-transform:uppercase;padding-bottom:8px;border-bottom:2px solid #eee;">Producto</th>
                      <th style="text-align:center;font-size:12px;color:#999;text-transform:uppercase;padding-bottom:8px;border-bottom:2px solid #eee;">Cant.</th>
                      <th style="text-align:right;font-size:12px;color:#999;text-transform:uppercase;padding-bottom:8px;border-bottom:2px solid #eee;">Precio</th>
                    </tr>
                  </thead>
                  <tbody>
                    {$filas_items}
                  </tbody>
                </table>

                <!-- Total -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
                  <tr>
                    <td style="font-size:16px;font-weight:700;color:#1a1a1a;">Total pagado</td>
                    <td style="font-size:18px;font-weight:800;color:#0C9268;text-align:right;">\${$total_fmt} MXN</td>
                  </tr>
                </table>

                <p style="margin:0 0 28px;color:#555;font-size:14px;line-height:1.7;">
                  Puedes revisar el estado de tu orden en cualquier momento desde tu historial de compras.
                </p>
                <a href="http://localhost:8080/compras.php" class="btn-cta" style="display:inline-block;background:linear-gradient(135deg,#0D87A8,#0C9268);color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;">
                  Ver mis compras →
                </a>
              </td>
            </tr>
            <!-- Footer -->
            <tr>
              <td class="email-footer" style="background:#f8f9fa;padding:24px 40px;text-align:center;border-top:1px solid #eee;">
                <p style="margin:0;color:#999;font-size:12px;">
                  Esfero Marketplace · Este correo fue generado automáticamente, por favor no respondas a este mensaje.
                </p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}
