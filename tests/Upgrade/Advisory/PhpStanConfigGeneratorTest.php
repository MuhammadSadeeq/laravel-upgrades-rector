<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Advisory;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory\PhpStanConfigGenerator;
use PHPUnit\Framework\TestCase;

final class PhpStanConfigGeneratorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phpstan-config-generator-'.uniqid('', true);
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_after_commit_service_override_is_generated_only_for_laravel_11(): void
    {
        $generator = new PhpStanConfigGenerator(dirname(__DIR__, 3));

        foreach ([11, 12, 13] as $targetMajor) {
            $path = $generator->generate(
                $this->directory,
                $targetMajor,
                $this->directory.'/generated-'.$targetMajor,
                queueDefault: 'sync',
            );
            $contents = (string) file_get_contents($path);

            self::assertStringContainsString(
                'errorFormatter.laravelUpgradeJson:',
                $contents,
                'Advisory JSON must preserve RuleError metadata across the process boundary.',
            );
            self::assertStringContainsString(
                'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\JsonErrorFormatter',
                $contents,
            );

            if ($targetMajor === 11) {
                self::assertStringContainsString('afterCommitWithSyncQueueRule:', $contents);
                self::assertStringContainsString("queueDefault: 'sync'", $contents);
            } else {
                self::assertStringNotContainsString('afterCommitWithSyncQueueRule:', $contents);
            }
        }
    }

    public function test_local_disk_context_is_generated_only_for_laravel_12(): void
    {
        $generator = new PhpStanConfigGenerator(dirname(__DIR__, 3));

        foreach ([11, 12, 13] as $targetMajor) {
            $path = $generator->generate(
                $this->directory,
                $targetMajor,
                $this->directory.'/local-disk-'.$targetMajor,
                localDiskRootConfigured: true,
                localDiskIsDefault: false,
            );
            $contents = (string) file_get_contents($path);

            if ($targetMajor === 12) {
                self::assertStringContainsString('localDiskDefaultRootRule:', $contents);
                self::assertStringContainsString('localDiskRootConfigured: true', $contents);
                self::assertStringContainsString('localDiskIsDefault: false', $contents);
            } else {
                self::assertStringNotContainsString('localDiskDefaultRootRule:', $contents);
            }
        }
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
