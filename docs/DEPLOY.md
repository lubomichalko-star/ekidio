# Deploy na server (FTP)

## Prečo GitHub Actions zlyháva (`ETIMEDOUT`)

FileZilla z tvojho PC **funguje**, ale GitHub cloud **nie** – hosting blokuje FTP z IP adres cloud serverov (185.102.21.128:21 timeout).

To nie je zlý heslo ani zlá cesta – je to firewall hostingu.

## Riešenie A – deploy z PC (najrýchlejšie)

### 1. Vytvor `.env.deploy` (raz)

Skopíruj `.env.deploy.example` → `.env.deploy` a doplň heslo:

```
FTP_SERVER=ftp.ekidio.com
FTP_USERNAME=github.ekidio.com
FTP_PASSWORD=tvoje_heslo
FTP_PLUGIN_DIR=/
```

### 2. Deploy jedným príkazom

```powershell
cd "C:\Users\lubom\Desktop\web\domace prace\ekidio"
npm run deploy:ftp
```

Build + upload – rovnaké údaje ako FileZilla.

## Riešenie B – automat po `git push` (self-hosted runner)

Workflow beží na **tvojom PC**, nie v GitHub cloude → FTP funguje.

### 1. GitHub → Settings → Actions → Runners → **New self-hosted runner**

- OS: **Windows**, architektúra **x64**
- Postupuj podľa inštrukcií (stiahni runner, spusti `config.cmd`)

### 2. Pri registrácii runnera

- Labels: nechaj `self-hosted`, `Windows`, `X64` (workflow to vyžaduje)
- Spusti runner: `run.cmd` (nech beží na pozadí alebo pri štarte PC)

### 3. Secrets na GitHube (už máš)

| Secret | Hodnota |
|--------|---------|
| `FTP_SERVER` | `ftp.ekidio.com` |
| `FTP_USERNAME` | `github.ekidio.com` |
| `FTP_PASSWORD` | heslo |
| `FTP_PLUGIN_DIR` | `/` |

### 4. Push → deploy

Keď PC a runner bežia, `git push` spustí deploy automaticky.

## Overenie

Po deployi: ekidio.com → **Ctrl+F5** → skontroluj verziu na login stránke.
