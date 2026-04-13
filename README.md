<p align="center">
  <img src="https://www.sadeeq.dev/images/packages/laravel-upgrades-rector/logo/logo.svg" width="150" alt="Laravel Upgrades Rector Logo">
</p>

<h1 align="center">Laravel Upgrades Rector</h1>

<p align="center">
  <a href="https://packagist.org/packages/muhammadsadeeq/laravel-upgrades-rector"><img src="https://img.shields.io/packagist/v/muhammadsadeeq/laravel-upgrades-rector.svg?style=flat-square" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/muhammadsadeeq/laravel-upgrades-rector"><img src="https://img.shields.io/packagist/dt/muhammadsadeeq/laravel-upgrades-rector.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="https://github.com/muhammadsadeeq/laravel-upgrades-rector/actions"><img src="https://img.shields.io/github/actions/workflow/status/muhammadsadeeq/laravel-upgrades-rector/tests.yml?branch=main&label=tests&style=flat-square" alt="Tests"></a>
</p>

<p align="center">
  <strong>Automate your Laravel upgrades</strong> with 64 Rector rules covering Laravel 10 through 13.
</p>

---

## Installation

```bash
composer require --dev muhammadsadeeq/laravel-upgrades-rector
```

## Usage

Preview changes:

```bash
vendor/bin/rector process --dry-run --config=vendor/muhammadsadeeq/laravel-upgrades-rector/config/laravel-13.php
```

Apply changes:

```bash
vendor/bin/rector process --config=vendor/muhammadsadeeq/laravel-upgrades-rector/config/laravel-13.php
```

Replace `laravel-13.php` with `laravel-12.php` or `laravel-11.php` for older upgrades.
These config names and set constants refer to the target upgrade version, so `laravel-13.php` / `LaravelUpgradeSetList::LARAVEL_13` means “upgrade the project to Laravel 13.”
The Laravel 11, Laravel 12, and Laravel 13 sets can also rewrite the nearest project `composer.json` when a supported dependency update applies.
They do not update `composer.lock` or install new packages for you.

## Recommended Upgrade Flow

```bash
# 1. Rewrite application code and supported config patterns
vendor/bin/rector process --config=vendor/muhammadsadeeq/laravel-upgrades-rector/config/laravel-11.php

# 2. Install the upgraded framework and package versions
composer update

# 3. Run your test suite and application checks
php artisan test
```

After Rector and Composer finish, review any generated advisory comments and TODO stubs before considering the upgrade complete.

## Rule Types

- Auto-fix rules rewrite code or configuration when the upgrade can be applied safely
- Advisory rules add comments when the package can identify a Laravel upgrade concern but cannot safely rewrite project-specific behavior
- Contract stub rules add required interface methods, often with TODO comments where implementation is application-specific

Examples of advisory/manual-review areas include `change()` migrations, relationship methods named `casts()`, custom password rehashing behavior, and other behavior-sensitive upgrade-guide items.

## Supported Versions

| Upgrade Path | Rules |
|--------------|-------|
| Laravel 12 &rarr; 13 | 20 rules |
| Laravel 11 &rarr; 12 | 14 rules |
| Laravel 10 &rarr; 11 | 31 rules |

Cumulative sets are available to upgrade across multiple versions at once.

## Custom Configuration

```php
<?php

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\Set\LaravelUpgradeSetList;

return RectorConfig::configure()
    ->withSets([
        LaravelUpgradeSetList::LARAVEL_13,
        // LARAVEL_11, LARAVEL_12, LARAVEL_13 for single-version upgrades
        // UP_TO_LARAVEL_12, UP_TO_LARAVEL_13 for cumulative upgrades
    ])
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/bootstrap',
        __DIR__ . '/config',
        __DIR__ . '/database',
    ]);
```

## Documentation

**[Full Documentation](https://www.sadeeq.dev/docs/laravel-upgrades-rector)** — Detailed rule reference, example transformations, architecture overview, and troubleshooting.

## Testing

```bash
composer test
composer analyse
```

Current verification: 293 tests, 421 assertions, and PHPStan at max level with zero errors.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Credits

- [Muhammad Sadeeq](https://github.com/muhammadsadeeq)
- [Rector Team](https://github.com/rectorphp/rector)
- [Laravel Team](https://laravel.com)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
