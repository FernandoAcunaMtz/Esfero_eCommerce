<?php
// Habilitar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Cargar conexión a la base de datos y funciones helper
try {
    require_once __DIR__ . '/includes/db_connection.php';
    require_once __DIR__ . '/includes/sanitize.php';
} catch (Exception $e) {
    error_log("Error al cargar includes en ayuda.php: " . $e->getMessage());
    // Continuar aunque falle la BD (para ver si es el problema)
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener usuario actual si está logueado
$usuario_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$usuario_nombre = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre']) : '';
$usuario_email = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : '';

// Obtener FAQs por categoría
$faqs = [];
try {
    if (isset($pdo) && $pdo !== null) {
        $stmt = $pdo->query("
            SELECT * FROM ayuda_faqs 
            WHERE activo = 1 
            ORDER BY categoria, orden, pregunta
        ");
        if ($stmt) {
            $faqs_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Organizar FAQs por categoría
            if (is_array($faqs_raw)) {
                foreach ($faqs_raw as $faq) {
                    $categoria = $faq['categoria'];
                    if (!isset($faqs[$categoria])) {
                        $faqs[$categoria] = [];
                    }
                    $faqs[$categoria][] = $faq;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Error al obtener FAQs: " . $e->getMessage());
    $faqs = [];
}

// Mensajes de éxito o error del formulario
$mensaje_formulario = '';
$tipo_mensaje = '';
if (isset($_GET['success'])) {
    $mensaje_formulario = 'Tu solicitud ha sido enviada exitosamente. Te responderemos pronto.';
    $tipo_mensaje = 'success';
    if (isset($_GET['ticket'])) {
        $mensaje_formulario .= ' Tu número de ticket es: ' . htmlspecialchars($_GET['ticket']);
    }
}
if (isset($_GET['error'])) {
    $mensaje_formulario = 'Error: ' . htmlspecialchars($_GET['error']);
    $tipo_mensaje = 'error';
}

// Nombres de categorías
$categoria_nombres = [
    'comprar' => 'Comprar',
    'vender' => 'Vender',
    'envios' => 'Envíos',
    'pagos' => 'Pagos',
    'cuenta' => 'Mi Cuenta',
    'seguridad' => 'Seguridad',
    'general' => 'General'
];

// Iconos por categoría
$categoria_iconos = [
    'comprar' => 'fa-shopping-bag',
    'vender' => 'fa-store',
    'envios' => 'fa-truck',
    'pagos' => 'fa-credit-card',
    'cuenta' => 'fa-user-circle',
    'seguridad' => 'fa-shield-alt',
    'general' => 'fa-info-circle'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#044E65">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png">
    <script>if('serviceWorker'in navigator)navigator.serviceWorker.register('/sw.js');</script>
    <title>Centro de Ayuda - Esfero</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .faq-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .faq-item {
            margin-bottom: 1rem;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 1rem;
        }
        .faq-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .faq-question {
            font-weight: 600;
            color: #0D87A8;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .faq-question:hover {
            background: #e9ecef;
        }
        .faq-question.active {
            background: #e7f3ff;
            color: #0C9268;
        }
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            padding: 0 1rem;
            color: #666;
            line-height: 1.6;
        }
        .faq-answer.active {
            max-height: 500px;
            padding: 1rem;
            margin-top: 0.5rem;
        }
        .faq-icon {
            transition: transform 0.3s ease;
        }
        .faq-question.active .faq-icon {
            transform: rotate(180deg);
        }
        .form-contacto {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #0D87A8;
            font-weight: 600;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            box-sizing: border-box;
        }
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        .btn-submit {
            background: linear-gradient(135deg, #0D87A8, #0C9268);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 166, 118, 0.3);
        }
        .mensaje-alerta {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .mensaje-alerta.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .mensaje-alerta.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .categoria-titulo {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #0C9268;
        }
        .categoria-icono {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #0D87A8, #0C9268);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <!-- Hero -->
    <section class="page-hero" style="background: linear-gradient(135deg, #044E65 0%, #0D87A8 50%, #0C9268 100%); padding: 6rem 0 4rem; text-align: center; color: white;">
        <div class="container">
            <h1 style="font-size: clamp(1.5rem, 4vw, 2.5rem); margin-bottom: 1rem;">Centro de Ayuda</h1>
            <p style="font-size: 1.2rem; opacity: 0.9; margin-bottom: 2rem;">¿En qué podemos ayudarte?</p>
        </div>
    </section>

    <!-- Preguntas Frecuentes -->
    <section class="sections" style="padding: 4rem 0; background: var(--c-bg, #F2F9FB);">
        <div class="container">
            <h2 style="font-size: 2rem; margin-bottom: 2rem; text-align: center; color: #0D87A8;">Preguntas Frecuentes</h2>
            
            <?php if (empty($faqs)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <p>No hay preguntas frecuentes disponibles en este momento.</p>
                </div>
            <?php else: 
                foreach ($faqs as $categoria => $faqs_categoria):
                    if (empty($faqs_categoria)) continue;
            ?>
                <div class="faq-section">
                    <div class="categoria-titulo">
                        <div class="categoria-icono">
                            <i class="fas <?php echo $categoria_iconos[$categoria] ?? 'fa-info-circle'; ?>"></i>
                        </div>
                        <h3 style="font-size: 1.5rem; color: #0D87A8; margin: 0;">
                            <?php echo $categoria_nombres[$categoria] ?? ucfirst($categoria); ?>
                        </h3>
                    </div>
                    
                    <?php foreach ($faqs_categoria as $faq): ?>
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(<?php echo (int)$faq['id']; ?>)">
                                <span><?php echo htmlspecialchars($faq['pregunta']); ?></span>
                                <i class="fas fa-chevron-down faq-icon"></i>
                            </div>
                            <div class="faq-answer" id="faq-answer-<?php echo (int)$faq['id']; ?>">
                                <?php echo nl2br(htmlspecialchars($faq['respuesta'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php 
                endforeach;
            endif; ?>
            
            <!-- Formulario de Contacto -->
            <div class="form-contacto">
                <h2 style="font-size: 2rem; margin-bottom: 1rem; color: #0D87A8; text-align: center;">
                    ¿No encontraste lo que buscabas?
                </h2>
                <p style="text-align: center; color: #666; margin-bottom: 2rem;">
                    Envíanos tu consulta y nuestro equipo te responderá pronto
                </p>
                
                <?php if ($mensaje_formulario): ?>
                    <div class="mensaje-alerta <?php echo $tipo_mensaje; ?>">
                        <?php echo $mensaje_formulario; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="process_ayuda.php" id="formContacto">
                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required 
                               value="<?php echo htmlspecialchars($usuario_nombre); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($usuario_email); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono">Teléfono (opcional)</label>
                        <input type="tel" id="telefono" name="telefono">
                    </div>
                    
                    <div class="form-group">
                        <label for="categoria">Categoría *</label>
                        <select id="categoria" name="categoria" required>
                            <option value="general">General</option>
                            <option value="comprar">Comprar</option>
                            <option value="vender">Vender</option>
                            <option value="envios">Envíos</option>
                            <option value="pagos">Pagos</option>
                            <option value="cuenta">Mi Cuenta</option>
                            <option value="seguridad">Seguridad</option>
                            <option value="reporte">Reportar un Problema</option>
                            <option value="reembolso">Reembolso</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="asunto">Asunto *</label>
                        <input type="text" id="asunto" name="asunto" required 
                               placeholder="Resumen breve de tu consulta">
                    </div>
                    
                    <div class="form-group">
                        <label for="mensaje">Mensaje *</label>
                        <textarea id="mensaje" name="mensaje" required 
                                  placeholder="Describe tu consulta o problema en detalle..."></textarea>
                    </div>
                    
                    <input type="hidden" name="usuario_id" value="<?php echo $usuario_id ?: ''; ?>">
                    
                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Enviar Solicitud
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <?php include 'components/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        function toggleFaq(id) {
            const answer = document.getElementById('faq-answer-' + id);
            const question = answer.previousElementSibling;
            
            // Cerrar otras FAQs de la misma sección
            const section = answer.closest('.faq-section');
            const allAnswers = section.querySelectorAll('.faq-answer');
            const allQuestions = section.querySelectorAll('.faq-question');
            
            allAnswers.forEach(function(item) {
                if (item.id !== 'faq-answer-' + id) {
                    item.classList.remove('active');
                }
            });
            
            allQuestions.forEach(function(item) {
                if (item !== question) {
                    item.classList.remove('active');
                }
            });
            
            // Toggle la FAQ actual
            answer.classList.toggle('active');
            question.classList.toggle('active');
        }
    </script>
</body>
</html>
