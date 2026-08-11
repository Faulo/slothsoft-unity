# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.21.0] - Unreleased

### Added
- Added the Symfony Console executable `unity-command` as the new API for Unity CI operations.
- Added the `build`, `empty-project`, `method`, `start`, `module-install`, `package-install`, and `tests` subcommands.
- Added optional JUnit reporting to every `unity-command` operation. `--junit PATH` writes an atomic, schema-validated report file, while `--junit -` writes only the report XML to standard output.
- Added workspace-aware package installation that can initialize a missing or empty workspace or reuse an exact Unity project root.

### Changed
- New documentation prefers `unity-command` for Unity CI pipelines.
- `unity-command package-install` merges installation manifest data into an existing project and fully replaces an existing embedded-package directory.
- Unity editor versions and changesets are resolved through Unity's official Release API, with the legacy symbol history and archive retained as fallbacks.

### Compatibility
- Existing Composer binaries retain their 2.20 argument order, defaults, output, and exit behavior. They remain supported and emit no runtime deprecation warnings.
- Existing Farah base assets and `*-junit` assets remain available with unchanged behavior.
- In particular, legacy `unity-package-install` remains `PACKAGE WORKSPACE`; only the new `unity-command package-install` API uses `WORKSPACE PACKAGE`.


## [2.20.0] - 2026-07-18

### Added
- Added the Composer executable `unity-empty-project WORKSPACE [VERSION]`. It installs the latest final Unity editor in the requested version subtree and creates a new empty project. If `VERSION` is omitted, it uses the latest final Unity editor available.


## [2.19.0] - 2025-09-01

### Added
- Added composer executable "steam-login".


## [2.18.0] - 2025-05-19

### Added
- Added environment variables UNITY_EMPTY_MANIFEST.


## [2.17.0] - 2025-04-21

### Added
- Added MailboxAccess using the environment variables EMAIL_CREDENTIALS_USR and EMAIL_CREDENTIALS_PSW.


## [2.16.0] - 2025-04-20

### Added
- Added UnityLicensor using the environment variables UNITY_CREDENTIALS_USR and UNITY_CREDENTIALS_PSW.
