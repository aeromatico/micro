/**
 * Config Tailwind SOLO para compilar las utilidades usadas por los bloques
 * Puck (src/components.jsx) y que el editor visual del backend las muestre
 * con el mismo aspecto que el sitio real. Sin esto, el preview del editor
 * no tenia ningun CSS para las clases bg-brand-x/font-heading/etc.
 *
 * Solo utilidades (ver src/editor.css), nunca base/components,
 * para no filtrar resets globales (body, html, tipografia) al panel admin.
 */
/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ['./src/components.jsx'],
    theme: {
        extend: {
            colors: {
                brand: {
                    primary: 'var(--color-primary, #4f46e5)',
                    'primary-dark': 'var(--color-primary-dark, #3730a3)',
                    secondary: 'var(--color-secondary, #0ea5e9)',
                    accent: 'var(--color-accent, #f59e0b)',
                    bg: 'var(--color-neutral-bg, #f8fafc)',
                    text: 'var(--color-neutral-text, #0f172a)',
                },
            },
            fontFamily: {
                heading: ['var(--font-heading)', 'Inter', 'sans-serif'],
                body: ['var(--font-body)', 'Inter', 'sans-serif'],
            },
            borderRadius: {
                brand: 'var(--radius, 0.75rem)',
            },
        },
    },
};
