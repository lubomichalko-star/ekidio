import { Client } from 'basic-ftp';
import fs from 'fs';
import path from 'path';

const root = process.cwd();

function loadEnvFile() {
  const envPath = path.join(root, '.env.deploy');
  if (!fs.existsSync(envPath)) return;
  for (const line of fs.readFileSync(envPath, 'utf8').split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eq = trimmed.indexOf('=');
    if (eq === -1) continue;
    const key = trimmed.slice(0, eq).trim();
    const val = trimmed.slice(eq + 1).trim().replace(/^["']|["']$/g, '');
    if (!process.env[key]) process.env[key] = val;
  }
}

function requireEnv(name) {
  const val = process.env[name];
  if (!val) {
    console.error(`Chýba ${name} – nastav v .env.deploy alebo ako premennú prostredia`);
    process.exit(1);
  }
  return val;
}

loadEnvFile();

const host = requireEnv('FTP_SERVER');
const user = requireEnv('FTP_USERNAME');
const password = requireEnv('FTP_PASSWORD');
const remoteDir = (process.env.FTP_PLUGIN_DIR || '/').replace(/\\/g, '/');
const localDir = path.join(root, 'deploy', 'plugin');

if (!fs.existsSync(path.join(localDir, 'dist', 'manifest.json'))) {
  console.error('Chýba deploy/plugin/ – najprv: npm run build && node scripts/prepare-plugin-deploy.mjs');
  process.exit(1);
}

const client = new Client(300000);
client.ftp.verbose = process.env.FTP_VERBOSE === '1';

try {
  console.log(`FTP → ${host} (${user}), remote: ${remoteDir}`);
  await client.access({
    host,
    user,
    password,
    secure: true,
    port: Number(process.env.FTP_PORT || 21),
  });

  if (remoteDir && remoteDir !== '/' && remoteDir !== '.') {
    await client.cd(remoteDir);
  }

  console.log('Čistím server a nahrávam plugin...');
  await client.clearWorkingDir();
  await client.uploadFromDir(localDir);
  console.log('Deploy pluginu OK.');
} catch (err) {
  console.error('Deploy zlyhal:', err.message);
  process.exit(1);
} finally {
  client.close();
}
