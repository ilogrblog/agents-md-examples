# AGENTS.md

Instructions for coding agents working on the Acme Reading Time WordPress
plugin. Codex reads this file directly. Claude Code reads it through
`CLAUDE.md`, which imports it.

## Project

- `acme-reading-time.php` is the plugin entry file. It registers hooks and
  nothing else.
- `includes/` holds classes. Keep calculation logic there, free of WordPress
  function calls, so it can be unit tested without a WordPress install.
- `tests/` holds PHPUnit tests for `includes/`. Hooks are checked by hand in a
  local WordPress site, not here.

## Commands

Run from the repository root. Each command must exit 0.

```bash
composer install
composer lint
composer test
```

- `composer lint` runs PHP_CodeSniffer with the rules in `phpcs.xml.dist`.
- `composer lint:fix` runs `phpcbf` to fix what it can. Read the diff
  afterwards; it rewrites files.
- `composer test` runs PHPUnit with `phpunit.xml.dist`.

## Before you propose a change

1. Run `composer lint` and `composer test`. Both must pass.
2. Add or change a test in `tests/` for any change to behaviour in
   `includes/`.
3. Report the last lines of both commands in your summary. If you could not
   run them, say so plainly and say why. Never describe unrun checks as
   passing.
4. Do not edit `phpcs.xml.dist` or `phpunit.xml.dist` to make a failure go
   away. Fix the code, or stop and explain the conflict.

## Coding standards

- WordPress Coding Standards (WPCS 3), full `WordPress` ruleset, as configured
  in `phpcs.xml.dist`.
- Prefix every global function, hook, option and class with
  `acme_reading_time` or `Acme_Reading_Time`.
- Text domain `acme-reading-time` on every translatable string.
- Escape on output, as late as possible (`esc_html()`, `esc_attr()`,
  `wp_kses_post()`). Sanitise and unslash input. Check a nonce and a
  capability before acting on a request.
- Use `$wpdb->prepare()` for any query with a variable in it.
- Minimum versions are in the plugin header: WordPress 6.5 and PHP 8.1. Do not
  use anything newer without changing the header in the same change.

## No-go zones

- Never open, print, copy, summarise or edit `wp-config.php`, `.env` or
  `.env.*`, or any file holding database credentials, authentication keys,
  salts or API tokens. If a task seems to need a value from one of them, stop
  and ask.
- Never write credentials, tokens or customer data into code, tests, fixtures,
  commit messages or logs.
- Never edit `vendor/`. Change `composer.json` and run Composer instead.
- Never edit WordPress core or another plugin to make this one work.
- Never run commands that write to a database you did not create for the task,
  including `wp db`, `wp search-replace` and `wp option update` against a
  shared or live site.

## Other agent files

This file is the single source of project instructions. `CLAUDE.md` imports it
for Claude Code and adds only Claude-specific notes. Put anything every agent
needs here, not there.

These instructions are guidance. The enforced limits for Claude Code are the
deny rules in `.claude/settings.json`; for Codex, they are its sandbox and
approval settings.
