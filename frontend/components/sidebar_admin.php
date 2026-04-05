<!-- Sidebar Admin Component -->
<aside class="sidebar-admin-component" style="background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); height: fit-content; position: sticky; top: 100px;">
    <div style="text-align: center; margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px solid #f0f0f0;">
        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #dc3545, #c82333); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; margin: 0 auto 1rem;">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h3 style="color: #dc3545; margin-bottom: 0.25rem;">Panel Admin</h3>
        <p style="color: #666; margin: 0; font-size: 0.9rem;">Administración del sistema</p>
    </div>
    
    <ul style="list-style: none; padding: 0; margin: 0;">
        <li style="margin-bottom: 0.5rem;">
            <a href="admin_dashboard.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-tachometer-alt" style="width: 20px;"></i>
                Dashboard
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="admin_usuarios.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-users" style="width: 20px;"></i>
                Usuarios
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="admin_productos.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-box" style="width: 20px;"></i>
                Productos
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="admin_reportes.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-chart-bar" style="width: 20px;"></i>
                Reportes
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="admin_guias.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-book" style="width: 20px;"></i>
                Guías
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="admin_ayuda.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-headset" style="width: 20px;"></i>
                Solicitudes de Ayuda
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="admin_configuracion.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-cog" style="width: 20px;"></i>
                Configuración
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="admin_procesos.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-flask" style="width: 20px;"></i>
                Procesos
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="index.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-home" style="width: 20px;"></i>
                Volver al Sitio
            </a>
        </li>
    </ul>
</aside>

<style>
    aside ul li a:hover,
    aside ul li a.active {
        background: #ffe5e5;
        color: #dc3545;
    }

    @media (max-width: 968px) {
        .sidebar-admin-component {
            position: static !important;
            padding: 1rem !important;
        }

        .sidebar-admin-component > div:first-child {
            display: none;
        }

        .sidebar-admin-component ul {
            display: flex !important;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .sidebar-admin-component ul li {
            margin-bottom: 0 !important;
            flex: 1;
            min-width: 100px;
        }

        .sidebar-admin-component ul li a {
            padding: 0.5rem 0.75rem !important;
            font-size: 0.85rem;
            justify-content: center;
            text-align: center;
            gap: 0.5rem !important;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop() || window.location.href.split('/').pop();
        const links = document.querySelectorAll('aside ul li a');
        links.forEach(link => {
            const href = link.getAttribute('href');
            // Comparar tanto el nombre completo como solo el archivo
            if (href === currentPage || href === window.location.pathname.split('/').pop()) {
                link.classList.add('active');
                link.style.background = '#ffe5e5';
                link.style.color = '#dc3545';
            }
        });
    });
</script>
