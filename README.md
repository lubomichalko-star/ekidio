# ekidio - WordPress Plugin

Týždenný plán rodinných domácich prác s automatickou rotáciou úloh medzi deťmi.

## Funkcie

- **Týždenný prehľad úloh** - Zobrazenie všetkých úloh pre aktuálny týždeň zoskupených podľa detí
- **Správa detí** - Pridávanie a úprava profilov detí s možnosťou priradenia k balíčkom úloh
- **Balíčky úloh** - Zoskupovanie súvisiacich úloh do balíčkov
- **Správa úloh** - Vytváranie a úprava úloh s nastavením frekvencie a rotácie
- **Automatická rotácia** - Úlohy sa automaticky rotujú medzi deťmi každý týždeň
- **Manuálna regenerácia** - Možnosť manuálne regenerovať týždenný plán

## Inštalácia

1. Skopírujte priečinok pluginu do `wp-content/plugins/rodinne-ulohy/`
2. Aktivujte plugin cez WordPress admin panel
3. Plugin automaticky vytvorí potrebné databázové tabuľky

## Použitie

### Vytvorenie detí

1. Prejdite na **ekidio > Správa detí**
2. Kliknite na **Pridať nové dieťa**
3. Vyplňte meno a voliteľne URL avatara
4. Uložte

### Vytvorenie balíčka úloh

1. Prejdite na **ekidio > Balíčky úloh**
2. Kliknite na **Vytvoriť nový balíček**
3. Zadajte názov a popis balíčka
4. Vyberte deti, ktoré budú rotovať v úlohách tohto balíčka
5. Uložte

### Vytvorenie úlohy

1. Prejdite na **ekidio > Správa úloh**
2. Vyplňte základné informácie (názov, popis)
3. Nastavte frekvenciu a dni v týždni
4. Povoľte týždennú rotáciu (ak chcete, aby sa úloha automaticky rotovala)
5. Vyberte balíček úloh (voliteľné)
6. Uložte

### Týždenný prehľad

1. Prejdite na **ekidio > Týždenný prehľad**
2. Zobrazia sa všetky úlohy pre aktuálny týždeň zoskupené podľa detí
3. Môžete označiť úlohy ako hotové pomocou checkboxu
4. Kliknutím na **Regenerovať tento týždeň** sa vytvorí nový plán s rotovanými úlohami

## Automatická rotácia

Plugin automaticky rotuje úlohy každý týždeň. Úlohy s povolenou rotáciou sa priradia ďalšiemu dieťaťu v poradí. Rotácia sa vykonáva každý pondelok o polnoci.

## Štruktúra databázy

Plugin vytvára nasledujúce tabuľky:
- `wp_rodinne_ulohy_children` - Deti
- `wp_rodinne_ulohy_packages` - Balíčky úloh
- `wp_rodinne_ulohy_tasks` - Úlohy
- `wp_rodinne_ulohy_package_children` - Priradenie detí k balíčkom
- `wp_rodinne_ulohy_assignments` - Týždenné priradenia úloh

## Požiadavky

- WordPress 5.8 alebo novší
- PHP 7.4 alebo novší

## Licencia

GPL v2 alebo novšia

