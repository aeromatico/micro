/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',
    content: [
        './layouts/**/*.htm',
        './pages/**/*.htm',
        './partials/**/*.htm',
        '../../plugins/aero/sites/components/**/*.htm',
    ],
    theme: {
        extend: {
            colors: {
                primary: 'var(--color-primary, #6366f1)',
                brand: {
                    primary: 'var(--color-primary, #4f46e5)',
                    'primary-dark': 'var(--color-primary-dark, #3730a3)',
                    secondary: 'var(--color-secondary, #0ea5e9)',
                    accent: 'var(--color-accent, #f59e0b)',
                    bg: 'var(--color-neutral-bg, #f8fafc)',
                    text: 'var(--color-neutral-text, #0f172a)',
                },
                // Tokens de "superficie" (chrome del sitio + tarjetas de
                // bloques Puck): a diferencia de brand-*, sí cambian entre
                // :root (claro) y :root.dark (oscuro) — ver DesignTheme::toCssVars.
                // Nombres planos (no anidados) para evitar utilidades ambiguas
                // tipo `text-surface-text`.
                surface: 'var(--color-surface-bg, #f8fafc)',
                'surface-alt': 'var(--color-surface-alt, #ffffff)',
                'surface-border': 'var(--color-surface-border, #e2e8f0)',
                ink: 'var(--color-surface-text, #0f172a)',
                'ink-muted': 'var(--color-surface-text-muted, #64748b)',
            },
            fontFamily: {
                sans: ['-apple-system', 'BlinkMacSystemFont', 'Inter', 'Segoe UI', 'sans-serif'],
                heading: ['var(--font-heading)', 'Inter', 'sans-serif'],
                heading2: ['var(--font-heading-2)', 'var(--font-heading)', 'Inter', 'sans-serif'],
                body: ['var(--font-body)', 'Inter', 'sans-serif'],
            },
            borderRadius: {
                brand: 'var(--radius, 0.75rem)',
            },
        },
    },
    plugins: [
        require('@tailwindcss/typography'),
    ],
    safelist: [
        // Color primario dinámico por tenant (via CSS var)
        'text-primary', 'bg-primary', 'border-primary',
        'text-indigo-400', 'text-indigo-500', 'bg-indigo-600',
        'text-green-400', 'text-red-400', 'text-yellow-400',

        // -------------------------------------------------------------------
        // Sistema de temas (DesignTheme) — clases "brand-*" fijas que
        // referencian variables CSS del tema del tenant. Reemplazan las
        // combinaciones bg-{color}-900 por bloque que había antes.
        // -------------------------------------------------------------------
        'bg-brand-primary', 'bg-brand-primary-dark', 'bg-brand-secondary', 'bg-brand-accent', 'bg-brand-bg',
        'text-brand-primary', 'text-brand-primary-dark', 'text-brand-accent', 'text-brand-text', 'text-white',
        'border-brand-primary', 'border-2',
        'font-heading', 'font-heading2', 'font-body',
        'rounded-brand',

        // -------------------------------------------------------------------
        // Tokens de superficie — chrome del sitio (header/footer/body) y
        // fondos neutros/transparentes de bloques Puck. Reaccionan a
        // :root.dark (ver tailwind.config.js colors.surface/ink).
        // -------------------------------------------------------------------
        'bg-surface', 'bg-surface-alt', 'bg-surface/95', 'bg-surface-alt/50',
        'text-ink', 'text-ink-muted', 'text-surface-border',
        'border-surface-border',

        // -------------------------------------------------------------------
        // Clases usadas por los bloques Puck (guardados en la BD, no visibles
        // para el escaneo estático). Mantener sincronizadas con
        // plugins/aero/sites/assets/puck-editor/src/components.jsx
        // -------------------------------------------------------------------
        // Layout / espaciado
        'py-14', 'py-16', 'py-20', 'py-24', 'py-2', 'py-4', 'py-8',
        'px-3', 'px-4', 'px-8', 'p-8', 'px-6',
        'mb-2', 'mb-3', 'mb-4', 'mb-6', 'mb-8', 'mb-10', 'mb-12', 'mt-3',
        'gap-2', 'gap-4', 'gap-8', 'mx-auto', 'space-y-3',
        // Display / grid / flex
        'grid', 'flex', 'inline-block', 'inline-flex', 'items-center', 'justify-center', 'justify-between',
        'flex-wrap', 'list-none', 'cursor-pointer',
        'grid-cols-1', 'md:grid-cols-2', 'md:grid-cols-3', 'md:grid-cols-4',
        'sm:grid-cols-2', 'sm:grid-cols-3',
        // Bloques de Layout (Grid/Flex/Space) — contenedores con slot
        'flex-col', 'flex-row', 'flex-nowrap', 'justify-start', 'justify-end',
        'items-start', 'items-end', 'items-stretch',
        // Fondos claros neutrales (variantes de bloques, no ligadas a marca)
        'bg-white', 'bg-gray-100',
        // Badges semánticos (éxito/alerta/neutro — no de marca)
        'bg-green-100', 'text-green-800',
        'bg-red-100', 'text-red-800',
        'bg-gray-100', 'text-gray-800',
        // Texto sobre fondos claros/oscuros
        'text-gray-300', 'text-gray-500', 'text-gray-600', 'text-gray-700',
        'text-2xl', 'text-3xl', 'text-4xl', 'text-5xl', 'md:text-2xl', 'md:text-6xl',
        'text-sm', 'text-lg', 'text-xl',
        // Tipografía / helpers
        'font-bold', 'font-semibold', 'italic',
        'leading-tight', 'leading-relaxed', 'opacity-75', 'opacity-90',
        'text-center', 'text-left',
        'prose', 'prose-lg', 'prose-sm', 'dark:prose-invert',
        // Bordes / radios / sombras
        'rounded-xl', 'rounded-2xl',
        'shadow-sm', 'border-gray-200', 'border-transparent', 'border-b', 'border-b-2',
        // Medios / dimensiones
        'object-cover', 'w-full', 'h-12', 'w-auto',
        'max-w-3xl', 'max-w-2xl', 'max-w-4xl', 'max-w-6xl', 'max-w-none',
        'aspect-video', 'overflow-hidden', 'h-full',
        'hover:opacity-90',
        'group', 'group-open:rotate-180', 'transition-transform', 'transition-opacity',
        // Espaciadores (Divider)
        'h-4', 'h-8', 'h-16', 'h-32',
        // Imagen de fondo con overlay (Hero)
        'relative', 'absolute', 'inset-0', 'bg-cover', 'bg-center', 'bg-black/50',
        // Efectos suaves (scroll-reveal + hover lift en cards)
        'reveal', 'hover:-translate-y-1', 'hover:shadow-lg', 'transition-all', 'duration-300', 'shadow-md',

        // -------------------------------------------------------------------
        // LEGACY — clases del sistema de color pre-DesignTheme. Ya no las
        // genera components.jsx/PuckHtmlRenderer.php, pero hay `content`
        // HTML ya guardado en BD (ej. página de inicio del tenant demo) que
        // las usa y no tiene `puck_data` para poder regenerarse. Quitar esta
        // sección cuando ese contenido histórico se migre (ver Fase 5).
        // -------------------------------------------------------------------
        'bg-indigo-900', 'bg-blue-900', 'bg-gray-900', 'bg-emerald-900', 'bg-rose-900',
        'bg-indigo-600', 'bg-indigo-100', 'bg-gray-50',
        'text-indigo-800', 'text-indigo-900', 'text-indigo-600', 'text-gray-900',
        'hover:bg-indigo-50', 'hover:bg-indigo-700', 'hover:bg-gray-100',
        'rounded-full',
    ],
};
