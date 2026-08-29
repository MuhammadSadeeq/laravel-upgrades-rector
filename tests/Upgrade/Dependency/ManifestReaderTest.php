<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Dependency;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ManifestReader;
use PHPUnit\Framework\TestCase;

final class ManifestReaderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/manifest-reader-'.bin2hex(random_bytes(5));
        mkdir($this->directory.'/vendor/composer', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_installed_json_is_used_when_lock_is_not_present(): void
    {
        file_put_contents($this->directory.'/vendor/composer/installed.json', json_encode([
            'packages' => [['name' => 'livewire/livewire', 'version' => 'v2.12.0']],
        ], JSON_THROW_ON_ERROR));

        $packages = (new ManifestReader)->readLockedPackages($this->directory);

        self::assertSame('v2.12.0', $packages['livewire/livewire']['version'] ?? null);
        self::assertSame('installed', $packages['livewire/livewire']['_source'] ?? null);
    }

    public function test_lock_entries_win_over_installed_fallback_entries(): void
    {
        file_put_contents($this->directory.'/composer.lock', json_encode([
            'packages' => [['name' => 'livewire/livewire', 'version' => 'v2.11.0']],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->directory.'/vendor/composer/installed.json', json_encode([
            'packages' => [['name' => 'livewire/livewire', 'version' => 'v2.12.0']],
        ], JSON_THROW_ON_ERROR));

        $packages = (new ManifestReader)->readLockedPackages($this->directory);

        self::assertSame('v2.11.0', $packages['livewire/livewire']['version'] ?? null);
        self::assertSame('lock', $packages['livewire/livewire']['_source'] ?? null);
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $path) {
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
