/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',
    content: [
        './layouts/**/*.htm',
        './pages/**/*.htm',
        './partials/**/*.htm',
        '../../plugins/aero/docs/components/**/*.htm',
        // Páginas de servicios generadas (aero/services): las clases viven en la BD, se vuelcan aquí al guardar.
        '../../storage/app/service-offers/*.html',
    ],
    theme: {
        extend: {
            colors: {
                canvas: {
                    DEFAULT: 'var(--color-canvas)',
                    elev: 'var(--color-canvas-elev)',
                    elev2: 'var(--color-canvas-elev-2)',
                },
                ink: {
                    DEFAULT: 'var(--color-ink)',
                    dim: 'var(--color-ink-dim)',
                },
                edge: 'var(--color-edge)',
                accent: {
                    DEFAULT: 'var(--color-accent)',
                    fg: 'var(--color-accent-fg)',
                },
            },
            fontFamily: {
                display: ['"Space Grotesk"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                body: ['Manrope', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                logo: ['Urbanist', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
        },
    },
    plugins: [],
};
