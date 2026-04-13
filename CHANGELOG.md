# Changelog

All notable changes to `laravel-upgrades-rector` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `UpdateSanctumConfigRector` now migrates the legacy `verify_csrf_token` Sanctum middleware key to `validate_csrf_token` and removes obsolete duplicates when the new key already exists
- `RemoveDoctrineDBALRector` now removes legacy `dbal.types` configuration from database config arrays in addition to cleaning up Doctrine DBAL imports and method usage
- `ReplaceHasVersion7UuidsRector` now handles grouped imports and avoids duplicate `HasUuids` imports when replacing removed Laravel 12 `HasVersion7Uuids` usage
- Laravel 12 database API advisories now detect imported grammar constructors, common untyped `$grammar` / `$connection` / `$blueprint` variables, and `return new Blueprint(...)` constructor usage
- `UpdateResponseFactoryContractRector` now generates and normalizes the Laravel 13 `eventStream(Closure $callback, array $headers = [], StreamedEvent|string|null $endStreamWith = '</stream>'): StreamedResponse` signature
- `UpdateBusDispatcherContractRector` now generates and normalizes the Laravel 13 `dispatchAfterResponse(...): void` signature and adds the new `chain(Collection|array|null $jobs = null): mixed` contract method
- `UpdateHttpClientThrowSignaturesRector` now updates `throw()` / `throwIf()` override signatures and forwards callback arguments to parent calls instead of adding advisory-only comments
- `UpdateSanctumConfigRector` now rewrites old app middleware class constants, not only old middleware strings
- Laravel 11 floating-point and spatial migration rules now fall back to common untyped `$table` / `$blueprint` migration variables when PHPStan cannot resolve `Blueprint`
- `RemoveDoctrineDBALRector` now detects removed Doctrine/native schema operation calls inside assignments and covers `usingNativeSchemaOperations()` / `useNativeSchemaOperationsIfPossible()`
- `UpdatePasswordRehashingRector` now adds `protected $authPasswordName` automatically when a custom `getAuthPassword()` column can be inferred
- Mixed `use` statements in `RemoveDoctrineDBALRector` now remove only Doctrine DBAL imports instead of dropping unrelated imports in the same declaration
- `Carbon3MigrationRector` now preserves explicitly signed `diffIn*()` behavior when the old `absolute: false` / second `false` argument was used
- `UpdateCashierStripeRector` now only adds the `'card'` argument on Billable-backed receivers instead of matching arbitrary methods with the same name
- `UpdateImageValidationSvgRector` now matches actual `image` rule segments and avoids false positives in rule parameters such as `required_without:image`
- `UpdateColumnModificationRector` now recognizes typed `Blueprint` variables instead of relying only on the `$table` variable name
- Shared contract-method detection now respects inherited concrete implementations and avoids redundant stubs in subclasses
- `UpdatePaginationViewNamesRector` now scopes replacements to pagination API calls instead of rewriting matching strings globally

### Added

### Documentation

- README now documents the expected upgrade flow: run Rector, run Composer, then verify the application
- README now explicitly distinguishes `composer.json` rewriting from actual dependency installation and clarifies that advisory comments / TODOs require manual follow-up

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
  - Bus Dispatcher: `dispatchAfterResponse(mixed $command, mixed $handler = null): void`, `chain(Collection|array|null $jobs = null): mixed`
  - ResponseFactory: `eventStream(Closure $callback, array $headers = [], StreamedEvent|string|null $endStreamWith = '</stream>'): StreamedResponse`
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
- HTTP Client `throw()`/`throwIf()` override signature updates with narrower `Illuminate\Http\Client\Response` matching

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
- 293 tests across all 3 upgrade paths and support utilities
- 421 assertions across Rector fixtures and support utility tests
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
