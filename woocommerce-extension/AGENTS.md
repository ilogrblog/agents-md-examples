# AGENTS.md

Instructions for coding agents working on the Acme Free Shipping Nudge
WooCommerce extension. Codex reads this file directly. Claude Code reads it
through `CLAUDE.md`, which imports it.

## Project

- `acme-free-shipping-nudge.php` is the plugin entry file. It declares
  WooCommerce feature compatibility and registers hooks.
- `includes/` holds classes. Keep money arithmetic there, free of WordPress
  and WooCommerce calls, so it can be unit tested without a store.
- `tests/` holds PHPUnit tests for `includes/`. Cart behaviour is checked by
  hand in a local store, not here.

## Commands

Run from the repository root. Each command must exit 0.

```bash
composer install
composer lint
composer test
```

- `composer lint` runs PHP_CodeSniffer with the `WooCommerce-Core` and
  `PHPCompatibilityWP` rules in `phpcs.xml.dist`.
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

- WooCommerce sniffs (`WooCommerce-Core`, which builds on WordPress Coding
  Standards 3), as configured in `phpcs.xml.dist`.
- Prefix every global function, hook, option and class with
  `acme_free_shipping_nudge` or `Acme_Free_Shipping_Nudge`. Hooks named
  `woocommerce_*` belong to WooCommerce; add callbacks to them, never new
  hooks with that prefix.
- Text domain `acme-free-shipping-nudge` on every translatable string.
- Money is handled in integer minor units inside `includes/`. Convert at the
  edge with `Acme_Free_Shipping_Nudge::to_minor_units()` and
  `wc_get_price_decimals()`, never with float multiplication.
- Orders go through WooCommerce CRUD objects: `wc_get_order()`,
  `$order->get_meta()`, `$order->update_meta_data()` then `$order->save()`.
  Never read or write order data with `get_post_meta()`, `update_post_meta()`,
  `WP_Query` or SQL against the posts tables. The extension declares
  compatibility with High-Performance Order Storage, and those calls can read
  stale or missing data when it is on.
- The plugin header's `Requires Plugins`, `WC requires at least` and
  `WC tested up to` lines are part of the contract. Update `WC tested up to`
  only after testing against that WooCommerce version.

## No-go zones

- Never open, print, copy, summarise or edit `wp-config.php`, `.env` or
  `.env.*`, or any file holding database credentials, authentication keys,
  salts, payment gateway keys or API tokens. If a task seems to need a value
  from one of them, stop and ask.
- Never write credentials, tokens or customer data (names, addresses, emails,
  order contents) into code, tests, fixtures, commit messages or logs.
- Never edit `vendor/`. Change `composer.json` and run Composer instead.
- Never edit WooCommerce, WordPress core or another plugin to make this one
  work.
- Never create, change or refund orders, or change store settings, on a shared
  or live store.

## Other agent files

This file is the single source of project instructions. `CLAUDE.md` imports it
for Claude Code and adds only Claude-specific notes. Put anything every agent
needs here, not there.

These instructions are guidance. The enforced limits for Claude Code are the
deny rules in `.claude/settings.json`; for Codex, they are its sandbox and
approval settings.
