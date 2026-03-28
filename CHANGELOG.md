# Changelog

All notable changes to `laravel-upgrades-rector` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-10-05

### Added

#### Laravel 10 → 11 Upgrade (21 Rules)
- ✅ Complete automation for Laravel 10 to 11 upgrades
- ✅ Composer dependency updates (framework, packages, tools)
- ✅ Database schema changes (floating-point types, spatial types, column modifications)
- ✅ Authentication updates (password rehashing, contract methods)
- ✅ Rate limiting conversion (minutes to seconds)
- ✅ Doctrine DBAL removal automation
- ✅ Package-specific updates:
  - Cashier Stripe 15.0 (payment methods, trial behavior, migrations)
  - Passport 12.0 (migration publishing, password grant)
  - Sanctum 4.0 (middleware configuration, migrations)
  - Telescope 5.0 (migration publishing)
- ✅ Spatie Once package removal (Laravel has native `once()` now)
- ✅ Contract interface updates (Enumerable, Mailer, BatchRepository, etc.)

#### Laravel 11 → 12 Upgrade (11 Rules)
- ✅ Complete automation for Laravel 11 to 12 upgrades
- ✅ Composer dependency updates (Laravel 12, PHPUnit 11, Pest 3)
- ✅ Carbon 3 migration with comprehensive breaking change handling:
  - Method renames (`minValue` → `startOfTime`, `maxValue` → `endOfTime`)
  - `diffIn*` methods now return floats/negatives
  - `formatLocalized()` → `isoFormat()` with format string conversion
  - Named argument updates (`tz` → `timezone`)
  - `isSame*()` → `isCurrent*()` conversions
- ✅ UUID trait migration (UUIDv7 by default, backward compatibility support)
- ✅ Image validation SVG handling
- ✅ Storage configuration updates (local disk path changes)
- ✅ Database schema multi-schema behavior documentation
- ✅ Container dependency resolution behavior changes
- ✅ Blueprint and DatabaseTokenRepository constructor updates
- ✅ Request merging nested array support documentation
- ✅ Concurrency result mapping behavior notes

#### Testing & Quality
- ✅ Comprehensive test suite with 162+ tests
- ✅ 103 tests for Laravel 11 rules (100% passing)
- ✅ 59 tests for Laravel 12 rules (100% passing)
- ✅ Full fixture-based testing using Rector's AbstractRectorTestCase
- ✅ PHPUnit 11 with modern attributes

#### Documentation
- ✅ Comprehensive README with examples and workflow
- ✅ Before/after code transformation examples
- ✅ Clear installation and usage instructions
- ✅ Troubleshooting guide
- ✅ Contributing guidelines
- ✅ Testing documentation

### Technical Details
- Minimum PHP version: 8.3
- Rector version: ^1.0
- PSR-4 autoloading
- Full test coverage for all rules
- CI/CD ready with PHPUnit and PHPStan

---

[1.0.0]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/releases/tag/v1.0.0
