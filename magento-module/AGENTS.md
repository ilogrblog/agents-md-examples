# AGENTS.md

Instructions for coding agents working on the `Acme_DispatchEstimate` Magento
2 module. Codex reads this file directly. Claude Code reads it through
`CLAUDE.md`, which imports it.

## Project

- This repository is one module, installed into a Magento project with
  Composer (`acme/module-dispatch-estimate`) or copied to
  `app/code/Acme/DispatchEstimate`.
- `registration.php` and `etc/module.xml` register the module. `Model/` holds
  the logic. Keep calculation classes free of framework dependencies where you
  can, so they run under plain PHPUnit.
- `Test/Unit/` holds unit tests. `Test/Unit/_files/ComponentRegistrarStub.php`
  lets `registration.php` load in a standalone checkout. It is mapped only in
  `autoload-dev`; do not move it into `autoload`.

## Commands

Run from the repository root with PHP 8.3 or later. Each command must exit 0.

```bash
composer install
vendor/bin/phpcs -i | grep Magento2
composer lint
composer test
```

- The second command confirms the `Magento2` standard is registered with
  PHP_CodeSniffer. If it fails, `composer lint` is not checking what it claims
  to.
- `composer lint` runs PHP_CodeSniffer with the `Magento2` standard in
  `phpcs.xml.dist`. The deprecation notices it prints about CSS and GraphQL
  sniffs come from the standard itself and are not failures.
- `composer lint:fix` runs `phpcbf` to fix what it can. Read the diff
  afterwards; it rewrites files.
- `composer test` runs PHPUnit with `phpunit.xml.dist`.

Inside a local development Magento installation only, and only when asked, the
module is also checked with:

```bash
bin/magento module:enable Acme_DispatchEstimate
bin/magento setup:upgrade
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Acme/DispatchEstimate/Test/Unit
```

`setup:upgrade` changes the database. Never run it against a shared, staging
or production database.

## Before you propose a change

1. Run the four commands above. All must pass.
2. Add or change a test in `Test/Unit/` for any change to behaviour in
   `Model/`.
3. Report the last lines of `composer lint` and `composer test` in your
   summary. If you could not run them, say so plainly and say why. Never
   describe unrun checks as passing.
4. Do not edit `phpcs.xml.dist` or `phpunit.xml.dist` to make a failure go
   away. Fix the code, or stop and explain the conflict.

## Coding standards

- Magento coding standard (`magento/magento-coding-standard`, `Magento2`
  ruleset), as configured in `phpcs.xml.dist`.
- `declare(strict_types=1);` in every PHP file. Constructor injection only;
  never call `ObjectManager` directly.
- Change core behaviour with plugins, observers or `di.xml` preferences in
  this module. Never by editing code under `vendor/magento/`.
- Database changes go in `etc/db_schema.xml`. After changing it, regenerate
  `etc/db_schema_whitelist.json` with
  `bin/magento setup:db-declaration:generate-whitelist --module-name=Acme_DispatchEstimate`;
  never edit the whitelist by hand.

## No-go zones

- Never open, print, copy, summarise or edit `app/etc/env.php`. It holds the
  database credentials and the encryption key. The same applies to `auth.json`
  (Composer repository keys), `.env` and `.env.*`. If a task seems to need a
  value from one of them, stop and ask.
- Never edit `app/etc/config.php` by hand. Module state changes go through
  `bin/magento module:enable` or `module:disable`.
- Never write credentials, keys or customer data into code, tests, fixtures,
  commit messages or logs.
- Never edit `vendor/`, `generated/`, `var/` or `pub/static/`. Change
  `composer.json` and run Composer, or regenerate with `bin/magento`.
- Never run `bin/magento` commands that write to a shared, staging or
  production database or cache.

## Other agent files

This file is the single source of project instructions. `CLAUDE.md` imports it
for Claude Code and adds only Claude-specific notes. Put anything every agent
needs here, not there.

These instructions are guidance. The enforced limits for Claude Code are the
deny rules in `.claude/settings.json`; for Codex, they are its sandbox and
approval settings.
