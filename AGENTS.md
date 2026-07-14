# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Overview

ImageField Tokens is a Drupal module that extends the Image field with a widget
and formatter supporting token-based Alt and Title attributes. It allows site
builders to use entity tokens (e.g. `[node:title]`) in image alt/title text that
are replaced at render time.

Optional integrations: Colorbox (formatter), Image Widget Crop (widget), Imce,
File Field Sources.

## Development Commands

**HARD RULE - use the provided command wrappers, never the tool binaries directly.** When `make` or `ahoy` exposes a command for a task, use that command; do not call the underlying binary directly. Each wrapper `chdir`s into `build/` and runs the tool with the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory. If no wrapped command covers what you need, extend the `make` / `ahoy` target rather than making a one-off raw call; if that is not feasible, stop and ask.

Run each tool through its `make` wrapper, never the binary directly:

- **PHPCS / PHPCBF**: `make lint` / `make lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
- **PHPStan**: `make lint` - never `vendor/bin/phpstan`.
- **Rector**: `make lint` (dry-run) / `make lint-fix` - never `vendor/bin/rector`.
- **Twig CS Fixer**: `make lint` / `make lint-fix` - never `vendor/bin/twig-cs-fixer`.
- **CSpell**: `make lint` - never `npx cspell`.
- **PHPUnit**: `make test` / `make test-unit` / `make test-kernel` / `make test-functional` - never `vendor/bin/phpunit`.
- **Drush**: `make drush <command>` - never `build/vendor/bin/drush` directly.

Run each tool through its `ahoy` wrapper, never the binary directly:

- **PHPCS / PHPCBF**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
- **PHPStan**: `ahoy lint` - never `vendor/bin/phpstan`.
- **Rector**: `ahoy lint` (dry-run) / `ahoy lint-fix` - never `vendor/bin/rector`.
- **Twig CS Fixer**: `ahoy lint` / `ahoy lint-fix` - never `vendor/bin/twig-cs-fixer`.
- **CSpell**: `ahoy lint` - never `npx cspell`.
- **PHPUnit**: `ahoy test` / `ahoy test-unit` / `ahoy test-kernel` / `ahoy test-functional` - never `vendor/bin/phpunit`.
- **Drush**: `ahoy drush <command>` - never `build/vendor/bin/drush` directly.

### Build and Environment Management

**Using Make (default):**
- `make build` - Complete build (stop → assemble → start → provision)
- `make assemble` - Assemble codebase with dependencies
- `make start` - Start PHP development server
- `make stop` - Stop development server
- `make provision` - Install/provision Drupal site
- `make reset` - Clean build directory and logs (aliases: `make delete`, `make destroy`)

**Using Ahoy (alternative):**
- `ahoy build` - Complete build process
- `ahoy assemble` - Assemble codebase
- `ahoy start` - Start development server
- `ahoy provision` - Provision Drupal site

### Code Quality

**Linting:**
- `make lint` - Run all linting tools
- `make lint-fix` - Auto-fix coding standards violations
- `ahoy lint` - Run all linting tools
- `ahoy lint-fix` - Auto-fix coding standards violations

**Testing:**
- `make test` - Run all tests
- `make test-unit` - Run unit tests only
- `make test-kernel` - Run kernel tests only
- `make test-functional` - Run functional tests only
- `ahoy test` - Run all tests
- `ahoy test-unit` - Run unit tests only
- `ahoy test-kernel` - Run kernel tests only
- `ahoy test-functional` - Run functional tests only

### Drupal Commands

- `make drush <command>` - Run Drush commands
- `make login` - Get one-time login link
- `ahoy drush <command>` - Run Drush commands
- `ahoy login` - Get one-time login link

### Diagnostics

- `make info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node versions, webserver host/port (with source), XDebug state, build directory, database path, and active profile. (alias: `make describe`)
- `ahoy info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node versions, webserver host/port (with source), XDebug state, build directory, database path, and active profile. (alias: `ahoy describe`)

## Project Structure

**Key Files:**
- `imagefield_tokens.info.yml` - Module info
- `imagefield_tokens.module` - Hook implementations (help, widget/formatter alters)
- `imagefield_tokens.install` - Install/uninstall hooks
- `src/Plugin/Field/FieldFormatter/ImageFieldTokensFormatter.php` - Token-aware image formatter
- `src/Plugin/Field/FieldFormatter/ColorboxFormatter.php` - Colorbox variant (conditional on colorbox module)
- `src/Plugin/Field/FieldWidget/ImageFieldTokensWigdet.php` - Token-aware image widget
- `src/Plugin/Field/FieldWidget/ImageFieldTokensCropWidget.php` - Crop + token widget (conditional on image_widget_crop)
- `tests/src/Functional/` - Functional tests (formatter, widget)
- `build/` - Assembled Drupal codebase (symlinked extension, gitignored)
- `.devtools/` - Build and deployment scripts used by CI
- `scripts/` - Custom post-assemble / post-provision hooks

## Architecture

The module provides field widget and formatter plugins that extend core's Image
plugins:

- **ImageFieldTokensFormatter** extends `ImageFormatter`. At render time it
  replaces tokens in the alt/title field values using the Token module, with
  the host entity as token data. Cache metadata from token replacement is
  merged into the render array.
- **ImageFieldTokensWigdet** extends `ImageWidget`. Provides default alt/title
  values that can contain tokens.
- **ColorboxFormatter** extends the formatter for Colorbox integration
  (conditionally enabled via `hook_field_formatter_info_alter()`).
- **ImageFieldTokensCropWidget** extends the widget for Image Widget Crop
  integration (conditionally enabled via `hook_field_widget_info_alter()`).

The module uses dependency injection for Token, RouteMatch, and other services.

## Environment Variables

- `DRUPAL_VERSION` - Target Drupal version (e.g., `10`, `11`, `11@alpha`)
- `WEBSERVER_HOST` - Development server host (default: localhost)
- `WEBSERVER_PORT` - Development server port. Auto-discovered from range 8000-8099 and written to `.env` if not already set
- `GITHUB_TOKEN` - GitHub API token to avoid rate limits

## Development Workflow

1. Build environment: `DRUPAL_VERSION=11 make build` or `ahoy build`
2. Develop code in `src/`
3. Check standards: `make lint` or `ahoy lint`
4. Run tests: `make test` or `ahoy test`
5. Access site via the Cloudflare tunnel URL printed by `make build`

## Code Quality Tools

- **CSpell**: Spell checking across the codebase (config at `.cspell.json`)
- **PHPCS**: Drupal and DrupalPractice standards
- **PHPStan**: Static analysis with Drupal extensions
- **Rector**: Automated refactoring and deprecation fixes
- **Twig CS Fixer**: Twig template formatting

## CI/CD Support

- **GitHub Actions**: `.github/workflows/test.yml` (lint + matrix test)
- **GitLab CI**: `.gitlab-ci.yml` for Drupal.org CI
- **Matrix testing**: PHP 8.2-8.4, Drupal 10-11
- **Automated deployment**: Mirror to Drupal.org on release

## Important Notes

- The `build/` directory contains the assembled Drupal site
- Extension files are symlinked from root into `build/web/modules/custom/` (module) or `build/web/themes/custom/` (theme)
- SQLite database created in `/tmp/site_imagefield_tokens.sqlite`
- All quality tools run from within `build/` directory

## Updating the scaffold

When the user asks to update this project's scaffold (e.g. "update scaffold"), fetch the update skill from GitHub into the local `.claude/skills/` directory, then invoke it:

1. Create the target directory if it does not exist:

   ```bash
   mkdir -p .claude/skills/update-consumer-drupal-extension-scaffold
   ```

2. Download the skill:

   ```bash
   curl -sSL https://raw.githubusercontent.com/AlexSkrypnyk/drupal_extension_scaffold/1.x/.scaffold/skills/update-consumer-drupal-extension-scaffold/SKILL.md -o .claude/skills/update-consumer-drupal-extension-scaffold/SKILL.md
   ```

3. Invoke the `update-consumer-drupal-extension-scaffold` skill and follow its steps.

The skill directory is git-ignored - it is fetched on demand and not committed to the project.
