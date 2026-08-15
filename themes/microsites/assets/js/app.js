// Alpine.js init — se carga vía CDN en el layout base
// Este archivo se usa para componentes globales y stores

document.addEventListener('alpine:init', () => {
    // Store global de navegación
    Alpine.store('nav', {
        open: false,
        toggle() { this.open = !this.open; },
        close() { this.open = false; },
    });

    // Store de modo claro/oscuro. El <html> ya trae la clase correcta antes
    // de este punto (script inline anti-parpadeo en layouts/base.htm) — este
    // store solo sincroniza el estado tras un click del usuario.
    Alpine.store('theme', {
        current: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
        toggle() {
            this.current = this.current === 'dark' ? 'light' : 'dark';
            document.documentElement.classList.toggle('dark', this.current === 'dark');
            localStorage.setItem('theme', this.current);
        },
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
