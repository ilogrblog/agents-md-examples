# AGENTS.md examples for WordPress, WooCommerce and Magento

Three small example repositories, each with an `AGENTS.md`, a `CLAUDE.md` that imports it and a `.claude/settings.json` that enforces part of it, plus `check.php`, a checker that runs their commands and proves its own checks can fail.

Companion to the [iLogr](https://ilogr.com/) article **[AGENTS.md for WordPress, WooCommerce and Magento Projects: Examples That Work with Claude Code and Codex](https://ilogr.com/agents-md-wordpress-woocommerce-magento/)**. More code that accompanies iLogr guides: [github.com/ilogrblog](https://github.com/ilogrblog).

> **Before you use it:** this is example code for learning, provided as is, without warranty of any kind. You use it entirely at your own risk, and nobody who wrote or published it accepts any responsibility for loss, damage or other problems. Try it on a development machine or a staging copy first, never on a live site, store or database, and take a backup before you adapt any of it to a real project. Read every file before you run it. `check.php` downloads packages from Packagist and runs the commands written in each `AGENTS.md`, so it refuses to run until you confirm with a flag. Agent instruction files and deny rules reduce mistakes; they do not make an agent safe, so keep an agent's access as narrow as you would for an untrusted contractor.

## What is in it

| Folder | What it is | Coding standard |
| --- | --- | --- |
| `wordpress-plugin/` | Acme Reading Time, a plugin that shows a reading time above posts | WordPress Coding Standards 3 (`WordPress`) |
| `woocommerce-extension/` | Acme Free Shipping Nudge, a cart notice with HPOS compatibility declared | WooCommerce sniffs (`WooCommerce-Core`) and `PHPCompatibilityWP` |
| `magento-module/` | `Acme_DispatchEstimate`, a dispatch date calculator | Magento coding standard (`Magento2`) |
| `check.php` | The checker for the three examples | |

The plugin and module code is deliberately small. It exists so the commands in each `AGENTS.md` have something real to lint and test. `Acme` is a placeholder vendor name.

Files that matter in each example:

- `AGENTS.md`: the instructions every agent shares. Codex reads it directly.
- `CLAUDE.md`: `@AGENTS.md` on its first line, then Claude-specific notes only. Claude Code reads `CLAUDE.md`, and the import brings in `AGENTS.md`.
- `.claude/settings.json`: Claude Code deny rules for the secret files and for edits under `vendor/`. The client enforces them, not the model.
- `composer.json`: the `lint`, `lint:fix` and `test` scripts the instruction files refer to.
- `phpcs.xml.dist` and `phpunit.xml.dist`: the coding standard and test configuration.
- In the Magento example, `Test/Unit/_files/ComponentRegistrarStub.php` lets Composer's autoloader load `registration.php` in a checkout that has no Magento framework. It is mapped only in `autoload-dev`, which Composer ignores when the module is installed into a Magento project.

## Requirements

- PHP 8.3 or later to run `check.php`. The floor comes from the Magento example, whose `composer.json` allows PHP 8.3, 8.4 and 8.5: Adobe lists 8.3 and 8.4 for Magento 2.4.8 and 8.5 for 2.4.9. The WordPress and WooCommerce examples themselves declare PHP 8.1, WordPress 6.5 and WooCommerce 9.0 as their minimums.
- Composer 2 on your `PATH` as `composer`, with `git` and `unzip` (or PHP's zip extension) available to it.
- Network access to Packagist.

## Run it

```bash
php check.php --allow-composer-install
```

The flag confirms that you accept what the full run does: for each example it copies the folder to a temporary directory, runs `composer install` there (which downloads packages and runs their Composer plugins) and runs each command from the first Bash block of the `AGENTS.md` "Commands" section. The example folders themselves are never changed. Without a flag, the script prints a one-line warning and stops.

One example at a time:

```bash
php check.php --allow-composer-install magento-module
```

A read-only run that needs no Composer, no network and no temporary files, and checks only the instruction files and `composer.json` autoload rules:

```bash
php check.php --static-only
```

The full run exits 0 when every check passes, 1 when any check fails and 2 when it cannot run or you have not confirmed. It checks, for each example:

1. **Instruction files.** `AGENTS.md` has the expected sections and stays under Codex's default 32 KiB limit. `CLAUDE.md` imports `@AGENTS.md` first, outside any code span or fenced block. No prose line is copied word for word between the two files (a paraphrase is not detected). Each secret path the checker knows about (`wp-config.php`, `app/etc/env.php`, `auth.json`, `.env`, `.env.*`) that is named under "No-go zones" has a matching `Read` deny rule, anchored with `//**/` where the file normally sits above the repository. The production `autoload` in `composer.json` names no test directory and uses no wildcard or root classmap. These are restricted guards for these examples, not general proofs.
2. **Commands.** Each command in the first Bash block runs as a separate shell command and must exit 0. Each command must occupy one line; a line ending in a backslash fails the static checks. The Magento commands for a full installation are in a second block and are not run.
3. **Sabotage.** The code is broken on purpose (unescaped output, or a changed calculation) and the lint and test commands must then fail, naming the sniff or assertion that caught it.
4. **Broken instruction files.** The static checks are fed deliberately broken files (a `CLAUDE.md` with no import, an import inside backticks or after an unclosed fence, a duplicated rule, an oversized `AGENTS.md`, an `AGENTS.md` wrapped in a code fence, a Commands line ending in a backslash, each deny rule removed in turn, a relative `wp-config.php` rule) and each must be caught.

Expected result on the tested versions:

| Example | Checks passed | Checks failed |
| --- | --- | --- |
| `wordpress-plugin` | 26 | 0 |
| `woocommerce-extension` | 26 | 0 |
| `magento-module` | 29 | 0 |
| **Total** | **81** | **0** |

Each example also works on its own. From inside a folder, `composer install`, `composer lint` and `composer test` run its checks.

## What the checks do not prove

- They do not start Claude Code or Codex. The instruction-file checks implement the loading rules as documented by Anthropic and OpenAI; they are not the tools themselves. To see what each tool actually loaded, use the steps below.
- They do not load Magento. The commands under "Inside a local development Magento installation" in the Magento `AGENTS.md` are not run by `check.php`.
- They do not test the deny rules in `.claude/settings.json`. Claude Code enforces those, and only for the paths and commands its documentation describes.
- The WooCommerce notice is added to the classic cart (the `[woocommerce_cart]` shortcode). The Cart block, which new stores use by default, does not run that hook, so no notice shows there.

## See what each tool loaded

From inside one of the example folders:

- **Codex:** run `codex --ask-for-approval never "Summarize the current instructions."`. OpenAI documents this as the way to confirm which guidance Codex loaded. Expect the rules from `AGENTS.md`, and nothing from `CLAUDE.md`.
- **Claude Code:** start a session and run `/context`. `CLAUDE.md` should be listed under Memory files, and asking Claude to list the no-go zones should return the ones from `AGENTS.md`, reached through the import.

## Tested versions

- PHP 8.3, 8.4 and 8.5 with Composer 2.10: the full `check.php` run, 81 of 81 passing on each.
- PHP 8.1: `composer lint` and `composer test` in the WordPress and WooCommerce examples (PHPUnit 10.5), the minimum their plugin headers declare.
- Packages resolved: PHP_CodeSniffer 3.13, WordPress Coding Standards 3.4.1, WooCommerce sniffs 2.0.0, Magento coding standard 41, PHPUnit 12.5 (13.4 on PHP 8.5 for the WordPress and WooCommerce examples).
- WordPress 7.1.3 on PHP 8.3 with WooCommerce 11.2.1 in a throwaway Docker set-up: both plugins activate, the WooCommerce extension shows as compatible with High-Performance Order Storage, the reading time appears above a post, and the free-shipping notice appears on the classic cart.
- Mage-OS 3.6.0 (based on Magento 2.4.9) on PHP 8.4 in a throwaway Docker container, without a database: `bin/magento module:enable Acme_DispatchEstimate` enables the module, its five unit tests pass with the unit test command in the Magento `AGENTS.md`, and installed with Composer from a path repository it uses Magento's own `ComponentRegistrar` rather than the stand-in class. `bin/magento setup:upgrade` was not run; the module has no database schema.

Later versions may behave differently. The composer constraints and the version numbers above are the starting point if something has moved.

## Licence

The repository is MIT licensed (`LICENSE`), because its main purpose is the instruction files and the checker. The two WordPress examples, `wordpress-plugin/` and `woocommerce-extension/`, are GPL-2.0-or-later, the licence of the WordPress code they build on, and carry their own `LICENSE` file. Both licences are provided without warranty and without liability. See the files for the full text.
