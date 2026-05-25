# ekidio – mobilná aplikácia (Capacitor)

Tento projekt sa dá zabaliť ako „normálna“ Android/iOS aplikácia pomocou **Capacitor**.

## Ako to funguje (najjednoduchšie)

Táto appka je **native obal (WebView)**, ktorý otvára tvoju existujúcu WordPress SPA stránku:

- v `capacitor.config.json` je nastavené `server.url` na `https://lubomichalko.com/parent/`

Výhoda: nič nemusíme prerábať na backend autentifikácii/nonce/routingu – používa sa presne to, čo už funguje na webe.

## Android (Windows)

### Požiadavky

- Node.js + npm
- Android Studio
- Android SDK (Android Studio si ho doinštaluje)
- Java (Android Studio obsahuje vlastné JDK)

### Spustenie v Android Studiu

```bash
npm run cap:android
```

To spraví:

- `npm run build`
- `npx cap sync android`
- otvorí Android Studio (`npx cap open android`)

V Android Studiu potom daj **Run** (▶) na zariadenie/emulátor.

### Build (APK/AAB)

V Android Studiu:

- APK: **Build → Build Bundle(s) / APK(s) → Build APK(s)**
- AAB: **Build → Generate Signed Bundle / APK…** (vyber *Android App Bundle*)

## iOS (macOS)

iOS build sa dá robiť iba na macOS s Xcode:

```bash
npm i @capacitor/ios --save
npx cap add ios
npm run build
npx cap sync ios
npx cap open ios
```

Potom v Xcode spustíš na zariadenie alebo urobíš archiváciu pre App Store.

## Zmena cieľovej URL

Ak chceš iný entrypoint (napr. samostatnú WP stránku pre appku), uprav:

- `capacitor.config.json` → `server.url`


