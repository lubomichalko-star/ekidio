# ekidio – mobilná aplikácia (Capacitor)

Tento projekt sa dá zabaliť ako „normálna“ Android/iOS aplikácia pomocou **Capacitor**.

## Ako to funguje (najjednoduchšie)

Táto appka je **native obal (WebView)**, ktorý otvára tvoju existujúcu WordPress SPA stránku:

- API backend beží na `https://ekidio.com` (fallback v `src/config/appConfig.js`)

Výhoda: nič nemusíme prerábať na backend autentifikácii/nonce/routingu – používa sa presne to, čo už funguje na webe.

### Google prihlásenie (Android)

Appka používa rovnaký **Web Client ID** ako web na ekidio.com. Pre natívne Google prihlásenie ešte treba v [Google Cloud Console](https://console.cloud.google.com/apis/credentials):

1. **OAuth klient typu Android**
   - Package name: `com.ekidio.app`
   - SHA-1 (debug, pre testovanie z Android Studia): `58:2A:96:83:8F:15:8F:DD:35:9E:06:CD:F8:67:E3:35:38:71:8B:89`
2. Pri release builde pridaj aj SHA-1 tvojho **upload/release** kľúča (v Android Studio: `./gradlew signingReport`).

Web Client ID je v `src/config/appConfig.js` (fallback pre Capacitor build).

## Android (Windows)

### Požiadavky

- Node.js + npm
- Android Studio
- Android SDK (Android Studio si ho doinštaluje)
- Java (Android Studio obsahuje vlastné JDK)

### Spustenie v Android Studiu

**Pred Run v Android Studio vždy najprv zbuilduj web časť:**

```bash
npm run cap:android:build
```

Potom v Android Studio daj **Run** (▶). Samotný Gradle **nestačí** — appka je WebView a UI sa berie z priečinka `dist/`, ktorý sa cez Capacitor kopíruje do Android projektu.

Odporúčaný postup pri testovaní:
1. `npm run cap:android:build`
2. V Android Studio: **Build → Clean Project**
3. Odinštaluj appku z telefónu/emulátora
4. **Run** (▶)

## Web (ekidio.com / WordPress)

Zmeny v `src/` sa na webe **nezobrazia**, kým nenahraješ nový build na server.

```bash
npm run deploy:web
```

Tento príkaz vytvorí `dist/` a vypíše, čo nahrať. Skopíruj celý priečinok **`dist/`** na server do WordPress pluginu:

`wp-content/plugins/<názov-pluginu>/dist/`

Po nahratí obnov stránku (`Ctrl+F5`). Na login obrazovke dole je malý text **build 2026-…** — ak ho nevidíš alebo je starý dátum, server ešte nemá nový build.

Alternatíva (build + otvorenie Studia):

```bash
npm run cap:android
```

### Build (APK/AAB)

V Android Studiu:

- APK: **Build → Build Bundle(s) / APK(s) → Build APK(s)**
- AAB: **Build → Generate Signed Bundle / APK…** (vyber *Android App Bundle*)

## iOS (iPhone) – iba na Macu

iOS appku **nevieš zbuildiť na Windows**. Potrebuješ **Mac** s **Xcode** (zadarmo z App Store).

### Požiadavky

- Mac (MacBook, Mac mini, …)
- Xcode (najnovšia verzia)
- **Apple Developer účet** – $99/rok (povinné pre TestFlight a App Store; na vlastný iPhone na test môže stačiť bezplatný Apple ID, obmedzene)
- rovnaký Node.js projekt ako pre Android

### Prvý setup (raz)

Na Macu v priečinku projektu:

```bash
npm install @capacitor/ios --save
npx cap add ios
npm run cap:icons
npm run cap:ios
```

V Xcode:
1. vyber **Signing & Capabilities** → Team (Apple ID / Developer účet)
2. Bundle ID nech zostane **`com.ekidio.app`** (rovnaké ako Android)
3. pripoj iPhone alebo spusti simulátor → **Run** (▶)

### Ďalšie úpravy (po zmene Vue kódu)

Rovnako ako Android – najprv web build, potom sync:

```bash
npm run cap:ios:build
```

Potom v Xcode **Run** (▶).

### Google prihlásenie na iPhone

V [Google Cloud Console](https://console.cloud.google.com/apis/credentials) pridaj:

1. **OAuth klient typu iOS**
   - Bundle ID: `com.ekidio.app`
2. Web Client ID (už máš) nechaj – používa ho aj natívny plugin

V Xcode skontroluj, že v projekte je **URL scheme** pre Google (Capacitor plugin to zvyčajne doplní pri `cap sync`).

### Publikovanie

- **TestFlight** (beta testovanie) – v Xcode: **Product → Archive**, potom upload do App Store Connect
- **App Store** – rovnaký archive, review od Apple (trvá zvyčajne 1–3 dni)

Na iPhone **nie je priamy APK download** ako na Android – distribúcia ide cez TestFlight alebo App Store.

### Ak nemáš Mac

- požičať / prenajať Mac na pár hodín
- cloud build služba s macOS runnerom (napr. Codemagic, GitHub Actions)
- alebo nechať iOS build spraviť niekoho s Macom – kód ostáva ten istý Capacitor projekt

## Zmena cieľovej URL

Ak chceš iný entrypoint (napr. samostatnú WP stránku pre appku), uprav:

- `capacitor.config.json` → `server.url`


