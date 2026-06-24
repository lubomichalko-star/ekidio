# Deploy len cez GitHub (bez PC)

Hosting **blokuje FTP z GitHub cloudu**, preto deploy nejde priamo z Actions cez FTP.
Riešenie: GitHub zbuildí plugin a publikuje vetvu **`deploy`**, hosting si ju **sám stiahne**.

## Ako pracuješ

1. Uprav kód na **github.com** (web editor, Codespaces, alebo push odkiaľkoľvek)
2. Zmeny mergni / pushni do vetvy **`main`**
3. GitHub Actions spustí workflow **Build and publish deploy branch**
4. Hosting stiahne vetvu **`deploy`** do `wp-content/plugins/ekidio`

Na PC nič spúšťať nemusíš.

## Nastavenie hostingu (raz)

V administrácii hostingu (nameserver.sk / panel) nájdi sekciu typu:

- **Git**
- **Deploy z GitHubu**
- **Verziovanie**

Nastav:

| Pole | Hodnota |
|------|---------|
| Repozitár | `https://github.com/lubomichalko-star/ekidio.git` |
| Vetva | `deploy` |
| Cieľová cesta | `/home/html/ekidio.com/public_html/wp-content/plugins/ekidio` |

Účet `github.ekidio.com` (FTP) už smeruje do toho istého priečinka – git deploy by mal ísť sem.

Ak panel Git neponúka, napíš podpore hostingu:

> Chcem automatický deploy z GitHubu (vetva deploy) do wp-content/plugins/ekidio. FTP z externých IP nefunguje, potrebujem git pull / webhook deploy.

## Overenie

1. GitHub → **Actions** → posledný beh musí byť zelený
2. GitHub → prepni vetvu na **`deploy`** – uvidíš `rodinne-ulohy.php`, `includes/`, `dist/`
3. ekidio.com → **Ctrl+F5** → skontroluj verziu na login stránke

## Ručný re-deploy

GitHub → **Actions** → **Build and publish deploy branch** → **Run workflow**

## Záložný spôsob (len ak treba)

Ak hosting nemá git deploy, jediné možnosti sú:

- zmeniť hosting (VPS s SSH), alebo
- `npm run deploy:ftp` z PC (FileZilla funguje len z tvojej siete)
