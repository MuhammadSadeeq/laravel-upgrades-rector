<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Rector\Laravel13\UpdateComposerDependenciesLaravel13Rector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class UpdateComposerDependenciesLaravel13RectorTest extends AbstractRectorTestCase
{
    private string $temporaryDirectory = '';

    public function test(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/laravel-upgrades-rector-' . uniqid('', true);
        $fixturePath = $this->temporaryDirectory . '/Example.php.inc';

        mkdir($this->temporaryDirectory, 0777, true);

        file_put_contents($this->temporaryDirectory . '/composer.json', <<<'JSON'
{
    "require": {
        "laravel/framework": "^12.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    }
}
JSON);

        file_put_contents($fixturePath, <<<'PHP'
<?php

namespace App;

final class Example
{
        }
PHP);

        $this->doTestFile($fixturePath);

        $composerJsonContents = file_get_contents($this->temporaryDirectory . '/composer.json');

        self::assertIsString($composerJsonContents);
        self::assertStringContainsString('"laravel/framework": "^13.0"', $composerJsonContents);
        self::assertStringContainsString('"phpunit/phpunit": "^12.0"', $composerJsonContents);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->temporaryDirectory === '') {
            return;
        }

        @unlink($this->temporaryDirectory . '/Example.php.inc');
        @unlink($this->temporaryDirectory . '/composer.json');
        @rmdir($this->temporaryDirectory);
        $this->temporaryDirectory = '';
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_rule.php';
    }
}
