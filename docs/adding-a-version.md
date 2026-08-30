# Adding a Laravel major version

This is the maintainer checklist for adding a new source-to-target transition.
This checkout has Laravel 10–13 skeletons and supports targets 11, 12, and 13.
Laravel 14 below is an example of the work required; it is not supported until
all gates pass. Do not claim any Laravel 14 requirement without checking the
target framework metadata, skeleton, and official upgrade guide.

The repository’s design has four important boundaries:

- A multi-major upgrade is adjacent transitions (10 → 11 → 12 → 13 → 14).
- Composer dependencies are installed before target-dependent Rector/PHPStan.
- Syntax changes are Rector rules; behaviour changes are findings, config
  policies, post-install steps, or verification checks.
- `plan` may write its plan artifact, but must not modify project files.

## 1. Baseline and discovery

From the repository root, run the checks that exist today:

```sh
git status --short
composer validate --strict
composer test
composer analyse
vendor/bin/pint --test
php bin/build-skeletons --check
```

`composer test` runs the env-11, env-12, env-13, and independent PHPUnit
suites. The env suites need the ignored `tests/env/laravel-*/vendor/` trees;
an absent `vendor/autoload.php` is not a passing environment. Use this review
search after changing a support bound (it is not a generator):

```sh
rg -n --glob '!vendor/**' --glob '!docs/**' \
  '10\\|11\\|12\\|13|\\[11, 12, 13\\]|MAX_SUPPORTED_TARGET|LARAVEL_1[123]|laravel-1[123]|env-1[123]' \
  src config tests bin composer.json .github
```

Before adding target data, record the exact target framework/skeleton release,
commit, PHP floor, extensions, direct dependency minimums, removals, default
changes, and contract changes from verified sources.

## 2. Pin the target skeleton

`SkeletonStep` classifies and merges snapshots in `resources/skeletons/{major}`;
provenance is `resources/skeletons/MANIFEST.json`. Current pinned
`laravel/laravel` tags are `v10.3.3`, `v11.6.1`, `v12.12.2`, and `v13.10.1`.

For Laravel 14:

1. Add the verified tag and commit to `SkeletonBuilder::PINNED_SNAPSHOTS` in
   `src/Upgrade/Skeleton/SkeletonBuilder.php`. Extend its accepted-major
   parser and manifest filter too; they currently accept only 10–13.
2. Build and validate the snapshot:

   ```sh
   php bin/build-skeletons 14
   php bin/build-skeletons --check 14
   php bin/build-skeletons --check --remote 14
   ```

   The build uses the GitHub Laravel repository and therefore needs network
   access. The snapshot must contain `composer.json`, `.env.example`,
   `artisan`, `bootstrap/app.php`, `config/app.php`, `routes/web.php`,
   `tests/TestCase.php`, and `public/index.php`; it rejects symlinks, excludes
   `.git`, `README.md`, and `node_modules`, preserves executable modes, and is
   limited to 1 MiB. Confirm `MANIFEST.json` has the matching tag, commit,
   completeness, size, and tree hash.
3. Review the 13 → 14 diff using the existing merge rules. Config files use
   `ConfigArrayMerger`; `phpunit.xml`, `vite.config.js`, `.gitignore`, and
   `.editorconfig` use `NonPhpFileMerger`; `package.json` is advisory-only.
   Routes, application code, most tests, Composer manifests/locks, `.env`, and
   migrations are not copied automatically. Removed ordinary, non-excluded
   files are findings in `keep` structure mode, not automatic deletions. An
   incomplete manifest only permits config reconciliation, so it cannot prove
   upstream file removals.

## 3. Add verified compatibility and policy data

Keep the existing JSON shapes and validate every value against the target
release. The package-guide schema and compatibility readers reject malformed
data; config policies should still be checked manually because an absent policy
file simply yields no policy findings.

- `resources/compat/php.json`: add a `"14"` entry under `php` with the
  target PHP floor and required extensions. `PreflightStep` also checks PHP,
  extensions, Composer 2.2+, strict Composer validation, SQLite, and the
  existing Laravel 11 cURL floor.
- `resources/compat/packages.json`: add verified target minimums for every
  relevant direct package, including PHP, framework or `illuminate/*`, Laravel
  ecosystem packages, PHPUnit/Pest, Pest’s Laravel plugin, Larastan, and
  optional packages. The planner proposes `^<minimum>`; unknown package/target
  combinations remain manual review. This file has no sibling schema today.
- `resources/compat/removals.json`: add only confirmed package removals,
  replacements, and target-skeleton removals. Skeleton removals produce
  findings; a package is removed only when no locked package still requires it.
- `resources/compat/post-install-steps.json`: add only verified target-14
  operations. Existing common commands are `composer dump-autoload
  --no-interaction`, `php artisan config:clear`, `route:clear`, and
  `view:clear`; package migration data is conservative and marker-based.
- `resources/config-policies/14.json`: use the current `behaviourChanging`
  and `structureOnly` shape. Policy fields are conditional: entries may be
  informational or transition entries (`informational`/`transition`), while a
  default-change entry can provide `upstreamDefault` and/or `preserveValue`.
  `severity`, `message`, and `action` have loader defaults; `guide` or
  `guideUrl` is optional. `ConfigArrayMerger` preserves project values and
  reports changed defaults/removals. Never put secrets or `.env` values here;
  `EnvExampleMerger` never writes the real `.env`.
- `resources/contracts/laravel-14.php`: return a list matching the existing
  contract specs. Compare actual source and target interfaces, encode exact
  signatures (never narrow an untyped parameter), and add loadable fixtures.
  If no new method is required, a valid `return [];` is correct.
- `resources/compat/package-guides.json`: only add verified guide items. It is
  validated by `package-guides.schema.json`; every major has non-empty
  `items`, and a `future` major also needs non-empty `notes`.
- `resources/compat/support.json`: add the adjacent source-to-target path and
  source PHP/security-fix facts to the rolling maximum-three window. Run
  `composer check-support-policy` after editing it. When a fourth path is
  implemented, a maintainer must review the retirement predicate (replacement
  path creates window pressure and the oldest source is past security EOL)
  before removing the oldest path; this decision cannot be inferred safely
  from Git state alone.

## 4. Register transforms, advisories, and support bounds

Add only verified syntax changes under `src/Rector/Laravel14/`, with namespaced
`.php.inc` fixtures under `tests/Rector/Laravel14/`. Fixtures need a `-----`
before/after separator or a `skip_` name, parseable rule samples, and must be
idempotent. Advisory comments do not belong in a transform set.

Create `src/Set/laravel-14.php`, register `LARAVEL_14` and
`UP_TO_LARAVEL_14` in `src/Set/LaravelUpgradeSetList.php` and the cumulative
set file, and add `config/laravel-14.php` with the existing paths/skips,
verified PHP version, target set, and optional Larastan extension. Update the
generated target PHP mapping in `RectorConfigGenerator`; its current fallback
is PHP 8.2. Use `carbon-3.php` only when installed Carbon metadata satisfies
`^3`, not merely because the Laravel target is newer.

Update all target gates before invoking the CLI:

- `resources/compat/support.json`: the single source of truth for the
  implemented adjacent paths, target lists, source PHP floors, and security
  dates; validate it with `composer check-support-policy`.
- `UpgradePlan.php`, `ToCommand.php`, `SingleStepCommand.php`, `DepsCommand.php`,
  and `PhpStanConfigGenerator.php`: consume the central policy rather than
  maintaining independent supported-major lists.
- `SkeletonBuilder.php` and `RectorConfigGenerator.php`: snapshot and PHP maps.
- Target branches in `ProjectAdvisor.php`, `VerifyStep.php`,
  `PublishedViewChecker.php`, and `PostStep.php`, only for verified behavior.

Keep the canonical order `preflight → dependencies → install → skeleton →
code → advisories → post → verify → commit`; `UpgradePlan` (used by `to` and
`plan`) is the component that expands and validates adjacent transitions.
Standalone engine commands still require detected source + 1. Target-based
`deps <target>` and `php bin/build-skeletons <major>` do not expand a
multi-major path. `verify` remains non-skippable.

For advisory behaviour, create `resources/phpstan/upgrade-14.neon` and new
rules under `src/PHPStan/Rules/`. Current neon files register `Rule` services
with the `phpstan.rules.rule` tag, level 0, and unmatched ignored errors off.
Add focused positive, negative, and boundary fixtures; update
`AdvisoryRuleRegistrationTest.php`’s exact class list. The current
`composer test` and `phpunit.xml` `independent` suite do not include
`tests/PHPStan`. Add a `phpstan` suite for that directory, a Composer
`test-phpstan` script (`phpunit --testsuite phpstan`), and `@test-phpstan` to
the aggregate `test` script. Verify it with:

```sh
vendor/bin/phpunit --testsuite phpstan
composer test-phpstan
```

`composer analyse` statically scans source/tests/fixtures but does not execute
these PHPUnit `RuleTestCase` fixtures. The generated
`.laravel-upgrade/phpstan-<major>.neon` includes Larastan when installed and
passes project facts (database drivers, queue default, session format).

## 5. Extend real-framework tests

Add `tests/env/laravel-14/composer.json` with verified constraints and test
dependencies, then generate/check in its lockfile:

```sh
cd tests/env/laravel-14
composer update --prefer-dist --no-interaction --no-progress
cd ../../..
```

Update the accepted environment list in `tests/bootstrap.php` and
`tests/Support/EnvAutoload.php`, environment maps in
`tests/Support/AbstractUpgradeRectorTestCase.php` and
`GeneratedCodeLoadsTest.php`, the `env-14` suite in `phpunit.xml`, the scripts
in `composer.json`, and the hard-coded script map in
`tests/Support/CodeStyleGatesTest.php`. Update transition assertions in the
upgrade-plan, command, report, dependency, and generator tests. The real
vendor tree is required for generated-code loading; run fixtures in a fresh
process with `@loads` where appropriate.

For the new target, run:

```sh
LARAVEL_ENV=14 php vendor/bin/phpunit --testsuite env-14
LARAVEL_ENV=14 php vendor/bin/phpunit tests/Support/GeneratedCodeLoadsTest.php
```

## 6. Add 13 → 14 E2E coverage

`bin/e2e` creates a real skeleton, installs this package from a local path,
checks dependency preview/apply and Composer validation, runs Rector twice,
then checks JSON rule output, changed PHP lint/load, boot, migrations, tests,
and idempotency. Add:

- realistic plants under `tests/E2E/plants/13`;
- `tests/E2E/expected/13-14-rules.txt` with expected `applied_rectors` names;
- a 13 → 14 matrix entry in `.github/workflows/e2e.yml`;
- any verified target-specific check needed in `bin/e2e`.

Exercise changed APIs, contract implementations, config or migration findings,
and at least one advisory decision. The current harness does not run the
advisory step. Add this check before `deps applies cleanly`, while the app is
still detected as Laravel 13:

```sh
vendor/bin/laravel-upgrade advise 14 --no-install --no-pint --no-interaction
```

`AdvisoryStep` invokes `vendor/bin/phpstan analyse -c
.laravel-upgrade/phpstan-14.neon --error-format=json` internally (with its
resolved project paths) and writes `.laravel-upgrade/findings.jsonl`. In the
new `bin/e2e` check, parse each JSONL line and assert a finding has the
verified new rule’s exact `ruleId` and `laravelVersion: 14`; do not assert only
that the command exited successfully. For an isolated analyzer check, run:

```sh
vendor/bin/phpstan analyse -c .laravel-upgrade/phpstan-14.neon --error-format=json app
```

Expect PHPStan exit code 1 when the planted advisory is found. The harness
ignores `vendor/`, `node_modules/`, `bootstrap/cache/`, `storage/framework/`,
and `.phpunit.cache` when comparing trees.

Run the new path and all existing gates:

```sh
php bin/e2e 13 14
composer test
composer analyse
vendor/bin/pint --test
php bin/e2e 10 11
php bin/e2e 11 12
php bin/e2e 12 13
php bin/e2e 13 14
```

## 7. Release checklist

- [ ] Target guide, framework requirements, package requirements, skeleton tag,
      and provenance are recorded from verified sources.
- [ ] Snapshot 14 is complete, hashed in `MANIFEST.json`, and passes offline
      and (when intended) remote `build-skeletons --check`.
- [ ] PHP/package/removal/post-install/config-policy/contract data contains
      only verified 14 facts; unknown package data is explicit manual review.
- [ ] Rector set, cumulative set, set-list constants, direct config, PHPStan
      neon/rules, registration tests, and fixtures are present and idempotent;
      the explicit `phpstan` PHPUnit suite passes.
- [ ] All target gates accept 14; `UpgradePlan`/the orchestrator expands and
      validates adjacent transitions, while standalone target-based commands
      behave as documented.
- [ ] Env-14 PHPUnit and generated-code tests, E2E plants/expected rules, and
      CI matrix pass; existing 10 → 11, 11 → 12, and 12 → 13 paths still pass.
- [ ] `plan 14` is byte-neutral apart from its plan artifact; Composer
      validation, lint/load, boot, route/config checks, migrations, PHPStan,
      and application tests pass.
- [ ] Update README supported versions, changelog, and release notes only
      after implementation and CI checks pass.

## Common traps and future tooling

- Resource files alone do not enable a target: current CLI regexes and lists
  explicitly reject 14 until all support bounds are changed.
- Do not copy Laravel 13 compatibility values, narrow interface signatures,
  run Rector without the target vendor tree, copy migrations, edit `.env`, or
  turn advisory findings into automatic syntax rewrites.
- A partial skeleton cannot establish upstream deletions, and a PHPStan neon
  file without matching rule classes/tests is incomplete.
- `bin/check-guide-drift` and `bin/build-compat-matrix` are available
  maintainer tools. Use `php bin/check-guide-drift --offline` for a checked-in
  guide/skeleton drift check and `php bin/build-compat-matrix --offline --check`
  for a read-only compatibility-matrix check. Use their fixture or remote
  modes only when the source data has been reviewed; `--write` on the matrix
  is intentionally explicit.
- The PHP 8.1 smoke job in `.github/workflows/tests.yml` resolves a temporary
  runtime-only Composer manifest without dev requirements, then checks the
  support policy and CLI help. The normal 8.2+ matrix runs the full test and
  analysis commands; do not add PHPUnit 11 to the PHP 8.1 job.

Current supported examples are `vendor/bin/laravel-upgrade plan 13`,
`to 13`, `continue`, `report`, `deps 13 --dry-run`, and
`php bin/build-skeletons --check`. The analogous `plan 14`, `to 14`, and
`deps 14 --dry-run` commands become valid only after this work and its gates
are complete.
