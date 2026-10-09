# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Add EXT:redirects; automatic redirects and slug updates are disabled until go-live (#2)
- Add redirect list for the old kjrs.de URLs and `bin-dev/import-redirects.php` (#2)

### Fixed

- Fix Composer resolution of the sitepackage on branches other than `main` (`@dev` instead of `dev-main`)

## [0.1.0] — 2026-10-09

### Added

- Add TYPO3 v14 SiteKit setup with the sitepackage `fizsoft/fiz-kjrs`
- Add frontend build setup in `Build/Default` (webpack, Bootstrap 5, Sass) (#1)
- Add Deployer configuration with targets `stage` and `live`; host data is read from git-ignored env files (#1)
- Add opcache reset with proof that the web process serves the new release; the deploy fails otherwise (#1)
- Add backup of backend changes to `config/sites` before a release overwrites them (#1)
- Add `bin-dev` scripts for database dump, fileadmin sync and Composer update (#1)
- Add `CLAUDE.md` (#1)

### Changed

- Load `.env.development` in the DDEV context (#1)
- Apply non-breaking `npm audit` fixes to `package-lock.json` (#1)

### Fixed

- Fix sitepackage template paths that pointed to the non-existent `EXT:ot_sitepackage` (#1)
- Fix `composer code-quality`: define the referenced scripts and repair the PHPStan, CodeSniffer and CS Fixer configuration (#1)
- Fix `.env.example` variable `DB_DB`, renamed to `DB_NAME` as required by `additional.php` (#1)
- Fix `.gitignore` pattern so `.ddev/config.yaml` is versioned (#1)
- Fix watcher detection in `feWatch.sh` on macOS (#1)

### Removed

- Remove `copyLiveToDdev.sh` and `copyDdevToDev.sh`, superseded by Deployer and the new `bin-dev` scripts (#1)
