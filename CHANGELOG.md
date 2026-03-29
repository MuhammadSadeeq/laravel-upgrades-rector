# Changelog

All notable changes to `laravel-upgrades-rector` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

#### Laravel 12 &rarr; 13 Upgrade (11 Rules)
- Composer dependency updates (framework ^13.0, boost ^2.0, tinker ^3.0, phpunit ^12.0, pest ^4.0)
- CSRF middleware rename: `VerifyCsrfToken`/`ValidateCsrfToken` &rarr; `PreventRequestForgery`
- Method rename: `validateCsrfTokens()` &rarr; `preventRequestForgery()`
- Contract method additions:
  - Cache Store: `touch($key, $seconds): bool`
  - Bus Dispatcher: `dispatchAfterResponse($command, $handler = null): mixed`
  - ResponseFactory: `eventStream(callable $callback, ?string $endStream = null, array $headers = [])`
  - MustVerifyEmail: `markEmailAsUnverified(): bool`
  - Queue: `pendingSize()`, `delayedSize()`, `reservedSize()`, `creationTimeOfOldestPendingJob()`
- Event property renames: `JobAttempted::$exceptionOccurred` &rarr; `$exception`, `QueueBusy::$connection` &rarr; `$connectionName`
- Pagination view name updates: `pagination::default` &rarr; `pagination::bootstrap-3`
- HTTP Client `throw()`/`throwIf()` signature change advisory comments

#### Laravel 11 &rarr; 12 Upgrade (12 Rules)
- Composer dependency updates (Laravel 12, PHPUnit 11, Pest 3)
- Carbon 3 migration with comprehensive breaking change handling:
  - Method renames (`formatLocalized` &rarr; `isoFormat` with format string conversion)
  - `diffIn*` methods now return floats/negatives
  - `setUtf8()` removal
  - Named argument updates (`tz` &rarr; `timezone`)
  - `isSame*()` &rarr; `isCurrent*()` conversions
- UUID trait migration (UUIDv7 by default, backward compatibility support)
- Image validation SVG handling
- Storage configuration updates (local disk path changes)
- Database schema multi-schema behavior documentation
- Container dependency resolution behavior changes
- Blueprint and DatabaseTokenRepository constructor updates
- Request merging nested array support documentation
- Concurrency result mapping behavior notes

#### Laravel 10 &rarr; 11 Upgrade (21 Rules)
- Composer dependency updates (framework, packages, tools)
- Database schema changes (floating-point types, spatial types, column modifications)
- Authentication updates (password rehashing, contract methods)
- Rate limiting conversion (minutes to seconds)
- Doctrine DBAL removal automation
- Package-specific updates:
  - Cashier Stripe 15.0 (payment methods, trial behavior, migrations)
  - Passport 12.0 (migration publishing, password grant)
  - Sanctum 4.0 (middleware configuration, migrations)
  - Telescope 5.0 (migration publishing)
- Spatie Once package removal (Laravel has native `once()` now)
- Contract interface updates (Authenticatable, Enumerable, Mailer, BatchRepository, ConnectionInterface, UserProvider)

#### Testing & Quality
- 225+ tests across all 3 upgrade paths (111 L11, 75 L12, 39 L13)
- Full fixture-based testing using Rector's AbstractRectorTestCase
- PHPStan at max level with zero errors

#### Architecture
- Shared utilities in `src/Support/NodeAnalyzer/` (InterfaceImplementationChecker, StaticCallExtractor)
- Set-based rule registration with cumulative upgrade paths
- 50+ type stubs for reliable PHPStan resolution
- Pre-configured convenience configs per version

### Technical Details
- Minimum PHP version: 8.3
- Rector version: ^1.0 || ^2.0
- PSR-4 autoloading
- CI/CD ready with PHPUnit and PHPStan

---

[Unreleased]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/main...HEAD
