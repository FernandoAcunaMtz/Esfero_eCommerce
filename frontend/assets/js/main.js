// Main JavaScript para Esfero

// Toggle user menu
function toggleUserMenu() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('show');
}

// Cerrar menú al hacer click fuera
document.addEventListener('click', function(event) {
    const userMenu = document.querySelector('.user-menu');
    const dropdown = document.getElementById('userDropdown');
    
    if (userMenu && !userMenu.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// Cerrar menú al presionar Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
        }
    }
});

// Navbar scroll effect
window.addEventListener('scroll', function() {
    const navbar = document.getElementById('navbar');
    if (navbar) {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }
}, { passive: true });

// Smooth scroll para enlaces de anclas
document.addEventListener('DOMContentLoaded', function() {
    // Agregar smooth scroll a todos los enlaces que apuntan a anclas
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            // Solo aplicar smooth scroll si no es solo "#"
            if (href !== '#' && href.length > 1) {
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    const navbarHeight = document.getElementById('navbar')?.offsetHeight || 55;
                    const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navbarHeight;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                    
                    // Cerrar menú móvil si está abierto
                    const mobileMenu = document.getElementById('mobileMenu');
                    if (mobileMenu && mobileMenu.classList.contains('active')) {
                        if (typeof window.closeMobileMenu === 'function') {
                            window.closeMobileMenu();
                        }
                    }
                }
            }
        });
    });
});


// El efecto Iridescence ahora está en iridescence-effect.js

// Parallax Effect - Simple background position
(function () {
    var sections = Array.prototype.slice.call(document.querySelectorAll('[data-parallax-speed]'));
    if (!sections.length) return;

    var items = sections.map(function (section) {
        var speedAttr = section.getAttribute('data-parallax-speed');
        var speed = speedAttr ? parseFloat(speedAttr) : 0.5;
        return { section: section, speed: speed };
    });

    var ticking = false;

    function update() {
        ticking = false;
        var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            var rect = it.section.getBoundingClientRect();
            var sectionTop = rect.top + scrollY;
            var sectionCenter = sectionTop + rect.height / 2;
            var viewportCenter = scrollY + window.innerHeight / 2;
            var distance = sectionCenter - viewportCenter;
            var translateY = distance * it.speed * 0.15;
            
            it.section.style.transform = 'translateY(' + translateY.toFixed(2) + 'px)';
        }
    }

    function requestTick() {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(update);
        }
    }

    window.addEventListener('scroll', requestTick, { passive: true });
    window.addEventListener('resize', requestTick);
    requestTick();
})();

// Efecto de explosión "¿Por qué elegir Esfero?"
(function() {
    const explodeBtnWrapper = document.getElementById('explode-button-wrapper');
    const explodeBtn = document.getElementById('explode-btn');
    const benefitsContainer = document.getElementById('benefits-container');
    const collapseBtn = document.getElementById('collapse-btn');
    const benefitCards = document.querySelectorAll('.benefit-card');
    const mainContainer = document.getElementById('explode-main-container');
    
    if (!explodeBtn || !benefitCards.length) return;
    
    let isExploded = false;
    
    // Función para explotar las tarjetas
    function explodeCards() {
        if (isExploded) return;
        isExploded = true;
        
        // Ocultar botón wrapper
        explodeBtnWrapper.style.opacity = '0';
        explodeBtnWrapper.style.pointerEvents = 'none';
        
        // Mostrar contenedor de beneficios en el mismo espacio
        setTimeout(() => {
            benefitsContainer.style.opacity = '1';
            benefitsContainer.style.pointerEvents = 'auto';
            
            // Animar tarjetas con escala
            benefitCards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('exploded');
                }, index * 100);
            });
        }, 200);
    }
    
    // Función para colapsar las tarjetas
    function collapseCards() {
        if (!isExploded) return;
        isExploded = false;
        
        // Ocultar tarjetas
        benefitCards.forEach((card, index) => {
            setTimeout(() => {
                card.classList.remove('exploded');
            }, index * 50);
        });
        
        // Después de que todas las tarjetas se oculten
        setTimeout(() => {
            benefitsContainer.style.opacity = '0';
            benefitsContainer.style.pointerEvents = 'none';
            
            // Mostrar botón principal de nuevo
            setTimeout(() => {
                explodeBtnWrapper.style.opacity = '1';
                explodeBtnWrapper.style.pointerEvents = 'auto';
            }, 200);
        }, benefitCards.length * 50 + 100);
    }
    
    // Event listeners
    explodeBtn.addEventListener('click', explodeCards);
    collapseBtn.addEventListener('click', collapseCards);
})();

// Optimizaciones para móviles
(function() {
    // Lazy loading de imágenes
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    // Prevenir zoom en inputs en iOS
    if (/iPhone|iPad|iPod/.test(navigator.userAgent)) {
        const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="number"], textarea');
        inputs.forEach(input => {
            input.style.fontSize = '16px'; // Previene zoom automático en iOS
        });
    }
    
    // Optimizar scroll en móviles
    let ticking = false;
    function optimizeScroll() {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                // Aquí puedes agregar lógica de scroll optimizada
                ticking = false;
            });
            ticking = true;
        }
    }
    
    // Touch events optimizados
    let touchStartY = 0;
    let touchEndY = 0;
    
    document.addEventListener('touchstart', function(e) {
        touchStartY = e.changedTouches[0].screenY;
    }, { passive: true });
    
    document.addEventListener('touchend', function(e) {
        touchEndY = e.changedTouches[0].screenY;
        // Aquí puedes agregar lógica de swipe si es necesario
    }, { passive: true });
    
    // Prevenir doble tap zoom en botones
    let lastTap = 0;
    document.addEventListener('touchend', function(e) {
        const currentTime = new Date().getTime();
        const tapLength = currentTime - lastTap;
        if (tapLength < 300 && tapLength > 0) {
            e.preventDefault();
        }
        lastTap = currentTime;
    }, false);
})();

// Sistema de búsqueda funcional
(function() {
    const searchInput = document.getElementById('search-input');
    const searchBar = document.getElementById('search-bar');
    const searchSuggestions = document.getElementById('search-suggestions');
    const searchButton = searchBar ? searchBar.querySelector('button') : null;
    
    if (!searchInput || !searchBar) return;
    
    let searchTimeout;
    let currentSuggestions = [];
    
    // Función para obtener sugerencias
    function getSuggestions(query) {
        if (query.length < 2) {
            hideSuggestions();
            return;
        }
        
        fetch(`api_buscar_sugerencias.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                currentSuggestions = data.sugerencias || [];
                showSuggestions(currentSuggestions, query);
            })
            .catch(error => {
                console.error('Error obteniendo sugerencias:', error);
                hideSuggestions();
            });
    }
    
    // Mostrar sugerencias
    function showSuggestions(sugerencias, query) {
        if (!searchSuggestions) return;
        
        if (sugerencias.length === 0) {
            hideSuggestions();
            return;
        }
        
        let html = '<div style="background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); overflow: hidden; margin-top: 0.5rem;">';
        
        sugerencias.forEach((sug, index) => {
            const tipo = sug.tipo === 'categoria' ? '📁' : '🔍';
            html += `
                <div class="suggestion-item" style="padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: background 0.2s;" 
                     onmouseover="this.style.background='#f5f5f5'" 
                     onmouseout="this.style.background='white'"
                     onclick="selectSuggestion('${sug.sugerencia.replace(/'/g, "\\'")}')">
                    <span style="margin-right: 0.5rem;">${tipo}</span>
                    <strong>${highlightMatch(sug.sugerencia, query)}</strong>
                </div>
            `;
        });
        
        html += '</div>';
        searchSuggestions.innerHTML = html;
        searchSuggestions.style.display = 'block';
    }
    
    // Resaltar coincidencias
    function highlightMatch(text, query) {
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark style="background: #fff3cd; padding: 0.1rem 0.2rem; border-radius: 3px;">$1</mark>');
    }
    
    // Ocultar sugerencias
    function hideSuggestions() {
        if (searchSuggestions) {
            searchSuggestions.style.display = 'none';
            searchSuggestions.innerHTML = '';
        }
    }
    
    // Seleccionar sugerencia
    window.selectSuggestion = function(sugerencia) {
        searchInput.value = sugerencia;
        hideSuggestions();
        performSearch(sugerencia);
    };
    
    // Realizar búsqueda
    function performSearch(query) {
        if (!query || query.trim().length === 0) return;
        window.location.href = `buscar.php?q=${encodeURIComponent(query.trim())}`;
    }
    
    // Event listeners
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => {
                getSuggestions(query);
            }, 300); // Debounce de 300ms
        } else {
            hideSuggestions();
        }
    });
    
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            hideSuggestions();
            performSearch(this.value);
        } else if (e.key === 'Escape') {
            hideSuggestions();
            this.blur();
        }
    });
    
    // Botón de búsqueda
    if (searchButton) {
        searchButton.addEventListener('click', function(e) {
            e.preventDefault();
            performSearch(searchInput.value);
        });
    }
    
    // Ocultar sugerencias al hacer click fuera
    document.addEventListener('click', function(e) {
        if (searchBar && !searchBar.contains(e.target) && searchSuggestions && !searchSuggestions.contains(e.target)) {
            hideSuggestions();
        }
    });
    
    // Si hay query en la URL, prellenar el input
    const urlParams = new URLSearchParams(window.location.search);
    const urlQuery = urlParams.get('q');
    if (urlQuery && searchInput) {
        searchInput.value = decodeURIComponent(urlQuery);
    }
})();

console.log('✅ Esfero JS cargado correctamente');

