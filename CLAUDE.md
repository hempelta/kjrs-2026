# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projekt

Relaunch **KJRS 2026** (kjrs.de) — TYPO3 **v14.3** auf dem **SiteKit von Oliver Thiele**, PHP 8.5,
lokal per DDEV (`kjrs-2026`, MariaDB 11.8, Apache-FPM). Composer-Projekt `fizsoft/kjrs-2026`.

Es ist ein SiteKit-Projekt: Die Developer Guidelines (`/Users/axel/PROJECTS/developer-guidelines`,
Einstieg `AGENTS.md`) gelten **inklusive** `guidelines/typo3/sitekit.md`. Erst nachsehen, wie das
SiteKit es löst (`vendor/oliverthiele/…`, Referenzsystem `ssh sitekit-demo`), dann selbst bauen.

## Befehle

Alles, was PHP/TYPO3 braucht, läuft im Container — `ddev exec …` bzw. `ddev composer …`,
`ddev typo3 …`. Auch der Frontend-Build läuft am zuverlässigsten im Container
(`ddev composer fe-build`): Die Webpack-Kette braucht Node ≥ 20.9, auf dem Mac liegt unter
`/usr/local/bin` ein Node 18, der mit `toSorted is not a function` abbricht.

| Zweck | Befehl |
|---|---|
| Umgebung starten | `ddev start` |
| Extensions einrichten, Schema, Caches | `ddev composer ext-setup` |
| Cache leeren | `ddev typo3 cache:flush` |
| DB-Dump ohne Caches/Sessions/Logs → `data/dev_dump.sql` | `ddev composer db-export` |
| DB-Dump einspielen | `ddev composer db-import` |
| Gezipptes Backup nach `data/` | `ddev composer db-backup` |
| Frontend-Build (Produktion) | `ddev composer fe-build` |
| Watcher im Hintergrund / stoppen | `composer fe-watch` / `composer fe-watch-stop` |
| Webpack-Dev-Server | `composer fe-start` |
| Alle PHP-Prüfungen in Guideline-Reihenfolge | `ddev composer code-quality` |
| … einzeln: PHPStan (Level 7) / CS Fixer / CodeSniffer (PSR-12) | `ddev composer phpstan` / `php-cs-fixer` / `php-codesniffer` |
| Rector | `ddev exec vendor/bin/rector process --dry-run` |

Alle drei Prüfwerkzeuge arbeiten auf `packages/`. `php-cs-fixer` **korrigiert** (kein Dry-Run,
so verlangt es `php.md`).

Tests gibt es noch keine (`typo3/testing-framework` ist nur als Abhängigkeit vorhanden).

## Architektur

**Sitepackage:** `packages/fiz-kjrs` (Extension-Key `fiz_kjrs`, Paket `fizsoft/fiz-kjrs`), per
Composer-Path-Repository als Symlink eingebunden. In Git liegt **nur dieses eine** Paket aus
`packages/` (siehe `.gitignore`); alles andere unter `packages/` ist lokal.

**Konfiguration läuft über Site Sets, nicht über statische TypoScript-Templates:**
- `config/sites/main/config.yaml` hängt am Set `fizsoft/fiz-kjrs` und definiert die Basis-URLs je
  Kontext (Live `kjrs.de`, `Production/Staging` → `stage.kjrs.de`, `Development/Local` →
  `kjrs-2026.ddev.site`). Nur Deutsch, `rootPageId: 1`.
- `packages/fiz-kjrs/Configuration/Sets/FizKjrs/config.yaml` legt fest, welche SiteKit-Sets aktiv
  sind (auskommentierte Zeilen = bewusst deaktiviert). Ein neues CE/Feature aus einer
  SiteKit-Extension wird hier zugeschaltet.
- `Configuration/SiteKit.yaml` ist die SiteKit-Registry: CE-Gruppen im Wizard per `overrides`
  (`textmedia`/`html` sind entfernt), Drittextensionen ohne eigene `SiteKit.yaml` per `elements`.

**Template-Layer (sitekit.md):** Überschreibungen nie im Core oder in Vendor-Extensions, sondern
über die Root-Path-Priorität — 0 Core · 11–59 Extensions · 60 SiteKit Base · 70 Themes ·
**80 Sitepackage**. Jeder Template-Pfad enthält `{$sitekit.frameworks.frontend.directory}/`.

**Umgebungskonfiguration:** `config/system/additional.php` wählt nach `TYPO3_CONTEXT` die
`.env`-Datei (vlucas/phpdotenv) — `Development/Local` (DDEV) und `Development` →
`.env.development`, `Production/Staging` → `.env.staging`, `Production` → `.env`. Unter DDEV
überschreibt danach ein eigener Block DB (`db`/`db`), ImageMagick und Mail (Mailpit,
`localhost:1025`). Der Mailcatcher-Hook (`MailcatcherState::wireMailTransport()`) muss am
Dateiende bleiben. Vorlage für Variablen: `.env.example`.

**Frontend-Build:** `Build/Default` (Webpack 5, Bootstrap 5.3, Sass, Font Awesome Pro 7). Jede
Datei in `Build/Default/EntryPoints/` wird ein eigenes Bundle. Ausgabe nach
`vendor/oliverthiele/ot-febuild/Resources/Public/Assets/` (also **nicht** versioniert), eingebunden
als `EXT:ot_febuild/…`; `Website/SVG` ist die komplette Font-Awesome-Pro-Symbolsammlung. Alias `@sitekit-components` zeigt auf
`vendor/oliverthiele/ot-sitekit-base/Resources/Private/Components`. Icon-Umbenennungen für
Font Awesome 7: `packages/fiz-kjrs/Configuration/Mapping/FontAwesome_7.php`.

## Deployment

Deployer 8 (`deploy.php`), Ziele `stage` (Branch `develop`, `Production/Staging`) und `live`
(Branch `main`, `Production`). Aufruf auf dem Mac: `vendor/bin/dep deploy stage`.

**Hostdaten stehen nicht im Repo** (es ist öffentlich). `deploy.php` und die `bin-dev`-Skripte
lesen sie aus den lokalen, ignorierten Dateien `.env` (live) bzw. `.env.staging` (stage):
`REMOTE_SSH_ALIAS` (Alias aus `~/.ssh/config`), `REMOTE_PROJECT_ROOT`, `DOMAIN`, optional
`PHP_BIN`. Fehlt etwas, bricht `deploy:check` mit dem Namen der fehlenden Variable ab.

Was der Ablauf über das Standardrezept hinaus tut — die Begründungen stehen in `deploy.php`:
- `build:frontend` baut **in DDEV**; `upload:frontend` überträgt das Bauergebnis, die
  Symbolsammlung wird bei gleicher Dateiliste per Hardlink aus `current` übernommen.
- `deploy:siteconfig` sichert im Backend geänderte `config/sites` nach
  `shared/siteconfig-backups/`, bevor das Release sie überschreibt — das Repo bleibt die Quelle.
- `deploy:opcache` setzt den FPM-Opcache per HTTP zurück und **belegt**, dass der Webprozess das
  neue Release ausführt; `deploy:proof` lässt den Deploy sonst am Ende scheitern. Eine Stage
  hinter Passwortschutz braucht `DEPLOY_HTTP_AUTH` oder eine Ausnahme für
  `_dep_opcache_reset_*.php` in ihrer geteilten `.htaccess`.
- `deploy:languages` lädt Sprachpakete nur nach, wenn `shared/var/labels` leer ist.
- `deploy:publish` aus dem Rezept wird bewusst **nicht** benutzt: Es meldet Erfolg vor dem
  Opcache-Beleg.

Geteilt über alle Releases: `auth.json`, `.env`, `.env.staging`, `public/.htaccess`,
`.htpasswd`, `fileadmin`, `typo3temp`, `var/labels`. `config/system/additional.php` ist
versioniert und **nicht** geteilt.

Hilfsskripte in `bin-dev/` (Argument `live` oder `stage`): `fetch-db.sh` (DB-Abzug nach `sql/`
und Import in DDEV), `sync-fileadmin.sh` (spiegelt fileadmin mit `--delete`),
`composerUpdate.sh` (Update auf `develop` → Prüfungen → Merge `--no-ff` nach `main` → Deploy
stage, dann live).

Aus der SiteKit-Vorlage stammen noch `copyLiveToDdev.sh` und `copyDdevToDev.sh` (rsync ohne
Deployer, `REMOTE_HOST` leer → brechen ab). Sie überschneiden sich mit den Skripten oben.
