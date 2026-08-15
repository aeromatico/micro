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
                // Tokens de "superficie" — deben coincidir con
                // themes/microsites/tailwind.config.js. Sin esto, los bloques
                // que usan bg-surface-alt/text-ink/etc. (la mayoría desde la
                // Fase 1) se ven sin estilo en el preview del editor.
                surface: 'var(--color-surface-bg, #f8fafc)',
                'surface-alt': 'var(--color-surface-alt, #ffffff)',
                'surface-border': 'var(--color-surface-border, #e2e8f0)',
                ink: 'var(--color-surface-text, #0f172a)',
                'ink-muted': 'var(--color-surface-text-muted, #64748b)',
            },
            fontFamily: {
                heading: ['var(--font-heading)', 'Inter', 'sans-serif'],
                heading2: ['var(--font-heading-2)', 'var(--font-heading)', 'Inter', 'sans-serif'],
                body: ['var(--font-body)', 'Inter', 'sans-serif'],
            },
            borderRadius: {
                brand: 'var(--radius, 0.75rem)',
            },
        },
    },
};
