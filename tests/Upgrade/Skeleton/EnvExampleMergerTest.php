<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\EnvExampleMerger;
use PHPUnit\Framework\TestCase;

final class EnvExampleMergerTest extends TestCase
{
    private EnvExampleMerger $merger;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->merger = new EnvExampleMerger;
        $this->tmpDir = sys_get_temp_dir().'/env-merge-'.uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpDir.'/.env.example');
        @rmdir($this->tmpDir);
    }

    public function test_adds_new_keys_for_laravel11(): void
    {
        $path = $this->tmpDir.'/.env.example';
        file_put_contents($path, "APP_NAME=Laravel\nAPP_KEY=\n");

        $result = $this->merger->merge($path, 11);

        self::assertStringContainsString('LOG_DEPRECATIONS_CHANNEL', $result);
        self::assertStringContainsString('# Laravel 11', $result);
    }

    public function test_skips_existing_keys(): void
    {
        $path = $this->tmpDir.'/.env.example';
        file_put_contents($path, "APP_NAME=X\nLOG_TRACE=true\n");

        $result = $this->merger->merge($path, 11);

        self::assertSame(substr_count($result, 'LOG_TRACE'), 1);
    }
}
