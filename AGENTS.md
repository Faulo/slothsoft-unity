# AGENTS.md

Shared instructions for coding agents. Project-specific information is kept in [README.md](README.md), read it before non-trivial changes.

## Farah Module

This project is a Farah module. Its module assets live in `assets/`, with `assets/manifest.xml` as the public entry point. Treat published asset paths, manifest identifiers, parameters, and stream names as public API. Preserve them unless the task explicitly permits a breaking release.

Keep manifests, schemas, PHP registration code, fixtures, and documentation synchronized when the module contract changes. Test both lookup and the resolved result: a manifest entry existing is not sufficient if it produces the wrong stream, media type, transformation, or content.

## PHP

### Environment and tools

Agents run directly on the host, not inside a Docker container. Commands, paths, and tools therefore use the host environment unless explicitly run through DDEV.

This is a DDEV project. The default DDEV `web` container defines the package's development runtime. `PHP_VERSION` in `.env` is authoritative.

All package code must be syntactically valid on the PHP version declared by `PHP_VERSION`, runnable in DDEV, and compatible with every PHP version supported by CI. Do not rely on syntax, extensions, binaries, or platform features available only on the host.

Host PHP may be used for ad hoc code execution and supporting work. Successful host-side execution does not validate package compatibility; use DDEV for validation that depends on the package runtime.

When executing ad hoc PHP code from the shell, do not use inline snippets such as `php -r`, especially under PowerShell. Write code to a temporary `.php` file and execute that file with `php`. Use project temp-file helpers inside PHP code; remove shell-created probe files after use when appropriate.

Run all Composer commands through DDEV with `ddev composer ...`. Do not run Composer directly on the host. Do not install persistent host dependencies. Use `npx` for one-off Node.js tools; do not run `npm install` on the host.

### PHPUnit

The PHPUnit config is `phpunit.xml`. Run PHPUnit in DDEV unless debugging the host specifically:

```bash
ddev exec vendor/bin/phpunit
```

If an optional extension or platform feature is unavailable in DDEV, report which validation could not run and why. Treat skipped tests as intentional unless the task concerns test skipping.

Use `@runInSeparateProcess` for tests of APIs with global or static process state. When manually changing a test marked `@todo auto-generated`, remove the marker so the generator no longer treats it as disposable. Files in `test-files/` are canonical fixtures, not disposable output.

Tests may create temporary files through `temp_file`, `temp_dir`, or `Slothsoft\Core\IO\FileInfoFactory::createTempFile`; manual cleanup is not required for files managed by these helpers.

### PHPStan

The PHPStan config is `phpstan.neon.dist`. Run PHPStan in DDEV's default `web` container:

```bash
ddev exec vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress --error-format=json
```

PhpStorm MCP inspection tools (`get_file_problems`, `get_inspections`, and `lint_files`) have been observed to omit PHPStan diagnostics even when PHPStan validation is enabled in the IDE. Use those inspections alongside the explicit PHPStan run; an empty IDE inspection response is not a PHPStan pass. If a PHPStan run configuration is available, it can also be executed through MCP; read its complete `fullOutputPath` when the returned preview is truncated, and verify that it uses the DDEV interpreter and shared configuration.

### MCP validation

After creating or editing a file, always format it with PhpStorm's MCP `reformat_file` tool (`mcp.phpstorm.reformat_file`), passing the explicit project-relative paths of the files you changed. Do this before the final inspections and validation.

When an IDE MCP server is available, use it after editing code to review changes and retrieve inspections for touched files. Fix relevant in-scope findings and report remaining ones. An authorized push normally starts CI; do not trigger another build unless needed for diagnosis. Investigate relevant failures and report the job, commit SHA, and result. If changes are not pushed, report CI validation as pending. Do not push or trigger jobs without authorization.

### Documentation and style

The PHPDoc config is `phpdoc.xml`. Generate documentation in DDEV with `ddev exec vendor/bin/phpdoc`. `.editorconfig` is in effect.

## General

### Meta commands

These short messages have special handling when they appear alone in a user message:

- `ping`: Reply with `pong`.
- `?`: Continue the previous response or task after an interruption.
- `ticket <URI>`: Read the linked ticket and all comments through the available integration. Inspect the project, reproduce the current behavior, and run relevant checks as needed. Then explain the request, project context, reproducibility, risks, and a proposed implementation plan. Do not edit files, change remote state, commit, or push until the user approves the approach.

### Compatibility

Follow semantic versioning. Preserve backward compatibility for public APIs unless the task explicitly permits a breaking change.

### Project conventions

`.editorconfig` is authoritative. Never edit `.editorconfig` unless expressly instructed by the user.

### Git

Git mutations are forbidden by default. Agents may use read-only inspection commands such as `git status`, `git log`, `git diff`, `git show`, `git blame`, and `git branch --list` without additional permission.

An agent may perform Git mutations only after the user explicitly opts in. Permission is limited to the operations and task the user authorized; do not treat prior authorization as standing permission for later mutations.

When Git mutations are authorized:

- Do not change the current branch unless asked. Verify the working-tree status before editing and again before creating commits.
- Treat all unknown local changes as user work. Do not overwrite, stage, commit, restore, or otherwise alter them.
- Keep commits small and cohesive.
- Format agent-authored commits according to Conventional Commits 1.0.0: `<type>[optional scope]: <description>`.
- When working from a ticket, include the ticket key and URL in the commit footer.
- Before committing, read the configured Git author name and email. Keep the configured email, append the agent name once, in brackets to the configured author name (e.g. `Daniel Schulz (Codex)`), and pass that identity explicitly with `git commit --author`. Do not modify repository or global Git configuration.
- Do not force-push, amend, rebase, reset, or discard changes unless the user explicitly requests that specific operation.

### CI Infrastructure

Jenkins runs at `https://ci.slothsoft.net/`. Infrastructure reads are always permitted; triggering builds or making any other change requires explicit user consent.

Use these access methods:

- `ssh ci.slothsoft.net <command>` invokes the Jenkins CLI directly; it does not open a host shell. Useful read-only commands include `who-am-i`, `list-jobs`, and `console`.
- `ssh <host>` opens that server's shell.
- `docker --context <host> ...` talks to that server's Docker daemon. Always name the context explicitly.

The CI servers are:

- `groke`: Mörkö, Ubuntu; Jenkins controller and Swarm manager. SSH alias and Docker context: `groke`.
- `garl`: Garl, Ubuntu; Jenkins agent and Swarm worker for `groke`. SSH alias and Docker context: `garl`.
- `dende`: Dende, Windows Server 2019; domain controller and Hyper-V host for `popo`. SSH alias and Docker context: `dende`.
- `popo`: Mr. Popo, Windows Server 2019 Standard Core VM on `dende`; Jenkins agent and Swarm worker for `groke`. SSH alias and Docker context: `popo`.

## Composer Package

This project is a Composer package. Its `composer.json` lists the minimum versions of its dependencies.

### Validation

Use the PHP project's DDEV and PHPUnit workflow for local validation. Add focused tests for each changed asset or public behavior, and retain coverage for previously discovered regressions.

Keep cross-platform and supported-PHP integration coverage in `.jenkins/Jenkinsfile.groovy`. The project job lives under `https://ci.slothsoft.net/job/php-packages/` and matches the repository name. GitHub CI validates the supported PHP and dependency matrix; Jenkins validates the repository's Linux and Windows integration matrix. For either system, inspect the result for the exact commit and read the complete relevant logs.

### Release cycle

When the user has authorized the required release, Git, CI, tagging, and publication operations, complete every phase in order.

#### Phase 1: Build and validate the candidate

1. Implement the change, add or update its tests, and document user-visible behavior under `Unreleased` in `CHANGELOG.md`.
2. Run focused tests while developing, then run the complete local test suite.
3. Commit and push the candidate changes.
4. Watch the complete GitHub CI run and the matching job under `https://ci.slothsoft.net/job/php-packages/`for the pushed commit.
5. If either validation fails, fix the issue and repeat from step 2 with a new commit and push.

#### Phase 2: Prepare and tag the release

1. Select the next version according to semantic versioning. If a new development line begins, keep Composer's branch alias aligned with it.
2. Move the relevant `CHANGELOG.md` entries from `Unreleased` into a heading for the selected version and release date.
3. Commit and push the release preparation.
4. Validate that exact commit in both GitHub CI and the matching Jenkins job. If it fails, fix it and return to Phase 1, step 2.
5. Tag the exact commit, using the repository's existing version-tag format, and push the tag. Never reuse or move a published tag.
6. If the feature was based on a ticket, update the ticket's body to reflect the shipped design and mark it complete.

If the expected behavior or its test contract changes at any point, restart at Phase 1, step 1.
