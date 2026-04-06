<?php
// Inicializar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar funciones de autenticación
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/db_connection.php';

// Obtener usuario actual
$current_user = get_session_user();
$is_logged_in = is_logged_in();
?>
<script>document.documentElement.classList.add('navbar-loaded');</script>
<!-- safe-area teal fill — aplica en todas las páginas vía navbar -->
<style>
  body::before {
    content: '';
    position: fixed;
    top: 0; left: 0; right: 0;
    height: env(safe-area-inset-top, 0px);
    background: #044E65;
    z-index: 10002;
    pointer-events: none;
  }
  body { background-color: #EEF8FA; }
</style>
<!-- Navbar -->
<nav class="navbar" id="navbar">
    <div class="nav-container">
        <!-- Logo -->
        <div class="navbar-brand">
            <a href="index.php" class="logo" aria-label="Esfero – Inicio">
                <img src="/assets/img/logo-white.svg" alt="Esfero" class="logo-img logo-img--white">
                <img src="/assets/img/logo-color.svg" alt="Esfero" class="logo-img logo-img--color">
            </a>
        </div>
        
        <!-- Menú de navegación desktop -->
        <ul class="navbar-nav">
            <?php 
            $current_page = basename($_SERVER['PHP_SELF']);
            $is_index = ($current_page === 'index.php');
            ?>
            <li><a href="index.php">Inicio</a></li>
            <li><a href="categorias.php">Categorías</a></li>
            <li><a href="<?php echo $is_index ? '#destacados' : 'productos.php?destacados=true'; ?>">Destacados</a></li>
            <li><a href="<?php echo $is_index ? 'productos.php?recientes=true' : 'productos.php?recientes=true'; ?>">Recientes</a></li>
            <li><a href="<?php echo $is_index ? '#blog' : 'guias.php'; ?>">Guías</a></li>
            <li><a href="ayuda.php">Ayuda</a></li>
        </ul>
        
        <!-- Elementos del usuario -->
        <div class="navbar-user">
            <?php if ($is_logged_in): ?>
                <?php if (!is_admin()): 
                    // Obtener contadores dinámicos
                    $favoritos_count = 0;
                    $carrito_count = 0;
                    if (function_exists('get_favoritos_count') && function_exists('get_carrito_count')) {
                        $user_id = $current_user['id'] ?? null;
                        if ($user_id) {
                            $favoritos_count = get_favoritos_count($user_id);
                            $carrito_count = get_carrito_count($user_id);
                        }
                    }
                ?>
                <a href="favoritos.php" class="nav-icon" title="Favoritos" id="navFavoritos">
                    <i class="far fa-heart"></i>
                    <span class="badge" id="favoritosBadge" style="<?php echo $favoritos_count > 0 ? '' : 'display: none;'; ?>"><?php echo $favoritos_count > 0 ? $favoritos_count : ''; ?></span>
                </a>
                <a href="carrito.php" class="nav-icon" title="Carrito" id="navCarrito">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="badge" id="carritoBadge" style="<?php echo $carrito_count > 0 ? '' : 'display: none;'; ?>"><?php echo $carrito_count > 0 ? $carrito_count : ''; ?></span>
                </a>
                <?php endif; ?>
                <div class="user-menu">
                    <button class="user-icon" onclick="toggleUserMenu()" title="Mi cuenta">
                        <?php if (!empty($current_user['foto_perfil'])): ?>
                            <img src="<?php echo htmlspecialchars($current_user['foto_perfil']); ?>" alt="Avatar" style="width: 32px; height: 32px; border-radius: 50%;">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="dropdown-header">
                            <div class="dropdown-header-avatar">
                                <?php if (!empty($current_user['foto_perfil'])): ?>
                                    <img src="<?php echo htmlspecialchars($current_user['foto_perfil']); ?>" alt="Avatar">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-header-info">
                                <strong><?php echo htmlspecialchars($current_user['nombre_completo'] ?? $current_user['nombre'] ?? 'Usuario'); ?></strong>
                                <small><?php echo htmlspecialchars($current_user['email'] ?? ''); ?></small>
                            </div>
                        </div>
                        <?php if (!is_admin()): ?>
                        <a href="perfil.php" class="dropdown-item">
                            <i class="fas fa-user-circle"></i>
                            Mi perfil
                        </a>
                        <a href="perfil.php?tab=compras" class="dropdown-item">
                            <i class="fas fa-shopping-bag"></i>
                            Mis compras
                        </a>
                        <?php endif; ?>
                        <?php if (is_vendedor()): ?>
                            <a href="perfil.php?tab=ventas" class="dropdown-item">
                                <i class="fas fa-store"></i>
                                Mis ventas
                            </a>
                        <?php endif; ?>
                        <?php if ($current_user['rol'] === 'admin'): ?>
                            <a href="admin_dashboard.php" class="dropdown-item">
                                <i class="fas fa-gauge-high"></i>
                                Panel de Admin
                            </a>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a href="perfil.php?tab=configuracion" class="dropdown-item">
                            <i class="fas fa-sliders"></i>
                            Configuración
                        </a>
                        <a href="logout.php" class="dropdown-item danger">
                            <i class="fas fa-arrow-right-from-bracket"></i>
                            Cerrar sesión
                        </a>
                    </div>
                </div>
                <?php if (!is_admin()): ?>
                    <?php if (is_vendedor()): ?>
                        <a href="publicar_producto.php" class="btn-vender">Vender</a>
                    <?php else: ?>
                        <a href="activar_vendedor.php" class="btn-vender" style="background: linear-gradient(135deg, #F97316, #e8630a);" title="Activa tu cuenta de vendedor">Vender</a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <a href="login.php" class="btn-login">Iniciar Sesión</a>
                <a href="registro.php" class="btn-register">Registrarse</a>
            <?php endif; ?>
        </div>
        
        <!-- Menú hamburguesa móvil -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Menú" type="button">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <!-- Menú móvil -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <a href="index.php" class="logo" aria-label="Esfero – Inicio">
                <img src="/assets/img/logo-white.svg" alt="Esfero" class="logo-img" style="height:36px;">
            </a>
            <button class="mobile-menu-close" id="mobileMenuClose" aria-label="Cerrar menú">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <ul class="mobile-menu-nav">
            <?php 
            $current_page = basename($_SERVER['PHP_SELF']);
            $is_index = ($current_page === 'index.php');
            ?>
            <li><a href="index.php" onclick="closeMobileMenu()">Inicio</a></li>
            <li><a href="categorias.php" onclick="closeMobileMenu()">Categorías</a></li>
            <li><a href="<?php echo $is_index ? '#destacados' : 'productos.php?destacados=true'; ?>" onclick="closeMobileMenu()">Destacados</a></li>
            <li><a href="productos.php?recientes=true" onclick="closeMobileMenu()">Recientes</a></li>
            <li><a href="<?php echo $is_index ? '#blog' : 'guias.php'; ?>" onclick="closeMobileMenu()">Guías</a></li>
            <li><a href="ayuda.php" onclick="closeMobileMenu()">Ayuda</a></li>
        </ul>
        <div class="mobile-menu-user">
            <?php if ($is_logged_in): ?>
                <div style="padding: 1rem 1.5rem; border-top: 1px solid #eee;">
                    <p style="font-weight: 600; color: #004E64; margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($current_user['nombre_completo'] ?? $current_user['nombre'] ?? 'Usuario'); ?>
                    </p>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">
                        <?php echo htmlspecialchars($current_user['email'] ?? ''); ?>
                    </p>
                    <?php if (!is_admin()): ?>
                    <a href="favoritos.php" class="btn-register" style="display: block; text-align: center; margin-bottom: 0.5rem;" onclick="closeMobileMenu()">Favoritos</a>
                    <a href="carrito.php" class="btn-register" style="display: block; text-align: center; margin-bottom: 0.5rem;" onclick="closeMobileMenu()">Carrito</a>
                    <?php endif; ?>
                    <a href="perfil.php" class="btn-register" style="display: block; text-align: center; margin-bottom: 0.5rem;" onclick="closeMobileMenu()">Mi Perfil</a>
                    <?php if (is_vendedor()): ?>
                    <a href="publicar_producto.php" class="btn-register" style="display: block; text-align: center; margin-bottom: 0.5rem;" onclick="closeMobileMenu()">Vender</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-login" style="display: block; text-align: center;" onclick="closeMobileMenu()">Cerrar Sesión</a>
                </div>
            <?php else: ?>
                <div style="padding: 1rem 1.5rem;">
                    <a href="login.php" class="btn-login" style="display: block; text-align: center; margin-bottom: 0.5rem;" onclick="closeMobileMenu()">Iniciar Sesión</a>
                    <a href="registro.php" class="btn-register" style="display: block; text-align: center;" onclick="closeMobileMenu()">Registrarse</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
// Funcionalidad del menú móvil
function toggleMobileMenu() {
    // Verificar si estamos en desktop
    if (window.innerWidth > 768) {
        return; // No hacer nada en desktop
    }
    
    const overlay = document.getElementById('mobileMenuOverlay');
    const menu = document.getElementById('mobileMenu');
    const body = document.body;
    
    if (!overlay || !menu) {
        console.error('No se encontraron los elementos del menú móvil');
        return;
    }
    
    if (menu.classList.contains('active')) {
        closeMobileMenu();
    } else {
        // Remover estilos inline que puedan estar bloqueando
        overlay.style.display = '';
        overlay.style.visibility = '';
        menu.style.display = '';
        menu.style.visibility = '';

        overlay.classList.add('active');
        menu.classList.add('active');
        body.style.overflow = 'hidden';

        // Ocultar el botón hamburguesa mientras el menú está abierto
        var toggle = document.getElementById('mobileMenuToggle');
        if (toggle) toggle.style.visibility = 'hidden';
    }
}

function closeMobileMenu() {
    // Verificar si estamos en desktop
    if (window.innerWidth > 768) {
        return; // No hacer nada en desktop
    }
    
    const overlay = document.getElementById('mobileMenuOverlay');
    const menu = document.getElementById('mobileMenu');
    const body = document.body;
    
    if (!overlay || !menu) return;
    
    overlay.classList.remove('active');
    menu.classList.remove('active');
    body.style.overflow = '';

    // Restaurar visibilidad del botón hamburguesa
    var toggle = document.getElementById('mobileMenuToggle');
    if (toggle) toggle.style.visibility = '';
    
    // Asegurar que se oculten correctamente
    setTimeout(function() {
        if (!menu.classList.contains('active')) {
            overlay.style.display = '';
            overlay.style.visibility = '';
            menu.style.display = '';
            menu.style.visibility = '';
        }
    }, 300); // Después de la transición
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('mobileMenuOverlay');
    const menu = document.getElementById('mobileMenu');
    
    // Asegurar que el mobile menu esté oculto al cargar
    if (overlay) {
        overlay.classList.remove('active');
    }
    if (menu) {
        menu.classList.remove('active');
    }
    
    // En desktop, forzar ocultamiento
    if (window.innerWidth > 768) {
        if (overlay) {
            overlay.style.display = 'none';
            overlay.style.visibility = 'hidden';
        }
        if (menu) {
            menu.style.display = 'none';
            menu.style.visibility = 'hidden';
        }
    }
    
    const toggle = document.getElementById('mobileMenuToggle');
    const close = document.getElementById('mobileMenuClose');
    // overlay ya está declarado arriba, no redeclarar
    
    if (toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (window.innerWidth <= 768) {
                toggleMobileMenu();
            }
        });
    }
    
    if (close) {
        close.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeMobileMenu();
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay && window.innerWidth <= 768) {
                closeMobileMenu();
            }
        });
    }
    
    // Cerrar con Escape (solo en móvil)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && window.innerWidth <= 768) {
            closeMobileMenu();
        }
    });
    
    // Cerrar mobile menu al redimensionar a desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeMobileMenu();
        }
    });
    
    // Función para actualizar contadores del navbar
    function updateNavbarCounters() {
        <?php if ($is_logged_in && !is_admin()): ?>
        // Actualizar contador de favoritos y carrito
        fetch('api_get_counters.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const favoritosBadge = document.getElementById('favoritosBadge');
                    const carritoBadge = document.getElementById('carritoBadge');
                    
                    if (favoritosBadge) {
                        if (data.favoritos > 0) {
                            favoritosBadge.textContent = data.favoritos;
                            favoritosBadge.style.display = '';
                        } else {
                            favoritosBadge.textContent = '';
                            favoritosBadge.style.display = 'none';
                        }
                    }
                    
                    if (carritoBadge) {
                        if (data.carrito > 0) {
                            carritoBadge.textContent = data.carrito;
                            carritoBadge.style.display = '';
                        } else {
                            carritoBadge.textContent = '';
                            carritoBadge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error al actualizar contadores:', error);
            });
        <?php endif; ?>
    }
    
    // Actualizar contadores al cargar la página
    updateNavbarCounters();
    
    // Exponer función globalmente para que otras páginas puedan actualizar los contadores
    window.updateNavbarCounters = updateNavbarCounters;
});
</script>

