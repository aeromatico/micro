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
            },
            fontFamily: {
                sans: ['-apple-system', 'BlinkMacSystemFont', 'Inter', 'Segoe UI', 'sans-serif'],
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
    ],
};
