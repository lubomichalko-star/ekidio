import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
  plugins: [vue()],
  root: '.',
  base: './',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    cssCodeSplit: true,
    // Generate manifest.json so WordPress can enqueue hashed files.
    // Vite 5 default is dist/.vite/manifest.json; we keep it in dist root for easier deployment.
    manifest: 'manifest.json',
    rollupOptions: {
      input: path.resolve(__dirname, 'index.html'),
    }
  }
});

