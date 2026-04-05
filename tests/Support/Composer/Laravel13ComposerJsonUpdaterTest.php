<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Support\Composer;

use MuhammadSadeeq\LaravelUpgradesRector\Support\Composer\Laravel13ComposerJsonUpdater;
use PHPUnit\Framework\TestCase;

final class Laravel13ComposerJsonUpdaterTest extends TestCase
{
    public function testItUpdatesComposerJsonForLaravel13(): void
    {
        $temporaryDirectory = sys_get_temp_dir() . '/laravel-upgrades-rector-' . uniqid('', true);
        mkdir($temporaryDirectory, 0777, true);

        $composerJsonPath = $temporaryDirectory . '/composer.json';

        file_put_contents($composerJsonPath, json_encode([
            'require' => [
                'laravel/framework' => '^12.0',
                'laravel/boost' => '^1.0',
                'laravel/tinker' => '^2.0',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^11.0',
                'pestphp/pest' => '^3.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $updater = new Laravel13ComposerJsonUpdater();

        self::assertTrue($updater->update($composerJsonPath));

        $updatedComposerJson = $this->readComposerJson($composerJsonPath);

        self::assertSame('^13.0', $updatedComposerJson['require']['laravel/framework']);
        self::assertSame('^2.0', $updatedComposerJson['require']['laravel/boost']);
        self::assertSame('^3.0', $updatedComposerJson['require']['laravel/tinker']);
        self::assertSame('^12.0', $updatedComposerJson['require-dev']['phpunit/phpunit']);
        self::assertSame('^4.0', $updatedComposerJson['require-dev']['pestphp/pest']);

        unlink($composerJsonPath);
        rmdir($temporaryDirectory);
    }

    /**
     * @return array{
     *     require: array<string, string>,
     *     require-dev: array<string, string>
     * }
     */
    private function readComposerJson(string $composerJsonPath): array
    {
        $decodedComposerJson = json_decode((string) file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decodedComposerJson);
        self::assertArrayHasKey('require', $decodedComposerJson);
        self::assertArrayHasKey('require-dev', $decodedComposerJson);
        self::assertIsArray($decodedComposerJson['require']);
        self::assertIsArray($decodedComposerJson['require-dev']);

        return [
            'require' => $this->normalizeStringMap($decodedComposerJson['require']),
            'require-dev' => $this->normalizeStringMap($decodedComposerJson['require-dev']),
        ];
    }

    /**
     * @param array<mixed> $values
     * @return array<string, string>
     */
    private function normalizeStringMap(array $values): array
    {
        $normalizedValues = [];

        foreach ($values as $key => $value) {
            self::assertIsString($key);
            self::assertIsString($value);

            $normalizedValues[$key] = $value;
        }

        return $normalizedValues;
    }
}
