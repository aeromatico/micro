import { defineConfig } from 'vite';

// Tema master (October CMS): Vite solo compila para producción — no hay
// dev server, la página la sirve October vía Twig. Salida con manifest.json
// (nombres con hash) para que el layout resuelva la URL real y el caché
// del navegador/Cloudflare se invalide solo con cada build, sin depender de
// que alguien se acuerde de subir un `?v=` a mano (bug que ya nos mordió
// más de una vez en este proyecto).
export default defineConfig({
    base: '/themes/master/assets/dist/',
    build: {
        outDir: 'assets/dist',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: {
                app: 'assets/src/app.js',
                signup: 'assets/src/signup.js',
            },
        },
    },
});
