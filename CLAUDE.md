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
`ddev typo3 …`. Der Frontend-Build läuft auf dem Host (npm im Ordner `Build/Default`).

| Zweck | Befehl |
|---|---|
| Umgebung starten | `ddev start` |
| Extensions einrichten, Schema, Caches | `ddev composer ext-setup` |
| Cache leeren | `ddev typo3 cache:flush` |
| DB-Dump ohne Caches/Sessions/Logs → `data/dev_dump.sql` | `ddev composer db-export` |
| DB-Dump einspielen | `ddev composer db-import` |
| Gezipptes Backup nach `data/` | `ddev composer db-backup` |
| Frontend-Build (Produktion) | `composer fe-build` |
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
Datei in `Build/Default/EntryPoints/` wird ein eigenes Bundle; Ausgabe über
`public/_assets`. Alias `@sitekit-components` zeigt auf
`vendor/oliverthiele/ot-sitekit-base/Resources/Private/Components`. Icon-Umbenennungen für
Font Awesome 7: `packages/fiz-kjrs/Configuration/Mapping/FontAwesome_7.php`.

**Sync-Skripte** in `bin-dev/` (`copyLiveToDdev.sh`, `copyDdevToDev.sh`) laufen per rsync/ssh. Das
Ziel (`REMOTE_HOST`) ist noch nicht eingetragen, ohne Ziel brechen beide Skripte ab.
