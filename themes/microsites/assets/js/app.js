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
