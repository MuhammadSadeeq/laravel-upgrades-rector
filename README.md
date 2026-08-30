# Laravel Upgrades Rector

Laravel upgrade tooling for Laravel 10 through 13. The package combines a
journaled CLI orchestrator with Rector transforms, Composer dependency
planning, Laravel skeleton/config synchronization, and PHPStan advisory
checks. It helps with the mechanical parts of a major upgrade; application
behaviour and package-specific migrations still need review.

## Requirements and installation

- PHP `^8.1` (Laravel 13 also requires PHP 8.3).
- Composer 2.2 or newer for the orchestrator.
- Rector `^2.3` (installed by this package).
- A Laravel 10, 11, or 12 project when targeting Laravel 11, 12, or 13.

The package keeps a rolling window of the three newest adjacent upgrade paths;
see the [full support and retirement policy](https://www.sadeeq.dev/docs/laravel-upgrades-rector/v1#support-window-and-retirement) for details.

Install it as a development dependency:

```bash
composer require --dev muhammadsadeeq/laravel-upgrades-rector
```

## Safest quick start

Start from a clean worktree, inspect a complete plan, then apply it:

```bash
git status --short                         # review until clean
vendor/bin/laravel-upgrade plan 11         # preview; writes only .laravel-upgrade/plan.json
vendor/bin/laravel-upgrade to 11           # apply the journaled upgrade
```

The target may be `11`, `12`, or `13`. A multi-major request runs each
one-major transition in order. The default `to` flow enables git safety and
checkpoint commits; use `--no-git` when you want to commit the changes
yourself. A clean worktree is required unless `--allow-dirty` is supplied.

If an apply run stops, fix the reported cause and resume its journal:

```bash
vendor/bin/laravel-upgrade continue
vendor/bin/laravel-upgrade report
```

`continue` requires an incomplete `.laravel-upgrade/state.json`. `report`
regenerates the root `UPGRADE-REPORT.md` from the canonical report. See the
[full v1 documentation](https://www.sadeeq.dev/docs/laravel-upgrades-rector/v1)
for the lifecycle, options, rule reference, and manual upgrade work.

## Useful alternatives

Preview or run one transition step at a time with `skeleton`, `code`,
`advise`, `post`, and `verify`. `deps <major> --dry-run` prints dependency
decisions and Composer preview commands; `deps <major>` applies the manifest
changes, validates them, and runs a Composer solver dry-run.

All engine commands accept `--working-dir`, `--composer`, `--no-install`,
`--no-tests`/`--skip-tests`, `--no-pint`, `--structure=keep|modern`, and
`--no-interaction`. `to` and `plan` additionally accept `--from-step`,
repeatable/comma-separated `--skip-step`, `--constraint-policy=replace|widen`,
`--slim-config`, `--annotate`, `--allow-dirty`, `--no-git`, and `--reset`.
`--plan` and `--dry-run` are aliases where shown by `--help`.

By default the tool processes existing `app`, `bootstrap`, `config`,
`database`, `routes`, and `tests` directories. `structure=keep` preserves the
existing application structure and synchronizes conservatively. The optional
`structure=modern` migration is only supported for Laravel 10 → 11 and needs
complete Laravel 10/11 skeleton snapshots; use `--slim-config` only when you
also want identical Laravel 10 config files removed. Resolve any reported
modern-structure conflicts before continuing.

## Direct Rector usage

The ready-to-use configs are available when the orchestrator is not needed:

```bash
vendor/bin/rector process --dry-run \
  --config=vendor/muhammadsadeeq/laravel-upgrades-rector/config/laravel-13.php
vendor/bin/rector process \
  --config=vendor/muhammadsadeeq/laravel-upgrades-rector/config/laravel-13.php
```

For a custom project config, opt into a set explicitly. Version upgrade sets
do not include generic modernization; add `LaravelUpgradeSetList::MODERNIZE`
only when that separate, opinionated cleanup is wanted.

```php
use MuhammadSadeeq\LaravelUpgradesRector\Set\LaravelUpgradeSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([LaravelUpgradeSetList::LARAVEL_13])
    ->withPaths([__DIR__.'/app', __DIR__.'/bootstrap', __DIR__.'/config']);
```

## Verification

The full flow verifies Composer, changed PHP syntax, changed class loading,
Laravel boot/routes/config cache, and `php artisan test` (unless disabled).
Run the same checks explicitly after reviewing the diff when appropriate:

```bash
composer validate --strict
php artisan test
```

The `advise` step runs the package PHPStan upgrade config and project-level
checks. Use `--annotate` only if you deliberately want its findings copied as
idempotent TODO markers into PHP source files; the default is report-only.

## Contributing and license

Contributions are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md). This
package is released under the [MIT License](LICENSE.md).
