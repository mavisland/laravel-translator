# Laravel Translator

Synchronize missing Laravel translation keys and report possibly unused keys in PHP and JSON language files.

Supports PHP 8.2+ and Laravel 11, 12, and 13.

## Installation

```bash
composer require mavisland/laravel-translator
php artisan vendor:publish --tag=translator-config
```

The publish command creates `config/translator.php`. Edit this file to configure the languages and directories to scan.

## Commands

```bash
php artisan translator:update
php artisan translator:unused
```

- `translator:update`: scans configured source directories for literal translation keys and adds missing keys to each configured language. Existing translation values are preserved. New values are empty by default; JSON keys are sorted alphabetically. Dotted group keys such as `__('pagination.next')` are stored in `lang/<locale>/pagination.php`, unless that exact key already exists in the locale's JSON file.
- `translator:unused`: reads existing `<locale>.json` and `<locale>/**/*.php` files and lists keys not found by the source scanner, sorted by locale and key. **Read-only:** no keys are deleted.

**Caution:** The unused report is a static-analysis aid, not proof a key can be deleted. Dynamic calls (for example, `__('messages.' . $type)`), keys used outside the configured scan directories, and translations referenced by other applications may be reported as unused. Review results manually.

## Configuration

Generate `config/translator.php` with `php artisan vendor:publish --tag=translator-config`, then edit the generated file:

```php
return [
    'languages' => ['tr', 'en'],
    'default_language' => 'tr',
    'use_keys_as_default_value' => false,
    'directories' => [app_path(), resource_path('views')],
    'output' => lang_path(),
    'extensions' => ['php'],
    'functions' => ['lang', '__'],
    'container' => [
        'config_loader' => \\Translator\\Framework\\LaravelConfigLoader::class,
        'translation_repository' => \\Translator\\Infra\\LaravelTranslationRepository::class,
    ],
];
```

The scanner recognizes literal `__('Hello')`, `lang('Hello')`, and equivalent configured function calls. Blade `@lang('Hello')` is also supported by the template scanner. Dynamic expressions cannot be fully resolved by static scanning.

The output location is configured through `output`; use `lang_path()` for modern Laravel installations. The default configuration uses Turkish and English; change `languages` and `default_language` as needed.

## Example

Source:

```php
__('Hello World');
__('pagination.next');
```

After `php artisan translator:update`, new keys have empty values:

```json
{
    "Hello World": ""
}
```

`lang/tr/pagination.php`:

```php
<?php

return [
    'next' => '',
];
```

Translations already present in the files are not overwritten. Running the command again should not change unchanged JSON content.

## Development checks

```bash
composer install
composer run tests
```

This runs PHP_CodeSniffer, PHPStan, and PHPUnit. Run the checks before publishing a release.

## Release

After the tests pass, create and push a version tag (for example `v1.0.0`) and submit the public GitHub repository URL to [Packagist](https://packagist.org/packages/submit). Packagist publication is a separate step from pushing commits to GitHub.

## Credits

Originally created by **Thiago Cordeiro dos Santos**. This maintained fork is developed by **Tanju Yildiz**. The original MIT copyright notice is retained in `LICENSE` alongside the current maintainer’s copyright notice, as required by its terms.
