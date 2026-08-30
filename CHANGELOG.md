# Changelog

All notable changes to `laravel-upgrades-rector` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Changes for the next release will be documented here.

## [1.0.0] — 2026-08-30

Initial public release of a reviewable Laravel 10–13 upgrade assistant. The
package combines journaled orchestration, safe Rector transformations,
dependency planning, skeleton synchronization, and advisory analysis.

### Added

- Journaled `to`, `plan`, `continue`, `report`, `deps`, `skeleton`, `code`,
  `advise`, `post`, and `verify` commands for adjacent Laravel major upgrades.
- Plan/apply workflows with resumable state, canonical JSON and Markdown
  reports, per-step findings, and project-working-directory support.
- Composer dependency planning backed by `composer/semver`, compatibility
  matrices, package removals, solver rehearsal, and `why-not` diagnostics.
- Conservative three-way Laravel skeleton and configuration synchronization,
  `.env.example` merging, conflict reporting, and optional Laravel 10→11
  structure modernization.
- Rector sets for Laravel 10→11, 11→12, and 12→13, cumulative `UP_TO_*` sets,
  contract updates, Carbon 3 migrations, schema changes, and package-specific
  migration guidance.
- PHPStan advisory rules and project scans with structured findings, optional
  source annotations, generated per-target configs, and optional Larastan use.
- Data-driven package-major guides for dependency migrations, including
  actionable URLs, severity, installed-version sources, and migration checks.
- Preflight and verification checks for PHP, Composer, extensions, SQLite,
  application boot, routes, caches, tests, and optional PHPStan analysis.
- Real Laravel 11, 12, and 13 test environments, generated-code load gates,
  fixture linting, idempotency checks, and end-to-end upgrade harnesses.
- CI coverage across PHP 8.2, 8.3, and 8.4 with prefer-lowest,
  prefer-stable, independent, and strict Composer validation suites.

### Changed

- Dependency manifest changes are explicit `deps` command decisions rather
  than implicit source transformations.
- Advisories remain in reports by default; writing TODO annotations into
  application source requires the explicit `--annotate` option.
- Upgrade presets include project tests, skip Blade files, use absolute
  generated paths, set the target PHP version, and work from any directory.
- Git checkpointing records a baseline, protects pre-existing dirty paths, and
  checkpoints only the files produced by an upgrade step.

### Fixed

- Generated contract implementations follow the typed and untyped signatures
  exposed by the supported Laravel framework versions.
- Carbon migrations preserve signed and absolute diff intent, escape literal
  format text, and leave unsupported or ambiguous calls for review.
- Schema and migration transforms preserve argument order and only rewrite
  confirmed framework receivers, avoiding unrelated userland classes.
- Import and advisory handling accounts for references in code and docblocks,
  deduplicates per-rule markers, and distinguishes confirmed findings from
  low-confidence matches.
- Reports normalize upgrade facts and findings into stable, reviewable output
  while retaining explicit skipped, unsupported, and not-performed actions.

### Safety

- The orchestrator requires verification before closing a transition and
  supports safe resume after an interrupted run.
- Release validation is local-only: `composer check-release` checks canonical
  version metadata, changelog links, SemVer dates, and annotated tags without
  publishing, pushing, or contacting a package registry.

[Unreleased]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/muhammadsadeeq/laravel-upgrades-rector/releases/tag/v1.0.0
