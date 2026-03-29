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
vendor/bin/phpunit --testsuite="Laravel 11 Upgrade Rules"
vendor/bin/phpunit --testsuite="Laravel 12 Upgrade Rules"
vendor/bin/phpunit --testsuite="Laravel 13 Upgrade Rules"

# Run static analysis
composer analyse
```

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
