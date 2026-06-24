# Deploy z GitHubu na server (FTP – bez SSH)

Hosting **nemusí mať SSH**. Stačí FTP – GitHub Actions po každom `push` do `main` zbuildí web a nahraje súbory automaticky.

## Ako to funguje

```
git push → GitHub Actions: npm run build → FTP upload → server
```

`dist/` nie je v gite – build sa robí v CI pri každom deployi.

**Nemusíš** mať otvorenú konzolu ani ručne používať FTP klient.

## Nastavenie (raz)

### 1. FTP údaje z hostingu

Z panelu hostingu (FTP účet) si skopíruj:

- server (napr. `ftp.ekidio.com`)
- používateľ a heslo
- po prihlásení cez FileZilla zisti **cesty** – často začínajú od `public_html/` alebo `www/`

Typické cesty:

| Čo | Príklad cesty na FTP |
|----|---------------------|
| WordPress plugin | `public_html/wp-content/plugins/rodinne-ulohy` |
| Download APK | `public_html/download` |

Názov priečinka pluginu over v FileZille – musí zodpovedať tomu, čo už na serveri máš.

### 2. GitHub Secrets

Repozitár → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

| Secret | Príklad |
|--------|---------|
| `FTP_SERVER` | `ftp.ekidio.com` |
| `FTP_USERNAME` | `uzivatel@ekidio.com` |
| `FTP_PASSWORD` | heslo k FTP |
| `FTP_PLUGIN_DIR` | `public_html/wp-content/plugins/rodinne-ulohy` |
| `FTP_DOWNLOAD_DIR` | `public_html/download` (voliteľné) |

`FTP_SERVER` bez `ftp://` – len hostname.

### 3. Push workflow na GitHub

```bash
git add .github/workflows/deploy.yml scripts/prepare-plugin-deploy.mjs docs/DEPLOY.md
git commit -m "FTP deploy from GitHub Actions"
git push origin main
```

Stav deployu: GitHub → **Actions**.

## Čo sa nasadí

| Cieľ na serveri | Obsah |
|-----------------|--------|
| `wp-content/plugins/rodinne-ulohy/` | `rodinne-ulohy.php`, `includes/`, vygenerovaný `dist/` |
| `/download/` | APK, `index.html`, `version.json` |

**Nenasadí sa:** `src/`, `android/`, `node_modules/`.

Plugin priečinok sa pri deployi **kompletne synchronizuje** (staré hashované súbory v `dist/` sa zmazajú – správne pre Vite build).

## Overenie

Po deployi otvor ekidio.com, `Ctrl+F5`, skontroluj verziu na login stránke.

## Problémy

| Problém | Riešenie |
|---------|----------|
| Actions zlyhá na FTP | Skontroluj server, heslo, cestu `FTP_PLUGIN_DIR` |
| Web sa nezmenil | Zlá cesta k pluginu alebo cache – `Ctrl+F5` |
| FileZilla ukazuje inú cestu | Skopíruj presnú cestu z FileZilly do secretu |

## SSH (ak hosting v budúcnosti pridá)

V repozitári môžeme prepnúť workflow na SSH/rsync – build ostáva rovnaký.
