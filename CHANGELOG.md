# Changelog

All notable changes to `laravel-upgrades-rector` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

#### Laravel 12 &rarr; 13 Upgrade (20 Rules)
- Composer dependency updates now target the real `composer.json` file (framework ^13.0, boost ^2.0, tinker ^3.0, phpunit ^12.0, pest ^4.0)
- CSRF middleware rename: `VerifyCsrfToken`/`ValidateCsrfToken` &rarr; `PreventRequestForgery`
- Method rename: `validateCsrfTokens()` &rarr; `preventRequestForgery()`
- Cache configuration upgrade coverage:
  - Cache `serializable_classes` config defaults
  - Cache/store prefix and session cookie default advisories
  - Cache Repository contract: `touch($key, $seconds): bool`
- Contract method additions:
  - Cache Store: `touch($key, $seconds): bool`
  - Bus Dispatcher: `dispatchAfterResponse($command, $handler = null): mixed`
  - ResponseFactory: `eventStream(callable $callback, ?string $endStream = null, array $headers = []): mixed`
  - MustVerifyEmail: `markEmailAsUnverified(): bool`
  - Queue: `pendingSize()`, `delayedSize()`, `reservedSize()`, `creationTimeOfOldestPendingJob()`
- Advisory coverage for documented Laravel 13 behavior changes:
  - `Container::call()` nullable class defaults
  - MySQL/MariaDB `upsert` empty `uniqueBy`
  - MySQL `DELETE ... JOIN` with `ORDER BY` / `LIMIT`
  - Model booting nested instantiation
  - Polymorphic pivot table naming
  - Queued notifications with missing models
  - Domain route registration precedence
  - Default password reset subject
  - `withScheduling()` registration timing
  - Manager `extend()` callback binding
  - `Str` factories reset between tests
  - `Js::from()` Unicode escaping behavior
- Event property renames: `JobAttempted::$exceptionOccurred` &rarr; `$exception`, `QueueBusy::$connection` &rarr; `$connectionName`
- Pagination view name updates: `pagination::default` &rarr; `pagination::bootstrap-3`
- HTTP Client `throw()`/`throwIf()` signature change advisory comments with narrower `Illuminate\Http\Client\Response` matching

#### Laravel 11 &rarr; 12 Upgrade (14 Rules)
- Composer dependency updates now target the real `composer.json` file (Laravel 12, PHPUnit 11, Pest 3)
- Carbon 3 migration handling for the Laravel 11 -> 12 upgrade path
- UUID trait migration advisories (UUIDv7 by default, backward compatibility support)
- Image validation SVG advisory comments for string rules and `File::image()`
- Storage configuration advisory comments for implicit `local` disk behavior
- Database schema multi-schema behavior documentation
- Database constructor and grammar API change advisories
- Container dependency resolution behavior changes
- Blueprint and DatabaseTokenRepository constructor updates
- Request merging nested array support documentation
- Concurrency result mapping behavior notes
- Route precedence duplicate-name advisories

#### Laravel 10 &rarr; 11 Upgrade (31 Rules)
- Composer dependency updates now target the real `composer.json` file:
  - Laravel and ecosystem package versions updated in `require` / `require-dev`
  - PHP requirement updated to `^8.2`
  - `doctrine/dbal` and `spatie/once` removed when present
- Carbon 3 migration with comprehensive breaking change handling:
  - Method renames (`formatLocalized` &rarr; `isoFormat` with format string conversion)
  - `diffIn*` methods now return floats/negatives
  - `setUtf8()` removal
  - Named argument updates (`tz` &rarr; `timezone`)
  - `isSame*()` &rarr; `isCurrent*()` conversions
- Database schema changes (floating-point types, spatial types, column modifications)
- Additional upgrade advisories for documented Laravel 11 behavior changes:
  - SQLite minimum version
  - `AuthenticationException::redirectTo($request)`
  - Email verification auto-registration in `EventServiceProvider`
  - Cache prefix suffix behavior
  - `Schema::getColumnType()` behavior
  - MariaDB `uuid()` column behavior
  - `sync` queue `after_commit`
  - Publishing providers to `bootstrap/providers.php`
- Authentication updates (password rehashing, contract methods)
- Rate limiting conversion (minutes to seconds)
- Doctrine DBAL removal automation
- Package-specific updates:
  - Cashier Stripe 15.0 (payment methods, trial behavior, migrations)
  - Passport 12.0 (migration publishing, password grant)
  - Sanctum 4.0 (middleware configuration, migrations)
  - Spark Stripe 5.0 (migration publishing)
  - Telescope 5.0 (migration publishing)
- Spatie Once package removal (Laravel has native `once()` now)
- Contract interface updates (Authenticatable, Enumerable, Mailer, BatchRepository, ConnectionInterface, UserProvider)

#### Testing & Quality
- 271 tests across all 3 upgrade paths and support utilities
- 399 assertions across Rector fixtures and support utility tests
- Full fixture-based testing using Rector's AbstractRectorTestCase
- PHPStan at max level with zero errors

#### Architecture
- Shared utilities in `src/Support/NodeAnalyzer/` (InterfaceImplementationChecker, StaticCallExtractor)
- Set-based rule registration with cumulative upgrade paths
- 60+ type stubs for reliable PHPStan resolution
- Pre-configured convenience configs per version

### Technical Details
- Minimum PHP version: 8.3
- Rector version: ^1.0 || ^2.0
- PSR-4 autoloading
- CI/CD ready with PHPUnit and PHPStan

---

[Unreleased]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/main...HEAD
