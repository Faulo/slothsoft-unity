# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Historical entries are reconstructed from Git tags and the changes between releases. Historical dates use the tag commit dates.

## [Unreleased]

## [2.23.3] - 2026-10-06

### Fixed
- Redacted the values of `UNITY_CREDENTIALS_PSW`, `STEAM_CREDENTIALS_PSW`, and `EMAIL_CREDENTIALS_PSW` from package-generated command-line diagnostics with `[REDACTED]`, including related exceptions and reports. Process arguments and output from Unity, Unity Hub, and SteamCMD are preserved. ([#8](https://github.com/Faulo/slothsoft-unity/issues/8))
- Corrected Unity Hub path tests to accept a configured installation path before any editor is installed and skip editor-directory checks when no editor is available.

## [2.22.2] - 2026-08-16

### Fixed
- Made valid Unity Test Runner XML authoritative even when Unity exits non-zero, and limited synthetic infrastructure errors to test modes that do not produce a usable report.

### Changed
- Enabled Composer's full binary compatibility mode to generate executable wrappers across platforms.

## [2.22.1] - 2026-08-15

### Fixed
- Made Unity module installation idempotent by skipping installed modules and verifying Unity Hub's module state after installation.

## [2.22.0] - 2026-08-14

### Fixed
- Reported inconclusive Unity tests as skipped and accepted valid empty test runs.

## [2.21.0] - 2026-08-12

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

## [2.20.1] - 2026-07-24

### Added
- Added the initial unity-command application infrastructure and UnityHubConfig for process output and execution settings.

### Fixed
- Restored cross-platform command tests.

## [2.20.0] - 2026-07-18

### Added
- Added the Composer executable unity-empty-project WORKSPACE [VERSION], selecting the latest final editor in the requested version subtree.

### Fixed
- Preserved the existing minimum-version selection behavior when adding latest-version selection to Unity Hub.

## [2.19.15] - 2026-07-13

### Fixed
- Updated Unity authentication for security challenges and verification-action selection.

### Changed
- Improved API typing, integration tests, documentation, and the DDEV development environment.

## [2.19.14] - 2026-04-29

### Fixed
- Recognized Unity 2021 missing-license messages and restored license checks instead of assuming a license.

## [2.19.13] - 2026-04-29

### Added
- Allowed empty-project creation without launching Unity and added the none logging mode.

### Changed
- Used filesystem-based project creation during package installation without requiring a license.

## [2.19.12] - 2026-04-25

### Changed
- Centralized Accelerator and no-graphics environment handling in UnityEnvironment.

## [2.19.11] - 2026-04-24

### Added
- Added UnityEnvironment for environment-based command logging, cache logging, and color formatting.

### Fixed
- Refreshed editor discovery when cached information is stale and corrected default logging behavior.

## [2.19.10] - 2026-04-11

### Fixed
- Corrected the project reference used while installing a package.

## [2.19.9] - 2026-04-09

### Fixed
- Passed --childModules when requesting editor and module installations.

## [2.19.8] - 2026-04-02

### Changed
- Required PHP 8.2 or later and updated dependencies and PHP CI coverage.

## [2.19.7] - 2026-03-12

### Fixed
- Corrected the Hub help asset's chunk-writer construction.

## [2.19.6] - 2025-11-18

### Fixed
- Restored the default empty-package manifest.

### Changed
- Moved manifest configuration to FileConfigurationField and updated dependencies and tests.

## [2.19.5] - 2025-09-13

### Changed
- Used the shared slothsoft/schema JUnit schema.

## [2.19.4] - 2025-09-11

### Fixed
- Handled SteamCMD exit codes 0 and 1 and used temporary files for SteamCMD execution.

### Added
- Added MailboxAccess::waitForLatestBy().

## [2.19.3] - 2025-09-10

### Fixed
- Enforced a five-minute deadline for Unity and Steam verification email polling.

## [2.19.2] - 2025-09-10

### Fixed
- Accepted Unity Hub editor listings with or without a comma before installed at.

### Changed
- Updated dependencies and expanded CI version coverage.

## [2.19.1] - 2025-09-03

### Changed
- Registered the Farah module and initialized the default empty-package manifest in the package bootstrap.

## [2.19.0] - 2025-09-01

### Added
- Added SteamCMD and the steam-login executable with email-based Steam Guard verification.

## [2.18.6] - 2025-09-01

### Changed
- Updated dependencies and guarded integration tests that require Hub or credentials.

## [2.18.5] - 2025-07-07

### Fixed
- Allowed up to five minutes for Unity verification emails to arrive.

## [2.18.4] - 2025-07-04

### Changed
- Moved API documentation publishing to GitHub Pages CI and updated locked dependencies.

## [2.18.3] - 2025-06-14

### Fixed
- Recognized Unity 6000 license status output.

## [2.18.2] - 2025-06-11

### Changed
- Raised explicit exceptions for missing or malformed JSON files.

## [2.18.1] - 2025-06-07

### Fixed
- Read UNITY_EMPTY_MANIFEST through getenv().

## [2.18.0] - 2025-05-19

### Added
- Added UNITY_EMPTY_MANIFEST to select a custom empty-project package manifest.

## [2.17.12] - 2025-04-22

### Fixed
- Improved Unity login form submission, redirects, verification, and code resend handling.

## [2.17.11] - 2025-04-21

### Changed
- Added diagnostics around Unity activation requests.

## [2.17.10] - 2025-04-21

### Fixed
- Removed an unsupported HTTP client timeout option.

## [2.17.9] - 2025-04-21

### Changed
- Added HTTP request timeouts to the Unity licensing client.

## [2.17.8] - 2025-04-21

### Fixed
- Corrected license-file paths and verification polling delays.

## [2.17.7] - 2025-04-21

### Fixed
- Returned a boolean from the licensing logging flag check.

## [2.17.6] - 2025-04-21

### Fixed
- Corrected syntax in licensing notice messages.

## [2.17.5] - 2025-04-21

### Added
- Added UNITY_CREDENTIALS_LOGGING to control licensing diagnostics.

## [2.17.4] - 2025-04-21

### Changed
- Tagged the same source revision as 2.17.3; no additional changes.

## [2.17.3] - 2025-04-21

### Fixed
- Flushed licensing diagnostic output immediately.

## [2.17.2] - 2025-04-21

### Fixed
- Prepared signed license files before activating them and added licensing response logs.

## [2.17.1] - 2025-04-21

### Fixed
- Corrected the verification-email time interval and restored licensing diagnostics.

## [2.17.0] - 2025-04-21

### Added
- Added MailboxAccess using EMAIL_CREDENTIALS_USR and EMAIL_CREDENTIALS_PSW, with email-based Unity verification.

## [2.16.3] - 2025-04-21

### Changed
- Included additional response diagnostics after login failures.

## [2.16.2] - 2025-04-21

### Changed
- Reported unexpected Unity login redirects as warnings.

## [2.16.1] - 2025-04-21

### Changed
- Expanded Unity login failure diagnostics with redirect information.

## [2.16.0] - 2025-04-21

### Added
- Added UnityLicensor and automated editor licensing using UNITY_CREDENTIALS_USR and UNITY_CREDENTIALS_PSW.

## [2.15.0] - 2025-03-20

### Added
- Added UNITY_ACCELERATOR_PARAMS for custom Accelerator arguments.

### Fixed
- Corrected the Composer development branch alias.

## [2.14.7] - 2025-03-20

### Fixed
- Invalidated stale editor caches and logged cached editor information.

## [2.14.6] - 2025-03-20

### Added
- Cached installed-editor listings.

## [2.14.5] - 2025-03-20

### Changed
- Assumed editor licensing by default and used the Accelerator upload-all-revisions flag.

## [2.14.4] - 2025-03-20

### Added
- Enabled timestamps in Unity editor logs.

## [2.14.3] - 2025-03-20

### Changed
- Replaced the Accelerator wait flag with flags for uploading existing imports and shader caches.

## [2.14.2] - 2025-03-20

### Changed
- Requested completion of Unity Accelerator uploads before process exit.

## [2.14.1] - 2025-03-03

### Fixed
- Handled Unity 6000 versions missing from symbol-server history.

## [2.14.0] - 2025-03-01

### Added
- Added unity-start for launching a Unity project.

## [2.13.15] - 2024-12-20

### Fixed
- Requested editor modules in one batch installation.

## [2.13.14] - 2024-12-09

### Changed
- Made autoversion use semantic versioning.

## [2.13.13] - 2024-11-03

### Fixed
- Loaded editor versions from Unity symbol-server history and resolved stable releases through the editor release pages.

## [2.13.12] - 2024-10-30

### Fixed
- Sorted the documentation table of contents and updated the Farah requirement.

## [2.13.11] - 2024-10-08

### Fixed
- Ignored malformed or empty lines in Unity Hub editor listings.

## [2.13.10] - 2024-10-08

### Fixed
- Adjusted SSL handling when loading remote editor release metadata.

## [2.13.9] - 2024-10-05

### Changed
- Regenerated API documentation and updated dependencies and test configuration.

## [2.13.8] - 2024-09-29

### Fixed
- Corrected the asset-manifest version.

### Changed
- Updated dependencies and repository metadata.

## [2.13.7] - 2024-09-25

### Fixed
- Removed the classmap-authoritative Composer configuration override.

## [2.13.6] - 2024-09-23

### Changed
- Updated Composer dependency requirements and locked dependencies.

## [2.13.5] - 2024-09-18

### Fixed
- Initialized the optional editor field used by lazy project loading.

## [2.13.4] - 2024-09-18

### Changed
- Deferred editor initialization until a project operation needs it.

## [2.13.3] - 2024-09-10

### Changed
- Allowed Linux Hub execution when xvfb-run is unavailable.

## [2.13.2] - 2024-09-07

### Fixed
- Directed Unity editor logging to process output.

## [2.13.1] - 2024-09-06

### Fixed
- Corrected the environment constant and applied the -nographics argument.

## [2.13.0] - 2024-09-06

### Added
- Added UNITY_NO_GRAPHICS to control headless editor execution.

## [2.12.5] - 2024-09-02

### Fixed
- Initialized optional editor changesets before use.

## [2.12.4] - 2024-09-02

### Fixed
- Read editor changesets from project version files and updated changeset extraction from Unity archive pages.

## [2.12.3] - 2024-07-01

### Fixed
- Used JsonUtils::save() when persisting package metadata.

## [2.12.2] - 2024-07-01

### Fixed
- Corrected the JSON writer method referenced by savePackage().

## [2.12.1] - 2024-06-30

### Fixed
- Moved JsonUtils into the package source so it is available outside tests.

## [2.12.0] - 2024-06-30

### Added
- Added savePackage() and saveManifest() for package and project metadata.

## [2.11.2] - 2024-05-31

### Changed
- Improved .NET formatting test-case names in JUnit output.

## [2.11.1] - 2024-05-31

### Changed
- Grouped .NET formatting diagnostics by file in JUnit reports and sorted reported changes.

## [2.11.0] - 2024-05-30

### Changed
- Updated the Composer development branch alias to 2.11; no runtime source changes from 2.10.0.

## [2.10.0] - 2024-05-30

### Added
- Added DotNet FormatLog and transform-dotnet-format for converting formatting reports into JUnit XML.

## [2.9.3] - 2024-05-30

### Fixed
- Corrected Composer autoloader fallback handling in the executables.

### Changed
- Expanded unity-build help with default paths and supported platforms.

## [2.9.2] - 2024-04-20

### Fixed
- Handled unresolved workspace paths when creating report names.

## [2.9.1] - 2024-04-01

### Fixed
- Updated the Unity editor archive URL used for changeset lookup.

## [2.9.0] - 2024-03-12

### Added
- Added UNITY_ACCELERATOR_ENDPOINT to configure Unity Accelerator.

## [2.8.2] - 2024-03-12

### Fixed
- Updated the Microsoft documentation cross-reference service endpoint.

## [2.8.1] - 2024-03-07

### Fixed
- Pinned Spyc to 0.6.2 and handled Unity Hub editor-list line endings across platforms.

## [2.8.0] - 2023-08-28

### Changed
- Added compatibility with PHP 8.2.

## [2.7.1] - 2023-07-28

### Fixed
- Printed setting text instead of its XML wrapper.

## [2.7.0] - 2023-07-28

### Added
- Added unity-project-setting for reading Unity project settings.

## [2.6.4] - 2023-04-18

### Added
- Added filterConfig.yml for documentation filtering.

## [2.6.3] - 2023-04-14

### Fixed
- Added syntax highlighting to generated documentation.

## [2.6.2] - 2023-04-14

### Fixed
- Corrected Mermaid rendering in the documentation template.

## [2.6.1] - 2023-04-11

### Changed
- Sorted discovered projects for consistent output.

## [2.6.0] - 2023-04-11

### Added
- Added a template option to unity-documentation.

## [2.5.10] - 2023-04-05

### Fixed
- Corrected documentation table-of-contents generation and filesystem access.

## [2.5.9] - 2023-04-05

### Changed
- Switched generated documentation to the Singulink template.

## [2.5.8] - 2023-04-04

### Fixed
- Moved package editor-license validation to result generation.

## [2.5.7] - 2023-04-04

### Fixed
- Improved empty-project and package creation, including Unity 2019 compatibility, unityRelease metadata, and optional packages-lock.json files.

## [2.5.6] - 2023-04-04

### Changed
- Allowed package installation to begin without a pre-existing editor license.

## [2.5.5] - 2023-03-21

### Fixed
- Retried Unity execution after exit code 199 and retained process exit codes in ExecutionError.

## [2.5.4] - 2023-03-20

### Fixed
- Handled empty documentation directories.

## [2.5.3] - 2023-03-10

### Fixed
- Corrected documentation directory discovery.

## [2.5.2] - 2023-03-10

### Added
- Added documentation-folder selection and support for Documentation~, Documentation, and LICENSE.md.

### Changed
- Moved generated documentation into .Documentation and reworked metadata generation.

## [2.5.1] - 2023-03-10

### Added
- Included README.md and CHANGELOG.md in generated documentation.

### Fixed
- Corrected exclusion of plugin assemblies from documentation.

## [2.5.0] - 2023-03-07

### Added
- Added unity-documentation and DocFX settings for Unity API documentation.

## [2.4.7] - 2023-03-06

### Fixed
- Normalized Steam build-file fields to UTF-8 when supplied in Windows encodings.

## [2.4.6] - 2023-02-18

### Fixed
- Allowed license checks to inspect unsuccessful process results without throwing.

## [2.4.5] - 2023-02-18

### Added
- Added optional process exit-code validation.

## [2.4.4] - 2023-02-18

### Added
- Added configurable throwOnFailure behavior for execution errors.

## [2.4.3] - 2023-01-27

### Fixed
- Handled a missing Standalone scripting-backend setting.

## [2.4.2] - 2022-12-13

### Fixed
- Corrected project version and scripting-backend value types.

## [2.4.1] - 2022-10-04

### Fixed
- Explicitly used UTF-8 for generated XML documents.

## [2.4.0] - 2022-09-17

### Added
- Added autoversion and unity-project-version executables.

## [2.3.5] - 2022-08-31

### Fixed
- Corrected project and package names in operation reports.

## [2.3.4] - 2022-08-29

### Changed
- Renamed the macOS build-target value from mac_os to mac.

## [2.3.3] - 2022-08-29

### Fixed
- Accepted build outputs that are directories, including application bundles.

## [2.3.2] - 2022-08-28

### Fixed
- Adjusted installed editor file permissions so Unity can access Android SDK and JDK files.

## [2.3.1] - 2022-08-27

### Fixed
- Corrected the Composer development branch version alias.

## [2.3.0] - 2022-08-27

### Added
- Added unity-help and unity-module-install executables and module-installation JUnit output.

## [2.2.2] - 2022-08-16

### Fixed
- Corrected executable checks and error messages in process and JUnit diagnostics.

## [2.2.1] - 2022-08-16

### Fixed
- Improved package execution, compile-error reporting, test timeouts, and exception handling.

## [2.2.0] - 2022-08-13

### Added
- Added UnityPackage and UnityPackageInfo, package test assets, and the unity-package-install executable.

## [2.1.2] - 2022-08-11

### Fixed
- Corrected license exchange file handling.

## [2.1.1] - 2022-08-08

### Fixed
- Improved detection of licensed Unity editors.

## [2.1.0] - 2022-08-08

### Added
- Added steam-buildfile and Steam app-build configuration with support for multiple depots.

## [2.0.0] - 2022-08-03

### Added
- Added the unity-build, unity-tests, and unity-method Composer executables, build-target/module handling, and JUnit reports for project operations.

### Changed
- Added Linux Hub discovery, centralized process execution and timeouts, and required PHP 7.4 or later.

### Removed
- Removed the old daemon and unused GitHub automation scripts and assets.

## [1.2.8] - 2022-04-04

### Fixed
- Handled projects without an installed editor and corrected execution arguments and Git tracking.

## [1.2.7] - 2022-01-09

### Fixed
- Corrected the editor discovery method call.

## [1.2.6] - 2022-01-09

### Added
- Added ensureEditorIsInstalled() and refreshed editor discovery after installation.

## [1.2.5] - 2022-01-08

### Added
- Added Git fetch and checkout-with-tracking helpers.

## [1.2.4] - 2022-01-08

### Changed
- Improved API type documentation.

## [1.2.3] - 2022-01-07

### Fixed
- Corrected Unity test result generation.

## [1.2.2] - 2022-01-07

### Fixed
- Handled invalid project directories safely.

## [1.2.1] - 2022-01-07

### Added
- Added project setting lookup through hasSetting() and getSetting().

### Fixed
- Fixed project discovery and Git checkout error handling.

## [1.2.0] - 2022-01-07

### Changed
- Reworked project discovery around UnityProjectInfo and consolidated Git project handling.

### Removed
- Removed the retired UnityCourse, UnityCourseStudent, and Testing project classes.

## [1.1.0] - 2022-01-05

### Added
- Added Unity Hub and editor APIs, editor discovery and installation, and Farah assets for Hub help and project operations.

## [1.0.0] - 2020-12-25

### Added
- Initial release with Unity project, Git project, and ML-Agents helpers.
