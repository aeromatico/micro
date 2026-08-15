// Alpine.js init — se carga vía CDN en el layout base
// Este archivo se usa para componentes globales y stores

document.addEventListener('alpine:init', () => {
    // Store global de navegación
    Alpine.store('nav', {
        open: false,
        toggle() { this.open = !this.open; },
        close() { this.open = false; },
    });
});

// Efectos suaves — fade-in-up al entrar en viewport para elementos .reveal
// (bloques Puck, ver components.jsx/PuckHtmlRenderer.php). Desactivado por
// completo si el tema del tenant tiene enable_animations = false (body con
// clase .no-animations, ver layouts/base.htm) — en ese caso el CSS ya deja
// todo visible, así que ni vale la pena observar.
(function () {
    if (document.body.classList.contains('no-animations')) return;
    if (!('IntersectionObserver' in window)) return;

    var observer = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15, rootMargin: '0px 0px -40px 0px' }
    );

    document.querySelectorAll('.reveal').forEach(function (el) {
        observer.observe(el);
    });
})();
