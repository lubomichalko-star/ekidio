import fs from 'fs';
import path from 'path';

const root = process.cwd();
const outDir = path.join(root, 'deploy', 'plugin');

const required = [
  path.join(root, 'rodinne-ulohy.php'),
  path.join(root, 'includes'),
  path.join(root, 'dist', 'manifest.json'),
];

for (const item of required) {
  if (!fs.existsSync(item)) {
    console.error(`Chýba: ${path.relative(root, item)} – najprv spusti npm run build`);
    process.exit(1);
  }
}

fs.rmSync(outDir, { recursive: true, force: true });
fs.mkdirSync(outDir, { recursive: true });

fs.copyFileSync(
  path.join(root, 'rodinne-ulohy.php'),
  path.join(outDir, 'rodinne-ulohy.php')
);

function copyDir(src, dest) {
  fs.mkdirSync(dest, { recursive: true });
  for (const entry of fs.readdirSync(src, { withFileTypes: true })) {
    const from = path.join(src, entry.name);
    const to = path.join(dest, entry.name);
    if (entry.isDirectory()) copyDir(from, to);
    else fs.copyFileSync(from, to);
  }
}

copyDir(path.join(root, 'includes'), path.join(outDir, 'includes'));
copyDir(path.join(root, 'dist'), path.join(outDir, 'dist'));

console.log(`Plugin balík pripravený: ${path.relative(root, outDir)}/`);
