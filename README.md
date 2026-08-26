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
  <strong>One-command Laravel upgrades</strong> — dependency planning, code transformation, skeleton sync, and PHPStan advisories for Laravel 10 through 13.
</p>

---

## Installation

```bash
composer require --dev muhammadsadeeq/laravel-upgrades-rector
```

Requires PHP 8.2+ and Rector ^2.3.

## One-Command Upgrade

```bash
# Full upgrade flow: preflight → deps → composer update → rector → config sync → advisories → verify
vendor/bin/laravel-upgrade to 11

# Preview without touching anything
vendor/bin/laravel-upgrade plan 11

# Resume an interrupted upgrade
vendor/bin/laravel-upgrade continue

# Generate UPGRADE-REPORT.md from findings
vendor/bin/laravel-upgrade report
```

## Manual Step-by-Step Flow

```bash
# 1. Plan dependencies (writes nothing)
vendor/bin/laravel-upgrade deps 11 --dry-run

# 2. Apply dependency changes, then install
vendor/bin/laravel-upgrade deps 11
composer update --with-all-dependencies

# 3. Transform application code
vendor/bin/rector process --config=vendor/muhammadsadeeq/laravel-upgrades-rector/config/laravel-11.php

# 4. Run PHPStan advisory rules
vendor/bin/phpstan analyse -c vendor/muhammadsadeeq/laravel-upgrades-rector/resources/phpstan/upgrade-11.neon app/

# 5. Verify
composer validate --strict && php artisan test
```


`--dry-run` for `deps` prints the exact `composer require/remove` commands and
the decision table; it never writes. `rector --dry-run` previews code changes;
neither dry-run mode mutates any file.

## The `deps` command

`deps <target-major>` reads your `composer.json`, decides per direct dependency
whether it already admits versions supporting the target major, must be bumped,
or can be removed (`doctrine/dbal`, `spatie/once` on the path to Laravel 11 —
only when no other locked package still requires them). Decisions come from a
vendored compatibility matrix (`resources/compat/packages.json`) evaluated with
composer/semver; unknown packages are flagged for manual review instead of
guessed. After editing, it validates strictly and rehearses `composer update`
as a dry run so solver conflicts surface before you commit.

## Rule Types

| Type | Count | What it does |
|------|-------|--------------|
| Rector transform rules | 20 in sets | Rewrite AST nodes when the upgrade can be applied safely |
| PHPStan advisory rules | 24 in neon configs | Structured findings with file/line/severity for behaviour changes needing human review |

Every generated contract signature was verified against real `laravel/framework`
sources. Every fixture output is re-applied to itself to prove idempotency.
Sets contain only transform rules — all advisory checks live in the PHPStan layer.

## Supported Versions

| Upgrade Path | Rules registered |
|--------------|------------------|
| Laravel 12 &rarr; 13 | 15 + CSRF class renames |
| Laravel 11 &rarr; 12 | 6 |
| Laravel 10 &rarr; 11 | 21 (+ gated Carbon 3 set) |

Carbon 3 rules ship in their own set, included by the Laravel 11/12 presets,
and activate only when `nesbot/carbon` `^3` is actually installed.

## Manual steps per version

The tool cannot do these for you; check them before upgrading:

**To Laravel 11**
- SQLite ≥ 3.26 and curl ≥ 7.34 on the deploy environment.
- Publish framework migrations when installed:
  `php artisan vendor:publish --tag=sanctum-migrations` / `passport-migrations` /
  `telescope-migrations` / `cashier-migrations` / `spark-migrations`.
- Passport 11+: call `Passport::enablePasswordGrant()` if you use password grants;
  `Passport::routes()` is gone.
- Cashier 15: `ignoreMigrations()` removed; `newSubscriptionName()` → `newSubscriptionType()`;
  `$subscription->name` → `$subscription->type`.
- The application structure (slim skeleton) migration is optional and not automated.
- Livewire 3 / Jetstream 5 / Inertia have their own upgrade guides.

**To Laravel 12**
- Bump `php` to `^8.2` if you have not already (the `deps` command does this).
- Carbon: `create*()` now returns `null` instead of `false`; compare accordingly.
- horizon/octane/pest-plugin-laravel majors are computed by `deps`.

**To Laravel 13**
- PHP ≥ 8.3 required.
- `config/session.php`: `'serialization' => 'json'` is the new default — switching
  invalidates every active session; migrate during a maintenance window.
- User-defined global `array_first()`/`array_last()` helpers collide with the new
  framework polyfills — rename yours or remove them.
- Republish vendor views if you customized `resources/views/vendor/**`
  (e.g. pagination `default.blade.php` → `bootstrap-3.blade.php`).
- Update the global Laravel installer (`composer global update laravel/installer`).

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
composer test        # runs all 4 env suites
composer analyse     # PHPStan level max
vendor/bin/pint --test   # code style

# Per-environment testing (requires tests/env/laravel-N composer install)
LARAVEL_ENV=11 php vendor/bin/phpunit --testsuite env-11
```

Current verification: 330+ tests across 4 environment suites, every fixture
re-applied to itself for idempotency, sample-parse gate over rule definitions,
code-style gates over src/, and PHPStan at max level with zero errors.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Credits

- [Muhammad Sadeeq](https://github.com/muhammadsadeeq)
- [Rector Team](https://github.com/rectorphp/rector)
- [Laravel Team](https://laravel.com)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
