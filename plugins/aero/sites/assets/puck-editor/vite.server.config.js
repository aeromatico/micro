import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  build: {
    lib: {
      entry: resolve(__dirname, 'src/render-server.jsx'),
      formats: ['cjs'],
      fileName: () => 'render-server.js',
    },
    outDir: resolve(__dirname, '../../bin'),
    emptyOutDir: false,
    rollupOptions: {
      external: ['react', 'react-dom/server', '@puckeditor/core'],
      output: {
        globals: {
          react: 'React',
          'react-dom/server': 'ReactDOMServer',
          '@puckeditor/core': 'PuckCore',
        },
      },
    },
    minify: false,
    sourcemap: false,
  },
});
