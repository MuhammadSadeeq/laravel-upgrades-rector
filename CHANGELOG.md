# Changelog

All notable changes to `laravel-upgrades-rector` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Changes for the next release will be documented here.

## [2.0.0] — 2026-08-30

The complete upgrade platform release: Laravel upgrade work is now a
journaled, reviewable workflow rather than a Rector-only code pass.

### Added

- Real Laravel 11, 12, and 13 vendor environments, generated-code load gates,
  fixture lint and idempotency checks, plus an end-to-end upgrade harness for
  each supported major transition.
- Shared, data-driven contract and AST infrastructure, installed-package-aware
  Carbon 3 handling, and generated per-run Rector configurations.
- PHPStan advisory analysis with structured findings, project-level checks,
  canonical JSON/Markdown upgrade reports, and optional source annotations.
- Journaled `to`, `plan`, `continue`, `report`, `deps`, `skeleton`, `code`,
  `advise`, `post`, and `verify` commands with preflight checks, Composer
  planning, safe git checkpoints, and resumable multi-major upgrades.
- Three-way Laravel skeleton/config synchronization and conservative
  `.env.example` merging, including conflict and preserved-value reporting.
- Optional Laravel 10 → 11 structure modernization and ecosystem package
  compatibility guidance for dependency planning.

### Changed

- Upgrade presets and reports now work from any project working directory and
  keep advisories out of source by default.
- Release metadata, changelog links, and annotated-tag checks are validated
  locally by `composer check-release`; the command never publishes or pushes.

## [1.1.0] — 2026-08-23

Stop-the-bleeding release: nothing the published package did can corrupt a
project any more, and dependency planning moved out of AST visitors into a
proper CLI command.

### Removed

- **composer.json is no longer written by Rector rules.** The three
  `UpdateComposerDependencies*` rules rewrote `composer.json` from inside node
  visitors: they mutated files during `--dry-run`, raced between parallel
  workers on a non-atomic write, produced schema-invalid JSON (`"require": []`
  after removing the last entry), destroyed formatting, and silently did
  nothing when Rector's cache elided the file. Dependency planning now lives in
  the new `deps` command (below).

### Added

- `vendor/bin/laravel-upgrade deps <major> [--dry-run]`: plans per-package
  bumps/removals with composer/semver against a vendored compatibility matrix,
  applies them through the Composer CLI (formatting preserved), validates
  strictly and rehearses `composer update --dry-run -W`, surfacing solver
  failures with `composer why-not` output and exit code 3.
- Carbon 3 rules split into five focused rules in their own set
  (`carbon-3.php`) that activates only when installed `nesbot/carbon` is `^3`
  — Laravel 11 accepts both Carbon 2 and 3, so only the installed version is a
  valid trigger.
- Idempotency gate: every rule fixture is re-applied to its own expected output
  in CI; a second run must change nothing.
- Compatibility data files under `resources/compat/` (packages, removals).

### Fixed

- **Contract signatures that load.** Every generated signature was verified
  against real `laravel/framework` sources. The stubs had made typed parameters
  pass tests while real apps fataled: `ConnectionInterface::scalar()`,
  `Cache\Contracts touch()`, queue sizing methods and
  `dispatchAfterResponse()` are declared with untyped parameters — generated
  implementations now match exactly.
- `UpdateMailerContractRector` return type corrected to the real
  `\Illuminate\Mail\SentMessage` (the contract class never existed).
- Contract rules append missing methods and never rewrite existing ones;
  the bus dispatcher no longer invents a `chain()` addition (not a Laravel 13
  change) nor strips `return` statements from user methods.
- `MustVerifyEmail::markEmailAsUnverified()` implementations use `forceFill(...)`
  on Eloquent models instead of a silent `return false`.
- HTTP Client `throw()/throwIf()` overrides get missing callback parameters
  appended by name — never renamed — with parent calls forwarding positionally.
- Carbon: signed-diff wrapping detects existing `(int)`/`abs()` wrappers through
  node structure instead of sniffing source text (`fabs(` defeated it);
  `formatLocalized()` conversion escapes literal text into `[...]` and refuses
  unmapped strftime tokens instead of emitting garbage;
  `createFromTimestamp($ts)` is left alone (the old UTC rewrite silently changed
  behaviour); `minValue()/maxValue()` map to `startOfTime()/endOfTime()` on the
  same receiver class instead of switching mutability.
- Spatial columns: `point('geo', 4326)` becomes `geometry('geo', 'point', 4326)`
  in the verified positional order — the old named-argument append produced a
  fatal "named parameter overwrites previous argument" migration. Subtypes are
  lowercased; `*Z` variants are left untouched.
- Rate limiting: exact-FQCN matching only (a userland `Limit` class no longer
  receives `5 * 60`), and `$decayMinutes` renames require the enclosing class to
  extend `ThrottlesExceptions(+Redis)` and rename the declaration too.
- Floating point: named/unpack arguments skipped instead of deleted; receivers
  must be confirmed `Blueprint` instances (the `$table` name-guess corrupted
  unrelated classes such as `PdfTable`).
- Import removal (`doctrine/dbal`, `spatie/once`) keeps imports that are still
  referenced anywhere in the file, docblocks included.
- Doctrine method renames apply only to the `Schema` facade — never `DB`, whose
  methods were never renamed; removed-method advisories distinguish confirmed
  vs low-confidence receivers.
- Eighteen dead or harmful advisory rules are no longer registered (shapes that
  never occur in real configs, per-call comment spam, contradictory sibling
  rules, seconds multiplied twice). Their checks move to future advisory/
  preflight/config engines; each set file documents where.
- Advisory comments carry unique per-rule markers and dedupe correctly (five
  rules previously shared the generic `'Laravel 12:'` marker and silenced each
  other); `ORIGINAL_NODE = null` is gone everywhere, ending full-class reprint
  churn that deleted blank lines and collapsed promoted constructors.
- Presets tolerate any working directory, include `tests/`, skip Blade files
  (previously parsed as inline HTML for zero effect), set the target PHP
  version and load Larastan when installed.

### Changed

- PHP floor lowered back to `^8.2` — the 10→11 audience was locked out.
- `rector/rector` constraint narrowed to `^2.3` (Rector 1 is untested here).
- CI matrix: PHP 8.2/8.3/8.4 × prefer-lowest/prefer-stable + strict composer
  validation.

## [1.0.5] — 2026-03-28

### Fixed

- Avoid reprinting `redirectTo` wrapper functions in the authentication
  exception rule.
- Improve Laravel 11 fallback matching for aliased imports and untyped
  variables across contract, casts, password rehashing, rate limiting and
  authentication exception rules.

## [1.0.4] — 2026-03-27

### Fixed

- Limit ready-made configs to this package's upgrade rules (no generic Rector
  modernization sets) and skip `bootstrap/cache`.

## [1.0.3] — 2026-03-26

### Fixed

- Cross-version compatibility fixes for Rector and PHPStan ranges.
- Mixed `use` statements: only Doctrine DBAL imports are dropped, not unrelated
  imports in the same declaration.
- `Carbon3MigrationRector` preserves explicitly signed `diffIn*()` behaviour
  when `absolute: false` was passed.
- `UpdateCashierStripeRector` narrows the `'card'` argument match to Billable
  receivers.
- `UpdateImageValidationSvgRector` matches real `image` rule segments and stops
  false positives like `required_without:image`.
- `UpdatePaginationViewNamesRector` scopes replacements to pagination API calls.

## [1.0.2] — 2026-03-25

### Fixed

- `RemoveDoctrineDBALRector` removes legacy `dbal.types` config arrays and
  detects removed Doctrine calls inside assignments.
- `UpdatePasswordRehashingRector` adds `protected $authPasswordName` when a
  custom password column can be inferred.
- Shared contract-method detection respects inherited concrete implementations
  (no redundant stubs in subclasses).
- `UpdateSanctumConfigRector` rewrites middleware class constants, not only
  strings.

## [1.0.1] — 2026-03-24

### Fixed

- `UpdateResponseFactoryContractRector` generates the full Laravel 13
  `eventStream(...)` signature.
- `UpdateHttpClientThrowSignaturesRector` updates override signatures and
  forwards callbacks to parent calls instead of advisory-only comments.
- Laravel 12 database API advisories detect imported grammar constructors and
  common untyped `$grammar` / `$connection` / `$blueprint` variables.
- `ReplaceHasVersion7UuidsRector` handles grouped imports without duplicating
  `HasUuids`.

## [1.0.0] — 2026-03-23

### Added

- Initial release: 64 rules across three upgrade paths (Laravel 10→11,
  11→12, 12→13) plus cumulative `UP_TO_*` sets:
  - Contract interface updates (Authenticatable, Enumerable, Mailer,
    BatchRepository, ConnectionInterface, UserProvider, Cache, Queue, Bus,
    ResponseFactory, MustVerifyEmail)
  - Carbon 3 migration handling (diffs, formatLocalized, removed methods,
    named arguments, isSame→isCurrent)
  - Schema changes: floating-point types, spatial types, column modification
    advisories, Doctrine DBAL removal, spatie/once removal
  - Rate limiting minutes → seconds, password rehashing, Sanctum/Cashier/
    Passport/Spark/Telescope package advisories
  - Laravel 12: UUID trait migration, concurrency result mapping, image SVG
    validation, storage/schema/token-repository advisories
  - Laravel 13: CSRF middleware rename, pagination view names, event property
    renames, container/query/eloquent behavior advisories

[Unreleased]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.1.0...v2.0.0
[1.1.0]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.0.5...v1.1.0
[1.0.5]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.0.4...v1.0.5
[1.0.4]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/releases/tag/v1.0.0
