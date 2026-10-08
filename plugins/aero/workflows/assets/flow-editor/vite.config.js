import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    lib: {
      entry: resolve(__dirname, 'src/index.jsx'),
      name: 'AeroFlowEditor',
      formats: ['iife'],
      fileName: () => 'flow-editor.js',
    },
    outDir: resolve(__dirname, '../../formwidgets/workfloweditor/assets'),
    emptyOutDir: true,
    rollupOptions: {
      output: {
        assetFileNames: 'flow-editor.[ext]',
        banner: 'var process=window.process||(window.process={env:{NODE_ENV:"production"}});',
      },
    },
  },
});
