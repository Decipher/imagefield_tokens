# ImageField Tokens

[![Pipeline](https://git.drupalcode.org/project/imagefield_tokens/badges/3.0.x/pipeline.svg)](https://git.drupalcode.org/project/imagefield_tokens/-/pipelines)
[![Test](https://github.com/Decipher/imagefield_tokens/actions/workflows/test.yml/badge.svg?branch=3.0.x)](https://github.com/Decipher/imagefield_tokens/actions/workflows/test.yml?query=branch%3A3.0.x)
[![Coverage](https://codecov.io/gh/Decipher/imagefield_tokens/branch/3.0.x/graph/badge.svg)](https://codecov.io/gh/Decipher/imagefield_tokens/branch/3.0.x)

The ImageField Tokens module extends Drupal's core Image field with a widget
and formatter that support token-based Alt and Title text. Site builders can
enter entity tokens (e.g. `[node:title]`) as default values for image alt and
title fields, and they will be automatically replaced at render time using the
[Token](https://www.drupal.org/project/token) module.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/imagefield_tokens).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/imagefield_tokens).

## Table of contents

- Features
- Requirements
- Installation
- Configuration
- Maintainers

## Features

- **Token-aware Image widget** - Set default Alt and Title text using entity
  tokens (e.g. `[node:title]`). Tokens are resolved in the edit form so editors
  see the final values before saving.
- **Token-aware Image formatter** - Replaces tokens at render time using the
  host entity as data context. Cache metadata from token replacement is
  correctly propagated.
- **Token tree picker** - Authenticated users see a browseable token tree when
  editing alt/title fields, scoped to the host entity type.
- **Media Library support** - Widgets work within the Media Library add form,
  resolving tokens against the in-progress media entity.
- **Optional Colorbox formatter** - Token-aware variant of the Colorbox image
  formatter (requires [Colorbox](https://www.drupal.org/project/colorbox)).
- **Optional Image Widget Crop widget** - Token-aware variant of the crop
  widget (requires
  [Image Widget Crop](https://www.drupal.org/project/image_widget_crop)).
- **Imce and File Field Sources** - Widgets are registered as supported by both
  modules when installed.

## Requirements

- Drupal 10 or 11
- [Token](https://www.drupal.org/project/token)

### Optional integrations

- [Colorbox](https://www.drupal.org/project/colorbox) - Token-aware Colorbox
  formatter.
- [Image Widget Crop](https://www.drupal.org/project/image_widget_crop) -
  Token-aware crop widget.
- [Imce](https://www.drupal.org/project/imce) - File browser integration.
- [File Field Sources](https://www.drupal.org/project/filefield_sources) -
  Alternative file upload sources.

## Installation

1. Install via Composer:

   ```
   composer require drupal/imagefield_tokens
   ```

1. Enable the module:

   ```
   drush en imagefield_tokens
   ```

## Configuration

The module has no global configuration page. Each image field can be configured
individually:

1. Go to **Administration > Structure > Content types > [type] > Manage form
   display** and select the **ImageField Tokens** widget for your image field.
1. Enter token-based default values (e.g. `[node:title]`) in the Alt and Title
   fields.
1. Go to **Administration > Structure > Content types > [type] > Manage display**
   and select the **ImageField Tokens** formatter to render tokens at view time.

Tokens are replaced using the host entity as data context, so any entity token
(e.g. `[node:title]`, `[node:author:name]`) is available.

## Maintainers

- Stuart Clark - [Deciphered](https://www.drupal.org/u/deciphered)
- Bradley Erickson - [eosrei](https://www.drupal.org/u/eosrei)
- Yaroslav Samoilenko - [ysamoylenko](https://www.drupal.org/u/ysamoylenko)
