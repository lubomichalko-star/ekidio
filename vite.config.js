import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import fs from 'fs';
import path from 'path';

const buildStamp = new Date().toISOString().slice(0, 16).replace('T', ' ');

function readReleaseVersion() {
  const gradlePath = path.resolve(__dirname, 'android/app/build.gradle');
  if (!fs.existsSync(gradlePath)) return '0.0.0';
  const content = fs.readFileSync(gradlePath, 'utf8');
  const match = content.match(/versionName\s+"([^"]+)"/);
  return match?.[1] || '0.0.0';
}

const releaseVersion = readReleaseVersion();

export default defineConfig({
  plugins: [vue()],
  root: '.',
  base: './',
  define: {
    __APP_BUILD__: JSON.stringify(buildStamp),
    __APP_RELEASE_VERSION__: JSON.stringify(releaseVersion),
  },
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

