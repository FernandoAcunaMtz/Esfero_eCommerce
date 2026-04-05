/**
 * Esfero — GSAP Animations
 *
 * Principios de rendimiento:
 *  - prefers-reduced-motion: no JS animations, just show elements
 *  - ScrollTrigger.batch() para grupos de cards (un solo IntersectionObserver interno)
 *  - once: true en todos los triggers (sin recalculo al hacer scroll hacia arriba)
 *  - clearProps: 'transform,opacity' libera capas de GPU tras animación
 *  - force3D: false en elementos simples (evita layer promotion innecesaria)
 *  - Carga diferida (atributo defer en el script tag)
 */

(function () {
  'use strict';

  /* ── Respetar prefers-reduced-motion ───────────────────────────────────── */
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    /* Asegurar visibilidad si algo quedó oculto esperando animación */
    document.querySelectorAll(
      '.portfolio-item, .team-member, .blog-card, .testimonial-card, .benefit-card'
    ).forEach(function (el) {
      el.style.opacity = '1';
      el.style.transform = 'none';
    });
    return;
  }

  /* ── Esperar a que GSAP esté disponible ─────────────────────────────────── */
  function init() {
    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
      /* GSAP aún no cargó (puede pasar en conexiones lentas) */
      return;
    }

    gsap.registerPlugin(ScrollTrigger);

    /* Configuración global de ScrollTrigger */
    ScrollTrigger.config({ limitCallbacks: true, syncInterval: 40 });

    /* ── 1. Hero — stagger de entrada ─────────────────────────────────────── */
    var heroEls = [
      document.querySelector('.hero h1'),
      document.querySelector('.hero p'),
      document.getElementById('search-bar'),
      document.getElementById('chips'),
    ].concat(
      Array.from(document.querySelectorAll('.hero > .hero-content .cta-button'))
    ).filter(Boolean);

    if (heroEls.length) {
      /* Cancelar la animación CSS que ya tienen h1 y p */
      heroEls.forEach(function (el) {
        el.style.animation = 'none';
        el.style.opacity = '0';
      });

      gsap.fromTo(
        heroEls,
        { y: 28, opacity: 0, force3D: false },
        {
          y: 0,
          opacity: 1,
          duration: 0.7,
          ease: 'power3.out',
          stagger: 0.11,
          delay: 0.18,
          clearProps: 'transform,opacity',
        }
      );
    }

    /* ── 2. Section titles ────────────────────────────────────────────────── */
    ScrollTrigger.batch('.section-title', {
      onEnter: function (els) {
        gsap.fromTo(
          els,
          { y: 16, opacity: 0 },
          { y: 0, opacity: 1, duration: 0.55, ease: 'power2.out', clearProps: 'all' }
        );
      },
      once: true,
      start: 'top 91%',
    });

    /* ── 3. Tarjetas de productos ─────────────────────────────────────────── */
    var productCards = document.querySelectorAll('#productsGrid .portfolio-item');
    if (productCards.length) {
      gsap.set(productCards, { y: 26, opacity: 0, force3D: false });

      ScrollTrigger.batch(productCards, {
        onEnter: function (batch) {
          gsap.to(batch, {
            y: 0,
            opacity: 1,
            duration: 0.5,
            ease: 'power2.out',
            stagger: 0.065,
            clearProps: 'transform,opacity',
          });
        },
        once: true,
        start: 'top 93%',
      });
    }

    /* ── 4. Tarjetas genéricas (team, blog) ───────────────────────────────── */
    ScrollTrigger.batch('.team-member, .blog-card', {
      onEnter: function (batch) {
        gsap.fromTo(
          batch,
          { y: 22, opacity: 0, force3D: false },
          {
            y: 0,
            opacity: 1,
            duration: 0.5,
            ease: 'power2.out',
            stagger: 0.07,
            clearProps: 'transform,opacity',
          }
        );
      },
      once: true,
      start: 'top 91%',
    });

    /* ── 5. Testimonios ───────────────────────────────────────────────────── */
    ScrollTrigger.batch('.testimonial-card', {
      onEnter: function (batch) {
        gsap.fromTo(
          batch,
          { y: 18, opacity: 0, force3D: false },
          {
            y: 0,
            opacity: 1,
            duration: 0.48,
            ease: 'power2.out',
            stagger: 0.09,
            clearProps: 'transform,opacity',
          }
        );
      },
      once: true,
      start: 'top 91%',
    });

    /* ── 6. Benefit cards (sección "¿Por qué Esfero?") ───────────────────── */
    ScrollTrigger.batch('.benefit-card', {
      onEnter: function (batch) {
        gsap.fromTo(
          batch,
          { scale: 0.93, opacity: 0, force3D: false },
          {
            scale: 1,
            opacity: 1,
            duration: 0.45,
            ease: 'back.out(1.5)',
            stagger: 0.08,
            clearProps: 'transform,opacity',
          }
        );
      },
      once: true,
      start: 'top 90%',
    });

    /* ── 7. Pasos "Vende en 3 pasos" — slide desde izquierda ─────────────── */
    ScrollTrigger.batch('[id="vende-pasos"] .container > div > div', {
      onEnter: function (batch) {
        gsap.fromTo(
          batch,
          { x: -18, opacity: 0, force3D: false },
          {
            x: 0,
            opacity: 1,
            duration: 0.5,
            ease: 'power2.out',
            stagger: 0.13,
            clearProps: 'transform,opacity',
          }
        );
      },
      once: true,
      start: 'top 90%',
    });

    /* ── 8. Contadores estadísticos animados ─────────────────────────────── */
    document.querySelectorAll('[data-count]').forEach(function (el) {
      var target = parseInt(el.dataset.count, 10) || 0;

      ScrollTrigger.create({
        trigger: el,
        start: 'top 85%',
        once: true,
        onEnter: function () {
          var obj = { val: 0 };
          gsap.to(obj, {
            val: target,
            duration: 1.9,
            ease: 'power2.out',
            onUpdate: function () {
              var v = Math.round(obj.val);
              el.textContent =
                v >= 1000
                  ? (v / 1000).toFixed(1) + 'K+'
                  : v.toLocaleString('es-MX') + '+';
            },
          });
        },
      });
    });

    /* ── 9. Fade-up genérico (opt-in con data-animate="fade-up") ─────────── */
    ScrollTrigger.batch('[data-animate="fade-up"]', {
      onEnter: function (batch) {
        gsap.fromTo(
          batch,
          { y: 18, opacity: 0 },
          { y: 0, opacity: 1, duration: 0.5, ease: 'power2.out', stagger: 0.1, clearProps: 'all' }
        );
      },
      once: true,
      start: 'top 91%',
    });

    /* ── Refresh al resize (debounced, passive) ───────────────────────────── */
    var resizeTimer;
    window.addEventListener(
      'resize',
      function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
          ScrollTrigger.refresh();
        }, 250);
      },
      { passive: true }
    );
  }

  /*
   * Los scripts con defer se ejecutan DESPUÉS de DOMContentLoaded,
   * así que el document.readyState siempre será 'complete' o 'interactive' aquí.
   * Llamamos init() directamente; si GSAP aún no cargó (raro con defer en orden),
   * esperamos al evento load.
   */
  if (typeof gsap !== 'undefined') {
    init();
  } else {
    window.addEventListener('load', init, { once: true });
  }
})();
