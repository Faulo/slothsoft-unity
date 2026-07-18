# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - Unreleased

### Added
- Added the optional `--junit PATH` argument to `unity-build`, `unity-empty-project`, `unity-method`, `unity-start`, `unity-module-install`, `unity-package-install`, and `unity-tests`. JUnit reports are written directly to `PATH`; `--junit -` writes the report to standard output.

### Changed
- **Breaking:** Unity CI commands no longer emit JUnit XML by default. Without `--junit`, they produce regular command output and communicate success or failure through their exit code.
- **Breaking:** Changed `unity-package-install` argument order from `PACKAGE WORKSPACE` to `WORKSPACE PACKAGE`, making `WORKSPACE` the first positional argument consistently across Unity project commands.
- Changed `unity-package-install` to reuse `WORKSPACE` when it already contains a Unity project. It creates an empty project only when no project exists, so running `unity-empty-project WORKSPACE` followed by `unity-package-install WORKSPACE PACKAGE` has the same result as running `unity-package-install WORKSPACE PACKAGE` alone.

### Migration
- Replace JUnit output redirection such as `composer exec unity-build -- WORKSPACE > reports/build.xml` with `composer exec unity-build -- --junit reports/build.xml WORKSPACE`.
- Replace `composer exec unity-package-install -- PACKAGE WORKSPACE` with `composer exec unity-package-install -- WORKSPACE PACKAGE`.


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
