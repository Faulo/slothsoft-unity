# AGENTS.md

Shared instructions for coding agents working in Slothsoft packages. Keep package-specific purpose, architecture, commands, and notes in README.md.

## Meta Commands

These short messages have special handling when they appear alone in a user message:

- `ping`: Reply with `pong`.
- `.`: Reply with `.`.
- `?`: Continue the previous response or task after an interruption.
- `ticket <URI>`: Treat `<URI>` as a Jira ticket link. Read the ticket and all comments through MCP. Inspect the repository and run the game build as needed. Then explain the ticket, relevant code context, local reproducibility, and a proposed implementation plan. Do not edit files, change remote state, commit, or push until the user approves the approach.

## Environment and Tools

Agents run directly on the host, not inside a Docker container. Commands, paths, and tools therefore use the host environment unless explicitly run through DDEV.

This is a DDEV project. The default DDEV `web` container defines the package's development runtime. `PHP_VERSION` in `.env` is authoritative.

All package code must be syntactically valid on the PHP version declared by `PHP_VERSION`, runnable in DDEV, and compatible with every PHP version supported by CI. Do not rely on syntax, extensions, binaries, or platform features available only on the host.

Host PHP may be used for ad hoc code execution and other supporting work needed to complete a task. A successful host-side execution does not validate package compatibility; use DDEV for validation that depends on the package runtime.

When executing ad hoc PHP code from the shell, do not use inline PHP snippets such as `php -r`, especially under PowerShell. Write the code to a temporary `.php` file and execute that file with `php`. Use existing temp-file helpers when working inside PHP code; for shell-only probes, create a normal temporary file and remove it after the command if appropriate.

Run all Composer commands through DDEV, using `ddev composer ...`. Do not run Composer directly on the host.

Do not install persistent software or dependencies on the host. Use `npx` for one-off Node.js tools; do not run `npm install` on the host.

## Git

Git mutations are forbidden by default. Agents may use read-only inspection commands such as `git status`, `git log`, `git diff`, `git show`, `git blame`, and `git branch --list` without additional permission.

An agent may perform Git mutations only after the user explicitly opts in. Permission is limited to the operations and task the user authorized; do not treat prior authorization as standing permission for later mutations.

When Git mutations are authorized:

- The user is responsible for choosing the branch. Verify the current branch and working-tree status before making edits and again before creating commits.
- Treat all unknown local changes as user work. Do not overwrite, stage, commit, restore, or otherwise alter them.
- Keep commits small and cohesive.
- Format every agent-authored commit according to Conventional Commits 1.0.0: `<type>[optional scope]: <description>`. Use lowercase common types such as `feat`, `fix`, `docs`, `test`, `refactor`, `build`, `ci`, or `chore`.
- When working from a Jira ticket, include the ticket key and URL in the commit footer.
- Before committing, read the configured Git author name and email. Keep the configured email, append the agent name once to the configured author name, for example `Daniel Schulz (Codex)`, and pass that identity explicitly with `git commit --author`. Do not modify repository or global Git configuration for this purpose.
- Do not force-push, amend, rebase, reset, or discard changes unless the user explicitly requests that specific operation.

## Validation

### PHPUnit

The PHPUnit config is `phpunit.xml`. Run all PHPUnit invocations, including filtered and targeted runs, in DDEV's default `web` container:

```bash
ddev exec vendor/bin/phpunit
```

Never run PHPUnit on the host. Start DDEV first when necessary. If an optional extension or platform feature is unavailable in DDEV, report which validation could not run and why. Treat skipped tests as intentional unless the task concerns test skipping.

When writing tests for APIs with global or static process state, use `@runInSeparateProcess` to avoid leaking side effects between tests.

PHPUnit tests marked with `@todo auto-generated` are controlled by the test generator. If you manually change one of these tests, remove the `@todo auto-generated` marker in the same edit so the generator does not treat it as disposable/regenerable.

Tests may write temporary files through `temp_file`, `temp_dir`, or `Slothsoft\Core\IO\FileInfoFactory::createTempFile`. Manual cleanup is not required for those helpers.

Files in `test-files/` are canonical fixtures. Do not treat them as disposable output.

### MCP

If an IDE MCP server is available, use it after editing code to review the changes and retrieve inspections for the touched files. Treat IDE review as part of validation alongside tests and syntax checks. Address relevant findings when safe and within scope, and report any remaining findings.

If a CI MCP server is available, use it to validate the exact commit SHA of every agent-authored commit. Authorized pushes start CI automatically; do not trigger jobs manually unless the task is to debug the CI pipeline. If a commit has not been pushed, report that CI validation is pending rather than pushing without permission. Investigate failures related to the change and report the job, commit SHA, and final result.

## Documentation and Style

The PHPDoc config is `phpdoc.xml`. Generate documentation in DDEV:

```bash
ddev exec vendor/bin/phpdoc
```

`.editorconfig` is in effect.

## Agent Workflow

- Work from the current package root.
- Read `README.md` for package-specific context before making non-trivial changes.
- Semantic versioning is in effect. Keep public classes, constructors, methods, constants, properties, and signatures backward-compatible unless the user explicitly requests a major-version API change.
- Prefer fast local inspection tools before making changes.
- Keep edits within the requested task and relevant package boundary; do not perform unrelated refactoring.
- Use normal patch/edit tools for manual edits. Avoid shell write tricks that make changes hard to review.
- Do not use destructive cleanup commands or revert user work unless explicitly asked for that exact operation.
