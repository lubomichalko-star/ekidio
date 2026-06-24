import fs from 'fs';
import path from 'path';

const root = process.cwd();
const apkSrc = path.join(root, 'android', 'app', 'build', 'outputs', 'apk', 'debug', 'app-debug.apk');
const downloadDir = path.join(root, 'download');
const buildGradlePath = path.join(root, 'android', 'app', 'build.gradle');

function readAndroidVersion() {
  const fallback = { version: '1.8', versionCode: 9 };
  if (!fs.existsSync(buildGradlePath)) return fallback;
  const content = fs.readFileSync(buildGradlePath, 'utf8');
  const versionNameMatch = content.match(/versionName\s+"([^"]+)"/);
  const versionCodeMatch = content.match(/versionCode\s+(\d+)/);
  return {
    version: versionNameMatch?.[1] || fallback.version,
    versionCode: Number(versionCodeMatch?.[1] || fallback.versionCode),
  };
}

const { version, versionCode } = readAndroidVersion();
const apkName = `ekidio-${version}.apk`;
const apkDest = path.join(downloadDir, apkName);
const versionJsonPath = path.join(downloadDir, 'version.json');

if (!fs.existsSync(apkSrc)) {
  console.error('Chýba APK. Spusti: cd android && gradlew.bat assembleDebug');
  process.exit(1);
}

fs.mkdirSync(downloadDir, { recursive: true });
fs.copyFileSync(apkSrc, apkDest);

const versionPayload = {
  android: {
    latestVersion: version,
    latestVersionCode: versionCode,
    downloadUrl: 'https://ekidio.com/download/',
    message: 'Je dostupná nová verzia aplikácie ekidio.',
  },
};
fs.writeFileSync(versionJsonPath, `${JSON.stringify(versionPayload, null, 2)}\n`, 'utf8');

const indexHtmlPath = path.join(downloadDir, 'index.html');
const cacheMeta = `    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
`;
if (fs.existsSync(indexHtmlPath)) {
  let html = fs.readFileSync(indexHtmlPath, 'utf8');
  if (!html.includes('http-equiv="Cache-Control"')) {
    html = html.replace(
      '<meta name="viewport" content="width=device-width, initial-scale=1.0" />',
      `<meta name="viewport" content="width=device-width, initial-scale=1.0" />\n${cacheMeta}`
    );
  }
  html = html.replace(/href="ekidio-[^"]+\.apk"/, `href="${apkName}"`);
  html = html.replace(/download="ekidio-[^"]+\.apk"/, `download="${apkName}"`);
  html = html.replace(/Verzia [^·]+ · Android APK/, `Verzia ${version} · Android APK`);
  fs.writeFileSync(indexHtmlPath, html, 'utf8');
}

console.log('');
console.log('Download folder pripravený:');
console.log(`  ${path.relative(root, downloadDir)}/`);
console.log(`  - ${apkName}`);
console.log('  - index.html');
console.log('  - version.json');
console.log('');
console.log('Nahraj celý priečinok download/ na server, napr.:');
console.log('  https://ekidio.com/download/');
console.log('');
