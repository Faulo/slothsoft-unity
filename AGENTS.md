# AGENTS.md

Shared instructions for coding agents working in slothsoft packages. Keep package-specific purpose, architecture, commands, and notes in README.md.

## Runtime Environment

The file `.env` is the source of truth for runtime configuration. In particular, `PHP_VERSION` is authoritative.

All code must be syntactically valid on the PHP version declared by `PHP_VERSION` in `.env`. Behavior must remain compatible with every PHP version supported by CI. Do not use syntax newer than the `.env` PHP version even if the local runtime supports it.

The machine is configured so `php` and `composer` use the correct PHP version. Run them directly.

When executing ad hoc PHP code from the shell, do not use inline PHP snippets such as `php -r`, especially under PowerShell. Write the code to a temporary `.php` file and execute that file with `php`. Use existing temp-file helpers when working inside PHP code; for shell-only probes, create a normal temporary file and remove it after the command if appropriate.

## Tools

Use these tools directly:

| Tool       | Use for                       |
|------------|-------------------------------|
| `composer` | PHP dependencies              |
| `php`      | Running PHP                   |
| `npx`      | One-off npm package execution |

`git` is read-only for agents. Use inspection commands such as `git status`, `git log`, `git diff`, `git show`, `git blame`, and `git branch --list` as needed. Never run mutating git commands such as `git commit`, `git add`, `git push`, `git pull`, `git merge`, `git rebase`, `git checkout`, `git switch`, `git reset`, `git stash`, `git tag`, or branch deletion.

The user handles all version control.

If the user says `ping`, reply with `pong` and nothing else.

## Testing

The PHPUnit config is `phpunit.xml`.

Run tests with:

```bash
vendor/bin/phpunit
```

Local environments should have the Composer development extensions installed. If an optional extension or platform feature is unavailable, report which validation could not run and why. Some tests are intentionally skipped when their requirements are missing; assume skipped tests are skipped for a valid reason unless the task is specifically about skipped tests.

When writing tests for APIs with global or static process state, use `@runInSeparateProcess` to avoid leaking side effects between tests.

PHPUnit tests marked with `@todo auto-generated` are controlled by the test generator. If you manually change one of these tests, remove the `@todo auto-generated` marker in the same edit so the generator does not treat it as disposable/regenerable.

Tests may write temporary files through `temp_file`, `temp_dir`, or `Slothsoft\Core\IO\FileInfoFactory::createTempFile`. Manual cleanup is not required for those helpers.

Files in `test-files/` are canonical fixtures. Do not treat them as disposable output.

## Documentation

The PHPDoc config is `phpdoc.xml`.

Generate documentation with:

```bash
vendor/bin/phpdoc
```

## Style

Use `.editorconfig` as the authoritative style configuration. There is no separate formatter, static analyzer, PHPCS, PHPStan, or Psalm setup at this time unless a task adds one explicitly.

## MCP Servers

Use the connected MCP servers when they are relevant to the task.

When editing files in a JetBrains IDE project, use the JetBrains MCP after the edit to retrieve IDE inspections for the touched files. Treat this as part of validation alongside running tests or syntax checks, and report any remaining warnings that are not safe to fix.

## Agent Workflow

- Work from the current package root.
- Read `README.md` for package-specific context before making non-trivial changes.
- Keep public APIs backward-compatible. Public classes, constructors, methods, constants, and public properties may only change when the user specifically requests that API change.
- Semantic versioning is in effect. Signature changes are not allowed without an explicit major-version API-change request.
- Prefer fast local inspection tools before making changes.
- Keep edits scoped to the requested task and relevant package boundary.
- Do not refactor unrelated code while fixing an issue.
- Use normal patch/edit tools for manual edits. Avoid shell write tricks that make changes hard to review.
- Never use destructive cleanup commands or revert user changes unless explicitly asked for that exact operation.
