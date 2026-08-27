<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
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

    public function test_reports_renamed_keys_without_touching_the_real_environment(): void
    {
        $path = $this->tmpDir.'/.env.example';
        $envPath = $this->tmpDir.'/.env';
        file_put_contents($path, "CACHE_DRIVER=file\n");
        file_put_contents($envPath, "CACHE_DRIVER=database\nAPP_KEY=secret\n");
        $collector = new FindingCollector;

        $result = $this->merger->merge($path, 11, null, $collector);

        self::assertStringContainsString('CACHE_STORE=database', $result);
        self::assertSame("CACHE_DRIVER=database\nAPP_KEY=secret\n", file_get_contents($envPath));
        self::assertCount(1, $collector->all());
        self::assertSame(['APP_KEY'], $this->merger->missingFromEnvironment($envPath, $path));
    }

    public function test_uses_a_complete_upstream_example_when_supplied(): void
    {
        $path = $this->tmpDir.'/.env.example';
        $upstream = $this->tmpDir.'/upstream.env';
        file_put_contents($path, "APP_NAME=Laravel\n");
        file_put_contents($upstream, "APP_NAME=Laravel\nNEW_KEY=from-upstream\n");

        $result = $this->merger->merge($path, 12, $upstream);

        self::assertStringContainsString('NEW_KEY=from-upstream', $result);
        self::assertStringContainsString('# Laravel 12', $result);
    }
}
