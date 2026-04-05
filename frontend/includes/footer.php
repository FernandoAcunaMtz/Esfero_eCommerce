<?php
// Detectar página actual para enlaces inteligentes
$current_page = basename($_SERVER['PHP_SELF']);
$is_index = ($current_page === 'index.php');
?>
<!-- Footer -->
<footer class="footer" id="contacto" data-parallax-speed="0.08">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Esfero</h3>
                <p>Tu marketplace de confianza en México. Compra y vende con protección al comprador.</p>
                <div class="social-links">
                    <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://twitter.com" target="_blank" rel="noopener noreferrer" aria-label="Twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Explora</h3>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li><a href="<?php echo $is_index ? '#categorias' : 'categorias.php'; ?>">Categorías</a></li>
                    <li><a href="<?php echo $is_index ? '#destacados' : 'productos.php?destacados=true'; ?>">Productos destacados</a></li>
                    <li><a href="<?php echo $is_index ? '#por-que-esfero' : 'index.php#por-que-esfero'; ?>">¿Por qué Esfero?</a></li>
                    <li><a href="<?php echo $is_index ? '#blog' : 'guias.php'; ?>">Guías y Consejos</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Ayuda</h3>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li><a href="<?php echo $is_index ? '#contacto' : 'ayuda.php'; ?>">Centro de ayuda</a></li>
                    <li><a href="<?php echo $is_index ? '#vende-pasos' : 'index.php#vende-pasos'; ?>">Cómo funciona</a></li>
                    <li><a href="ayuda.php">Envíos y devoluciones</a></li>
                    <li><a href="ayuda.php">Seguridad</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Pagos y envíos</h3>
                <p>Pagos seguros y envíos a todo México.</p>
                <div style="display:flex; gap:0.5rem; flex-wrap: wrap; align-items:center; margin-top: 0.75rem;">
                    <span style="background:rgba(255,255,255,0.1); padding:0.35rem 0.6rem; border-radius:8px; font-size:0.85rem;">Visa</span>
                    <span style="background:rgba(255,255,255,0.1); padding:0.35rem 0.6rem; border-radius:8px; font-size:0.85rem;">Mastercard</span>
                    <span style="background:rgba(255,255,255,0.1); padding:0.35rem 0.6rem; border-radius:8px; font-size:0.85rem;">OXXO</span>
                    <span style="background:rgba(255,255,255,0.1); padding:0.35rem 0.6rem; border-radius:8px; font-size:0.85rem;">Transferencia</span>
                </div>
            </div>
        </div>
        <div style="text-align: center; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1);">
            <p style="margin: 0; opacity: 0.9;">&copy; <?php echo date('Y'); ?> Esfero México. Todos los derechos reservados.</p>
        </div>
    </div>
</footer>
