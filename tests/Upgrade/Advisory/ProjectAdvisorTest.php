<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Advisory;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory\ProjectAdvisor;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use PHPUnit\Framework\TestCase;

final class ProjectAdvisorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/project-advisor-'.uniqid('', true);
        mkdir($this->root.'/config', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function test_reports_project_configuration_packages_views_and_removed_files(): void
    {
        $this->write('config/database.php', "<?php\nreturn ['default' => env('DB_CONNECTION', 'sqlite'), 'connections' => ['mysql' => ['driver' => 'mysql']]];\n");
        $this->write('config/session.php', "<?php\nreturn ['serialization' => 'php'];\n");
        $this->write('config/cache.php', "<?php\nreturn ['prefix' => env('CACHE_PREFIX', 'app')];\n");
        $this->write('config/queue.php', "<?php\nreturn ['default' => env('QUEUE_CONNECTION', 'sync')];\n");
        $this->write('config/filesystems.php', "<?php\nreturn ['disks' => ['local' => ['driver' => 'local']]];\n");
        $this->write('.env', "DB_CONNECTION=sqlite\nQUEUE_CONNECTION=sync\nCACHE_DRIVER=file\nBROADCAST_DRIVER=log\n");
        $this->write('.env.example', "CACHE_DRIVER=file\nBROADCAST_DRIVER=log\n");
        $this->write('app/Jobs/ExampleJob.php', "<?php\nnamespace App\\Jobs;\nclass ExampleJob { public bool \$afterCommit = true; }\n");
        $this->write('app/Console/Kernel.php', "<?php\nnamespace App\\Console;\nclass Kernel {}\n");
        $this->write('resources/views/vendor/pagination/default.blade.php', '<nav />');
        $this->write('composer.lock', json_encode([
            'packages' => [
                ['name' => 'livewire/livewire', 'version' => 'v3.0.0'],
                ['name' => 'laravel/jetstream', 'version' => 'v5.0.0'],
                ['name' => 'inertiajs/inertia-laravel', 'version' => 'v2.0.0'],
                ['name' => 'filament/filament', 'version' => 'v3.0.0'],
                ['name' => 'laravel/nova', 'version' => 'v5.0.0'],
                ['name' => 'laravel/boost', 'version' => 'v2.0.0'],
            ],
            'packages-dev' => [],
        ], JSON_THROW_ON_ERROR));

        $collector = new FindingCollector;
        $advisor = new ProjectAdvisor($this->root.'/config', 13, static fn (): string => '2.0.0');
        $advisor->scan($collector);
        $findings = $collector->all();
        $ruleIds = array_map(static fn (Finding $finding): string => $finding->ruleId, $findings);

        foreach ([
            'laravelUpgrade.sqliteConnection', 'laravelUpgrade.databaseDrivers',
            'laravelUpgrade.sessionSerialization', 'laravelUpgrade.cachePrefix',
            'laravelUpgrade.queueDefaultSync', 'laravelUpgrade.afterCommitWithSyncQueue',
            'laravelUpgrade.localDiskDefaultRoot', 'laravelUpgrade.envKeyRenamed',
            'laravelUpgrade.livewireUpgradeGuide', 'laravelUpgrade.jetstreamUpgradeGuide',
            'laravelUpgrade.inertiaUpgradeGuide', 'laravelUpgrade.filamentUpgradeGuide',
            'laravelUpgrade.novaUpgradeGuide', 'laravelUpgrade.boostAvailability',
            'laravelUpgrade.skeletonFileRemoved', 'laravelUpgrade.paginationPublishedView',
            'laravelUpgrade.publishedVendorViews', 'laravelUpgrade.laravelInstaller',
        ] as $ruleId) {
            self::assertContains($ruleId, $ruleIds, $ruleId);
        }

        $livewireFindings = array_values(array_filter(
            $findings,
            static fn (Finding $finding): bool => $finding->ruleId === 'laravelUpgrade.livewireUpgradeGuide'
        ));
        self::assertCount(1, $livewireFindings);
        $livewire = $livewireFindings[0];
        self::assertSame('https://livewire.laravel.com/docs/upgrading', $livewire->guideUrl);
        self::assertSame(4, count(array_filter($ruleIds, static fn (string $id): bool => $id === 'laravelUpgrade.envKeyRenamed')));

        $removed = array_values(array_filter(
            $findings,
            static fn (Finding $finding): bool => $finding->ruleId === 'laravelUpgrade.skeletonFileRemoved'
                && $finding->file === 'app/Console/Kernel.php',
        ));
        self::assertCount(1, $removed);
        self::assertSame(11, $removed[0]->laravelVersion);

        self::assertCount(1, array_filter(
            $findings,
            static fn (Finding $finding): bool => $finding->ruleId === 'laravelUpgrade.publishedVendorViews',
        ));
        self::assertCount(1, array_filter(
            $findings,
            static fn (Finding $finding): bool => $finding->ruleId === 'laravelUpgrade.paginationPublishedView',
        ));
    }

    public function test_skips_absent_optional_surfaces_and_records_unchecked_installer(): void
    {
        $this->write('config/database.php', "<?php\nreturn ['default' => 'pgsql'];\n");
        $collector = new FindingCollector;
        (new ProjectAdvisor($this->root.'/config', 13))->scan($collector);
        $ruleIds = array_map(static fn (Finding $finding): string => $finding->ruleId, $collector->all());

        self::assertContains('laravelUpgrade.laravelInstallerCheck', $ruleIds);
        self::assertNotContains('laravelUpgrade.livewireUpgradeGuide', $ruleIds);
        self::assertNotContains('laravelUpgrade.publishedVendorViews', $ruleIds);
        self::assertNotContains('laravelUpgrade.boostAvailability', $ruleIds);
        self::assertNotContains('laravelUpgrade.skeletonFileRemoved', $ruleIds);
    }

    private function write(string $relative, string $contents): void
    {
        $path = $this->root.'/'.$relative;
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $contents);
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
