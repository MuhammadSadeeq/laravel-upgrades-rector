# Contributing

Contributions are **welcome** and will be fully **credited**!

Thank you for considering contributing to Laravel Upgrades Rector. This guide will help you get started.

## Ways to Contribute

- 🐛 **Bug Reports** - Found an issue? Let us know!
- ✨ **Feature Requests** - Have an idea? We'd love to hear it!
- 📝 **Documentation** - Improve our docs
- 🧪 **Tests** - Add test coverage
- 🔧 **Code** - Fix bugs or add features

## Pull Request Guidelines

### Before Submitting

1. **Search existing PRs** - Someone might already be working on it
2. **Open an issue first** - Discuss major changes before coding
3. **Fork the repository** - Work in your own fork
4. **Create a feature branch** - Don't submit from `main`

### Code Standards

- ✅ **PSR-12 Coding Standard** - Follow PHP coding standards
- ✅ **Type Declarations** - Use strict types and type hints
- ✅ **Rector Conventions** - Follow Rector's rule patterns
- ✅ **Meaningful Names** - Use descriptive variable and method names

### Testing Requirements

**All pull requests MUST include tests.** Your PR will not be accepted without proper test coverage.

```bash
# Run all tests
composer test

# Run specific test suite
vendor/bin/phpunit tests/Rector/Laravel11/
vendor/bin/phpunit tests/Rector/Laravel12/

# Run with coverage (optional)
vendor/bin/phpunit --coverage-html coverage/
```

### Adding a New Rule

1. **Create the Rector rule** in `src/Rector/Laravel11/` or `src/Rector/Laravel12/`
2. **Add to the set configuration** in `src/Set/laravel-11.php` or `src/Set/laravel-12.php`
3. **Create test fixtures** in `tests/Rector/Laravel11/YourRule/Fixture/`
4. **Create test configuration** in `tests/Rector/Laravel11/YourRule/config/`
5. **Create test class** in `tests/Rector/Laravel11/YourRule/YourRuleTest.php`
6. **Update documentation** if needed

#### Example Test Structure

```php
<?php

// tests/Rector/Laravel12/MyNewRector/MyNewRectorTest.php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Rector\Laravel12\MyNewRector;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12\MyNewRector;

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
  feat: add Laravel 13 upgrade rules
  fix: correct floating point type conversion
  docs: update README examples
  test: add missing test for UUID migration
  ```

### Pull Request Process

1. **Update documentation** - README, CHANGELOG, etc.
2. **Run all tests** - Ensure they pass
3. **Run static analysis** - Fix any PHPStan errors
4. **Create PR** with clear description:
   - What changes were made
   - Why the changes are needed
   - Link to related issue(s)
   - Before/after examples (if applicable)

5. **Respond to feedback** - Address review comments promptly
6. **Squash commits** - Keep history clean (if requested)

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

- 💬 [Open a Discussion](https://github.com/muhammadsadeeq/laravel-upgrades-rector/discussions)
- 📧 Email: afridi.sadeeq.m@gmail.com
- 🐛 [Report an Issue](https://github.com/muhammadsadeeq/laravel-upgrades-rector/issues)

## Code of Conduct

Be respectful, inclusive, and constructive. We're all here to make Laravel upgrades easier for everyone.

---

**Happy coding!** 🚀
