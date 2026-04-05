<!-- Sidebar Vendedor Component -->
<aside class="sidebar-vendedor-component" style="background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); height: fit-content; position: sticky; top: 100px;">
    <div style="text-align: center; margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px solid #f0f0f0;">
        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #004E64, #00A676); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; margin: 0 auto 1rem;">
            <i class="fas fa-store"></i>
        </div>
        <h3 style="color: #004E64; margin-bottom: 0.25rem;">Panel Vendedor</h3>
        <p style="color: #666; margin: 0; font-size: 0.9rem;">Gestiona tus productos y ventas</p>
    </div>
    
    <ul style="list-style: none; padding: 0; margin: 0;">
        <li style="margin-bottom: 0.5rem;">
            <a href="vendedor_dashboard.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-tachometer-alt" style="width: 20px;"></i>
                Dashboard
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="publicar_producto.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-plus-circle" style="width: 20px;"></i>
                Publicar Producto
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="mis_productos.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-box" style="width: 20px;"></i>
                Mis Productos
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="ventas.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-chart-line" style="width: 20px;"></i>
                Mis Ventas
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="mensajes.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-comments" style="width: 20px;"></i>
                Mensajes
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="resenas.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;">
                <i class="fas fa-star" style="width: 20px;"></i>
                Reseñas
            </a>
        </li>
        <li style="margin-bottom: 0.5rem;">
            <a href="perfil.php" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; color: #666; text-decoration: none; border-radius: 10px; transition: all 0.3s ease; background: #f0f8ff; border: 1px solid #004E64;">
                <i class="fas fa-user" style="width: 20px;"></i>
                Mi Perfil
            </a>
        </li>
    </ul>
</aside>

<style>
    .sidebar-vendedor-component ul li a:hover,
    .sidebar-vendedor-component ul li a.active {
        background: #f0f8ff;
        color: #004E64;
    }

    @media (max-width: 968px) {
        .sidebar-vendedor-component {
            position: static !important;
            padding: 1rem !important;
        }

        .sidebar-vendedor-component > div:first-child {
            display: none;
        }

        .sidebar-vendedor-component ul {
            display: flex !important;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .sidebar-vendedor-component ul li {
            margin-bottom: 0 !important;
            flex: 1;
            min-width: 100px;
        }

        .sidebar-vendedor-component ul li a {
            padding: 0.5rem 0.75rem !important;
            font-size: 0.85rem;
            justify-content: center;
            text-align: center;
            gap: 0.5rem !important;
        }
    }
</style>

<script>
    // Marcar como activo el link actual
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop();
        const links = document.querySelectorAll('.sidebar-vendedor-component ul li a');
        links.forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });
    });
</script>

