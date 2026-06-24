import fs from 'fs';
import path from 'path';

const root = process.cwd();
const manifestPath = path.join(root, 'dist', 'manifest.json');
const assetsDir = path.join(root, 'dist', 'assets');

if (!fs.existsSync(manifestPath)) {
  console.error('Chýba dist/manifest.json – najprv spusti: npm run build');
  process.exit(1);
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const entry = manifest['index.html'];
const js = entry?.file || '';
const css = Array.isArray(entry?.css) ? entry.css : [];
const loginChunks = fs.readdirSync(assetsDir).filter((name) => name.startsWith('LoginView-'));

console.log('');
console.log('=== WEB DEPLOY (ekidio.com) ===');
console.log('');
console.log('Zmeny v src/ sa na webe NEUKAZU samy.');
console.log('Na server musis nahrat cely priečinok dist/ do WordPress pluginu:');
console.log('');
console.log('  wp-content/plugins/<ekidio-plugin>/dist/');
console.log('');
console.log('Aktualny build:');
console.log(`  JS:  dist/${js}`);
css.forEach((file) => console.log(`  CSS: dist/${file}`));
loginChunks.forEach((file) => console.log(`  Login: dist/assets/${file}`));
console.log('');
console.log('Po nahratí obnov stránku (Ctrl+F5).');
console.log('Na login obrazovke dole uvidis verziu, napr. "verzia 1.8.2".');
console.log('');
