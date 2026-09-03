# Contributing

Contributions are **welcome** and will be fully **credited**!

Thank you for considering contributing to Laravel Upgrades Rector. This guide will help you get started.

## Ways to Contribute

- **Bug Reports** - Found an issue? Let us know!
- **Feature Requests** - Have an idea? We'd love to hear it!
- **Documentation** - Improve our docs
- **Tests** - Add test coverage
- **Code** - Fix bugs or add features

## Pull Request Guidelines

### Before Submitting

1. **Search existing PRs** - Someone might already be working on it
2. **Open an issue first** - Discuss major changes before coding
3. **Fork the repository** - Work in your own fork
4. **Create a feature branch** - Don't submit from `main`

### Code Standards

- **PSR-12 Coding Standard** - Follow PHP coding standards
- **Type Declarations** - Use strict types and type hints
- **Rector Conventions** - Follow Rector's rule patterns
- **Meaningful Names** - Use descriptive variable and method names

### Testing Requirements

**All pull requests MUST include tests.** Your PR will not be accepted without proper test coverage.

```bash
# Run all tests
composer test

# Run specific test suite
LARAVEL_ENV=11 vendor/bin/phpunit --testsuite=env-11
LARAVEL_ENV=12 vendor/bin/phpunit --testsuite=env-12
LARAVEL_ENV=13 vendor/bin/phpunit --testsuite=env-13
vendor/bin/phpunit --testsuite=independent

# Run static analysis
composer analyse

# Check formatting and release metadata
composer format-test
composer check-release
```

The environment suites load the matching real Laravel vendor tree. They must
run in separate processes because each environment supplies its own framework
autoloading. `composer test` runs `env-11`, `env-12`, `env-13`, and
`independent` (which contains the orchestrator, report, and support tests).

## Release checklist

Before preparing a release:

Version bumps represent actual public releases only; keep development changes
in `[Unreleased]` until a release is ready.

1. Update `src/PackageInfo.php` and add the matching dated section and release
   link to `CHANGELOG.md`: use a release-tag URL for the first release and a
   compare link to the previous release thereafter. Keep an `[Unreleased]`
   section at the top.
2. Run `composer validate --strict`, `composer test`, `composer analyse`,
   `composer format-test`, and `composer check-release`.
3. Run the end-to-end harness for `10 11`, `11 12`, and `12 13` with
   `php bin/e2e <from> <to>` and review the generated diffs.
4. Review the working tree and ensure the release commit contains only the
   intended source, test, workflow, and changelog changes.
5. Create an annotated tag (never a lightweight tag):

   ```bash
   git tag -a vX.Y.Z -m "Release vX.Y.Z"
   git show --format=fuller --no-patch vX.Y.Z
   composer check-release
   ```

6. Push the commit and tag only after the tag validation succeeds. The release
   workflow validates the tag object and metadata; it does not publish or push
   anything.

### Adding a New Rule

1. **Create the Rector rule** in `src/Rector/Laravel{version}/`
2. **Add to the set configuration** in `src/Set/laravel-{version}.php`
3. **Create test fixtures** in `tests/Rector/Laravel{version}/YourRule/Fixture/`
4. **Create test configuration** in `tests/Rector/Laravel{version}/YourRule/config/`
5. **Create test class** in `tests/Rector/Laravel{version}/YourRule/YourRuleTest.php`
6. **Add stubs** if your rule needs type resolution (in `stubs/`)
7. **Update documentation** if needed

#### Test Structure

Each rule should have its own directory under `tests/Rector/Laravel{version}/`:

```
tests/Rector/Laravel13/MyNewRector/
  MyNewRectorTest.php
  config/configured_rule.php
  Fixture/
    positive_case.php.inc      # Input + expected output (separated by -----)
    skip_unrelated.php.inc     # Should not be modified (no separator)
    skip_already_done.php.inc  # Idempotency check (no separator)
```

#### Example Test Class

```php
<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Rector\Laravel13\MyNewRector;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class MyNewRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public static function provideData(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_rule.php';
    }
}
```

#### Shared Utilities

If your rule needs interface or method detection, use the existing utilities in `src/Support/NodeAnalyzer/`:

- **InterfaceImplementationChecker** - Check if a class implements an interface and whether it already has a method
- **StaticCallExtractor** - Extract static call nodes from expressions

### Static Analysis

Run PHPStan to ensure code quality:

```bash
composer analyse
```

Fix any issues before submitting your PR.

### Commit Messages

- Use clear, descriptive commit messages
- Reference issue numbers when applicable
- Follow conventional commits format (optional but appreciated):
  ```
  feat: add Laravel 14 upgrade rules
  fix: correct floating point type conversion
  docs: update README examples
  test: add missing test for UUID migration
  ```

### Pull Request Process

1. **Run all tests** - Ensure they pass (`composer test`)
2. **Run static analysis** - Fix any PHPStan errors (`composer analyse`)
3. **Update documentation** - CHANGELOG, README if needed
4. **Create PR** with clear description:
   - What changes were made
   - Why the changes are needed
   - Link to related issue(s)
   - Before/after examples (if applicable)
5. **Respond to feedback** - Address review comments promptly

## Development Setup

```bash
# Clone your fork
git clone https://github.com/YOUR-USERNAME/laravel-upgrades-rector.git
cd laravel-upgrades-rector

# Install dependencies
composer install

# Install the per-version framework environments the rule suites analyse
# against. These are gitignored and required: without them the env suites
# stop with a clear error instead of silently skipping.
composer setup-envs

# Run tests to ensure everything works
composer test

# Run static analysis
composer analyse
```

## Questions or Need Help?

- [Open a Discussion](https://github.com/muhammadsadeeq/laravel-upgrades-rector/discussions)
- Email: afridi.sadeeq.m@gmail.com
- [Report an Issue](https://github.com/muhammadsadeeq/laravel-upgrades-rector/issues)

## Code of Conduct

Be respectful, inclusive, and constructive. We're all here to make Laravel upgrades easier for everyone.
