<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Support\Composer;

use MuhammadSadeeq\LaravelUpgradesRector\Support\Composer\Laravel12ComposerJsonUpdater;
use PHPUnit\Framework\TestCase;

final class Laravel12ComposerJsonUpdaterTest extends TestCase
{
    public function testItUpdatesComposerJsonForLaravel12(): void
    {
        $temporaryDirectory = sys_get_temp_dir() . '/laravel-upgrades-rector-' . uniqid('', true);
        mkdir($temporaryDirectory, 0777, true);

        $composerJsonPath = $temporaryDirectory . '/composer.json';

        file_put_contents($composerJsonPath, json_encode([
            'require' => [
                'laravel/framework' => '^11.31',
                'livewire/livewire' => '^3.0',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^10.5',
                'pestphp/pest' => '^2.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $updater = new Laravel12ComposerJsonUpdater();

        self::assertTrue($updater->update($composerJsonPath));

        $updatedComposerJson = $this->readComposerJson($composerJsonPath);

        self::assertSame('^12.0', $updatedComposerJson['require']['laravel/framework']);
        self::assertSame('^3.4', $updatedComposerJson['require']['livewire/livewire']);
        self::assertSame('^11.0', $updatedComposerJson['require-dev']['phpunit/phpunit']);
        self::assertSame('^3.0', $updatedComposerJson['require-dev']['pestphp/pest']);

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
