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
        file_put_contents($path, "CACHE_DRIVER=file\nAPP_NAME=Laravel\n");
        file_put_contents($envPath, "CACHE_DRIVER=database\nAPP_KEY=secret\n");
        $collector = new FindingCollector;

        $result = $this->merger->merge($path, 11, null, $collector);

        self::assertStringContainsString('CACHE_STORE=database', $result);
        self::assertSame("CACHE_DRIVER=database\nAPP_KEY=secret\n", file_get_contents($envPath));
        self::assertCount(1, $collector->all());
        self::assertSame(['APP_NAME'], $this->merger->missingFromEnvironment($envPath, $path));
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

    public function test_preserves_upstream_order_and_crlf_when_appending_new_keys(): void
    {
        $path = $this->tmpDir.'/.env.example';
        $upstream = $this->tmpDir.'/upstream.env';
        file_put_contents($path, "APP_NAME=Laravel\r\nCACHE_DRIVER=file\r\n");
        file_put_contents($upstream, "APP_NAME=Laravel\r\nLOG_NEW=one\r\nCACHE_DRIVER=database\r\nSECOND_NEW=two\r\n");

        $result = $this->merger->merge($path, 11, $upstream);

        self::assertStringContainsString("# Laravel 11\r\nLOG_NEW=one\r\nSECOND_NEW=two\r\n", $result);
        self::assertSame(1, substr_count($result, 'LOG_NEW='));
        self::assertSame(1, substr_count($result, 'SECOND_NEW='));
    }

    public function test_complete_base_does_not_resurrect_removed_keys_and_keeps_commented_defaults(): void
    {
        $path = $this->tmpDir.'/.env.example';
        $base = $this->tmpDir.'/base.env';
        $upstream = $this->tmpDir.'/upstream.env';
        file_put_contents($base, "APP_NAME=Laravel\nREMOVED_FROM_PROJECT=old\n");
        file_put_contents($path, "APP_NAME=Laravel\n");
        file_put_contents($upstream, "APP_NAME=Laravel\nREMOVED_FROM_PROJECT=new\n# NEW_DEFAULT=database\nNEW_KEY=value\n");

        $result = $this->merger->merge($path, 11, $upstream, null, $base);

        self::assertStringNotContainsString('REMOVED_FROM_PROJECT=', $result);
        self::assertStringContainsString('# NEW_DEFAULT=database', $result);
        self::assertStringContainsString('NEW_KEY=value', $result);
        self::assertStringContainsString("APP_NAME=Laravel\n# NEW_DEFAULT=database\nNEW_KEY=value", $result);
        self::assertStringNotContainsString('# Laravel 11', $result);
    }

    public function test_missing_environment_keys_can_be_checked_against_a_proposed_example(): void
    {
        self::assertSame(
            ['NEW_KEY'],
            $this->merger->missingFromEnvironmentContents("APP_NAME=Laravel\n", "APP_NAME=Laravel\nNEW_KEY=value\n"),
        );
    }

    public function test_complete_snapshot_additions_append_as_a_group_when_no_project_anchor_exists(): void
    {
        $path = $this->tmpDir.'/.env.example';
        $base = $this->tmpDir.'/base.env';
        $upstream = $this->tmpDir.'/upstream.env';
        file_put_contents($base, "BASE_KEY=old\r\n");
        file_put_contents($path, "PROJECT_ONLY=value\r\n");
        file_put_contents($upstream, "BASE_KEY=new\r\n\r\n# New settings\r\nNEW_KEY=target\r\n# NEW_DEFAULT=database\r\n");

        $result = $this->merger->merge($path, 11, $upstream, null, $base);

        self::assertStringContainsString(
            "PROJECT_ONLY=value\r\n\r\n# New settings\r\nNEW_KEY=target\r\n# NEW_DEFAULT=database\r\n",
            $result,
        );
        self::assertStringNotContainsString('# Laravel 11', $result);
        file_put_contents($path, $result);
        self::assertSame($result, $this->merger->merge($path, 11, $upstream, null, $base));
        self::assertSame(
            ['NEW_KEY', 'NEW_DEFAULT'],
            $this->merger->missingFromEnvironmentContents("PROJECT_ONLY=value\r\n", $result),
        );
    }
}
