# Slothsoft Unity

[![Packagist Version](https://img.shields.io/packagist/v/slothsoft/unity)](https://packagist.org/packages/slothsoft/unity)
[![PHP Version Support](https://img.shields.io/packagist/php-v/slothsoft/unity)](https://www.php.net/)
[![Documentation](https://img.shields.io/badge/docs-reference-blue.svg)](https://faulo.github.io/slothsoft-unity/)
[![Test Status](https://github.com/Faulo/slothsoft-unity/actions/workflows/ci-tests.yml/badge.svg)](https://github.com/Faulo/slothsoft-unity/actions/workflows/ci-tests.yml)
[![license badge](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Unity automation helpers for slothsoft packages and CI jobs.

This package wraps Unity Hub, Unity Editor batchmode, Unity project/package metadata, Steam build file generation, DocFX setup, and Farah executable assets behind PHP classes and Composer binaries. It is primarily used for repeatable CI workflows around Unity projects: install the required editor, prepare licensing, install modules, run tests, build players, execute editor methods, inspect project settings, and create temporary projects for local packages.

This is an older slothsoft package with active Unity automation code and some historical compatibility code. Most classes in `src/` are still live; the `Slothsoft\Unity\MLAgents` namespace is legacy and should not be used for new code.

## Compatibility Policy

Semantic versioning is in effect. Public classes are public API, including public constructors, methods, constants, and properties. Public signatures must remain backward-compatible unless an API-breaking change is specifically requested for a major release.

All code in this package must remain syntactically valid on the PHP version declared by `PHP_VERSION` in `.env` and behaviorally compatible with every PHP version covered by CI.

Bug fixes are allowed in every area of the package, including legacy APIs. Legacy APIs should not be used for new code, but they are still expected to keep working for existing consumers when practical.

Adding dependencies is acceptable when the dependency is justified by the change and remains compatible with this package's supported PHP versions.

## Current / Supported Areas

These parts are suitable for use in new or maintained Unity automation code:

- `Slothsoft\Unity\UnityHub`
  - Process-wide Unity Hub facade for locating Unity Hub, listing installed editors, installing editors and modules, resolving Unity changesets, preparing license files, and finding Unity projects or packages.
  - Uses `HubLocatorInterface` implementations for platform-specific Unity Hub command discovery.
- `Slothsoft\Unity\UnityEditor`
  - Wrapper around one Unity Editor installation.
  - Executes editor batchmode commands, installs editor modules, creates empty projects, and handles license activation retries.
- `Slothsoft\Unity\UnityProject` and `UnityProjectInfo`
  - Unity project model loaded from `ProjectSettings/ProjectVersion.txt`, `ProjectSettings/ProjectSettings.asset`, `Packages/manifest.json`, and `Packages/packages-lock.json`.
  - Supports project version reads/writes, PlayerSettings lookup, asset iteration, editor-method execution, Unity Test Runner execution, player builds, editor/module installation, and licensing checks.
- `Slothsoft\Unity\UnityPackage` and `UnityPackageInfo`
  - Unity package model loaded from `package.json`.
  - Creates temporary empty Unity projects with a local package installed under `Packages/`.
- `Slothsoft\Unity\UnityBuildTarget`
  - Maps package build targets (`windows`, `linux`, `mac`) and scripting backends to Unity module IDs, build output names, and batchmode build arguments.
- `Slothsoft\Unity\UnityEnvironment`
  - Reads Unity-related environment variables for no-graphics mode, Accelerator configuration, and log routing.
  - Provides colored formatting for command, stdout, stderr, license, and cache logs.
- `Slothsoft\Unity\UnityLicensor`
  - Browser-style workflow for signing Unity manual activation files when Unity credentials are available in the environment.
- `Slothsoft\Unity\ExecutionError`, `JUnit`, `JsonUtils`, and `MailboxAccess`
  - Shared support utilities for process failures, JUnit date formatting, JSON reads/writes, and mailbox lookup for external verification codes.
- `Slothsoft\Unity\LocateHubFromCommand`, `LocateHubFromWindowsRegistry`, and `LocateHubNull`
  - Unity Hub locator strategies used by `UnityHub`.
- `Slothsoft\Unity\Steam`
  - `AppBuild` generates Steam VDF app build files.
  - `SteamCMD` signs in through `steamcmd`, optionally retrieving Steam Guard codes through `MailboxAccess`.
- `Slothsoft\Unity\DocFX`
  - `Settings` creates DocFX configuration and table-of-contents files for Unity projects and supported documentation templates.
- `Slothsoft\Unity\DotNet`
  - `FormatLog` converts `dotnet format` JSON reports into DOM documents that can be transformed to JUnit XML.
- `Slothsoft\Unity\Git`
  - `GitProject` is a small git process wrapper used by older automation flows. It is live code, but be careful when using its mutating operations.
- `Slothsoft\Unity\Assets`
  - Farah executable builders and parameter filters that back the command-line tools and `farah://slothsoft@unity/...` asset URLs.
  - Project assets cover method execution, builds, tests, module installation, project version reads/writes, and project setting lookup.
  - Package assets cover temporary project creation with a local package installed.
  - Hub assets expose Unity Hub help output.
- `assets/`
  - Farah manifest, XSLT transformation assets, and DocFX templates used by the Composer binaries and Farah URLs.
- `scripts/`
  - Composer binaries for Unity, Steam, DocFX, version, and report-conversion workflows.

## Legacy Areas

These components are included for historical reasons. Do not use them for new code unless you are maintaining an existing workflow.

- `Slothsoft\Unity\MLAgents`
  - Legacy helpers for Python virtualenv setup and Unity ML-Agents training runs.
  - New ML-Agents workflows should use maintained Python and Unity tooling directly.

## Farah Assets

The package registers a Farah module with executable assets for Unity automation. Those assets are the implementation layer behind most Composer binaries.

Example asset groups:

```text
farah://slothsoft@unity/hub/help
farah://slothsoft@unity/project/method
farah://slothsoft@unity/project/method-junit
farah://slothsoft@unity/project/build
farah://slothsoft@unity/project/build-junit
farah://slothsoft@unity/project/tests
farah://slothsoft@unity/project/tests-junit
farah://slothsoft@unity/project/module
farah://slothsoft@unity/project/module-junit
farah://slothsoft@unity/project/version
farah://slothsoft@unity/project/setting
farah://slothsoft@unity/package/install
farah://slothsoft@unity/package/install-junit
```

See `assets/manifest.xml` for the canonical asset definitions and parameter filters.

## Command-Line Usage

Install dependencies first:

```bash
composer install
```

All commands are executed through Composer:

```bash
composer exec <command> -- <arguments>
```

Composer also accepts the historical form without `--`; the examples below keep that shorter style because the bundled scripts document it.

### `autoversion`

Determine the version of a repository based on its tags and commits.

```bash
composer exec autoversion plastic|git "path/to/repository"
```

### `steam-buildfile`

Create a Steam app build VDF file.

```bash
composer exec steam-buildfile "path/to/root" "path/to/logs" AppID [DepotID=path/to/depot]+ [SetLive]
```

### `steam-login`

Sign in to Steam through `steamcmd`.

Uses `STEAM_CREDENTIALS_USR` and `STEAM_CREDENTIALS_PSW`. If mailbox credentials are configured, `EMAIL_CREDENTIALS_USR` and `EMAIL_CREDENTIALS_PSW` may be used to retrieve a Steam Guard code.

```bash
composer exec steam-login
```

### `transform-dotnet-format`

Transform a `dotnet format` JSON report to JUnit XML and write the XML to stdout.

```bash
composer exec transform-dotnet-format "path/to/format-report.json"
```

### `unity-build`

Build a Unity project.

```bash
composer exec unity-build "path/to/project" ["path/to/build"] [Platform]
```

### `unity-documentation`

Create a DocFX documentation template in `.Documentation` below the project root. The generated template can be built from there with `dotnet tool run docfx`; generated HTML is written to `.Documentation/html`.

```bash
composer exec unity-documentation "path/to/project" [template=default+mermaid]
```

Supported templates:

- `default`
- `default+mermaid`
- `unity`
- `singulinkfx+mermaid`

### `unity-help`

Call `unityhub help`. This is a quick way to verify that Unity Hub can be located.

```bash
composer exec unity-help
```

### `unity-empty-project`

Create a new empty Unity project with the latest final editor version matching the requested version prefix.

```bash
composer exec unity-empty-project "path/to/new-project" [6000.0]
```

### `unity-method`

Run a specific editor method inside a Unity project and quit Unity afterward.

```bash
composer exec unity-method "path/to/project" "Method.To.Execute" ["additional params", ...]
```

### `unity-start`

Start a specific editor method inside a Unity project without forcing `-quit`.

The method must call `EditorApplication.Quit()` or otherwise arrange for Unity to exit.

```bash
composer exec unity-start "path/to/project" "Method.To.Execute" ["additional params", ...]
```

### `unity-module-install`

Install Unity modules for the editor used by a Unity project. Use `unity-help` to inspect available module IDs for the installed Unity Hub version.

```bash
composer exec unity-module-install "path/to/project" [module-id]+
```

### `unity-package-install`

Create a new Unity project and install a local package into it.

```bash
composer exec unity-package-install "path/to/project/Packages/path-to-package" "path/to/new-project"
```

### `unity-project-setting`

Read one PlayerSettings value from a Unity project.

```bash
composer exec unity-project-setting "path/to/project" "setting-name"
```

### `unity-project-version`

Read or write the `bundleVersion` of a Unity project.

```bash
composer exec unity-project-version "path/to/project" get|set ["new-version"]
```

### `unity-tests`

Run Unity Test Runner tests inside a Unity project.

```bash
composer exec unity-tests "path/to/project" [EditMode|PlayMode|Platform]+
```

## Environment

Useful environment variables:

- `UNITY_NO_GRAPHICS`
  - Set to `1` to pass `-nographics` to Unity Editor commands.
- `UNITY_ACCELERATOR_ENDPOINT`
  - Unity Accelerator endpoint in `ip:port` form.
- `UNITY_ACCELERATOR_PARAMS`
  - Additional Unity Accelerator command-line parameters.
- `UNITY_LOGGING`
  - Whitespace-separated log channels. Supported values are `all`, `none`, `stdin`, `stdout`, `stderr`, `licensor`, and `cache`.
- `UNITY_EMPTY_MANIFEST`
  - Path to the empty package manifest copied into temporary package-install projects.
- `UNITY_CREDENTIALS_USR` and `UNITY_CREDENTIALS_PSW`
  - Unity account credentials for manual activation signing.
- `STEAM_CREDENTIALS_USR` and `STEAM_CREDENTIALS_PSW`
  - Steam credentials for `steam-login`.
- `EMAIL_CREDENTIALS_USR` and `EMAIL_CREDENTIALS_PSW`
  - Mailbox credentials used to retrieve Steam Guard codes.

## Development Notes

Some APIs use global or static process state, especially `UnityHub`, `UnityEnvironment`, `UnityPackage`, the Hub locator configuration, and license/cache folders. Tests that exercise stateful behavior should isolate side effects with `@runInSeparateProcess`.

Tests may create temporary files through `temp_file`, `temp_dir`, or `Slothsoft\Core\IO\FileInfoFactory::createTempFile`; those helpers do not require manual cleanup. Files in `test-files/` are canonical fixtures.

The local development environment should provide the Composer development extensions. If an optional extension or platform feature is unavailable, affected tests may be skipped or impossible to run locally. Skipped tests should be treated as intentionally skipped unless the task is specifically about test skipping.

Use `.editorconfig` for coding style. Additional formatter or static-analysis tooling may be added later, but none is required right now.

Run tests with:

```bash
vendor/bin/phpunit
```

Generate API documentation with:

```bash
vendor/bin/phpdoc
```

## Installation

```bash
composer require slothsoft/unity
```

## Requirements

See `composer.json` for required PHP extensions and optional development extensions.
