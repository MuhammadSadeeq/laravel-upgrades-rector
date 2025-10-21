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
  <strong>Automate your Laravel upgrades</strong> with confidence using Rector. This package provides comprehensive automation rules for Laravel framework upgrades, handling breaking changes, dependency updates, and configuration migrations.
</p>

---

## Why Use This?

Upgrading Laravel manually is time-consuming and error-prone. This package:

- ✅ **Automates 95% of breaking changes** - Code transformations, dependency updates, config changes
- 🎯 **Based on official guides** - All rules match Laravel's official upgrade documentation
- 📝 **Adds helpful comments** - Flags items that need manual review
- 🧪 **Thoroughly tested** - 162+ tests covering all transformations
- 🔧 **Zero config needed** - Works immediately after installation

## Supported Versions

| From | To | Rules | Status |
|------|-------|-------|--------|
| Laravel 10 | Laravel 11 | 21 rules | ✅ Production Ready |
| Laravel 11 | Laravel 12 | 11 rules | ✅ Production Ready |

## Installation

```bash
composer require --dev muhammadsadeeq/laravel-upgrades-rector
```

## Quick Start

### Upgrade Laravel 10 → 11

```bash
# Preview changes (recommended)
vendor/bin/rector process --dry-run --config=vendor/muhammadsadeeq/laravel-upgrades-rector/config/laravel-11.php

# Apply changes
vendor/bin/rector process --config=vendor/muhammadsadeeq/laravel-upgrades-rector/config/laravel-11.php
```

### Upgrade Laravel 11 → 12

```bash
vendor/bin/rector process --config=vendor/muhammadsadeeq/laravel-upgrades-rector/config/laravel-12.php
```

## What Gets Automated

### Laravel 10 → 11 (21 Rules)
- Dependency updates (composer.json)
- Database schema changes (floating-point, spatial types)
- Authentication updates (password rehashing, contracts)
- Rate limiting (minutes → seconds conversion)
- Doctrine DBAL removal
- Package updates (Cashier, Passport, Sanctum, Telescope)

### Laravel 11 → 12 (11 Rules)
- Composer dependencies (Laravel 12, PHPUnit 11, Pest 3)
- Carbon 3 migration (comprehensive breaking changes)
- UUID v7 migration with backward compatibility
- Image validation (SVG handling)
- Storage configuration updates
- Database schema multi-schema support

## Custom Configuration

Create a `rector.php` in your project root:

```php
<?php

use Rector\Config\RectorConfig;
use MuhammadSadeeq\LaravelUpgradesRector\LaravelUpgradeSetList;

return RectorConfig::configure()
    ->withSets([
        LaravelUpgradeSetList::LARAVEL_11, // or LARAVEL_12
    ])
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
    ])
    ->withSkip([
        __DIR__ . '/app/Legacy', // Skip specific directories
    ]);
```

Then run:
```bash
vendor/bin/rector process
```

## Example Transformations

### Before (Laravel 10)
```php
Schema::create('products', function (Blueprint $table) {
    $table->double('price', 8, 2);
    $table->unsignedDouble('weight');
});

new GlobalLimit($attempts, 2); // minutes
```

### After (Laravel 11)
```php
Schema::create('products', function (Blueprint $table) {
    $table->double('price');
    $table->double('weight')->unsigned();
});

new GlobalLimit($attempts, 2 * 60); // seconds
```

## Documentation

**📖 [Full Documentation](https://www.sadeeq.dev/docs/laravel-upgrades-rector)**

Visit the full documentation for:
- Detailed upgrade guides
- Complete rule reference
- Troubleshooting tips
- Advanced customization
- API documentation

## Testing

```bash
# Run all tests
composer test

# Run static analysis
composer analyse
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Credits

- [Muhammad Sadeeq](https://github.com/muhammadsadeeq)
- [Rector Team](https://github.com/rectorphp/rector)
- [Laravel Team](https://laravel.com)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.

## Support

- 📖 [Full Documentation](https://www.sadeeq.dev/docs/laravel-upgrades-rector)
- 🐛 [Issue Tracker](https://github.com/muhammadsadeeq/laravel-upgrades-rector/issues)
- 💬 [Discussions](https://github.com/muhammadsadeeq/laravel-upgrades-rector/discussions)

---

**Made with ❤️ for the Laravel community**
