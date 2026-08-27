<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\SkeletonRepository;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\SkeletonStep;
use PHPUnit\Framework\TestCase;

final class SkeletonStepTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/skel-'.uniqid();
        mkdir($this->tmpDir.'/config', 0777, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $fileInfo->isDir() ? @rmdir($fileInfo->getPathname()) : @unlink($fileInfo->getPathname());
        }
        @rmdir($this->tmpDir);
    }

    public function test_sync_merges_missing_keys(): void
    {
        file_put_contents($this->tmpDir.'/config/cache.php',
            "<?php\n\nreturn [\n    'default' => 'file',\n];\n");

        $step = new SkeletonStep;
        // The 13 skeleton has serializable_classes in cache.php which is
        // missing from the project config, so it gets merged.
        $merged = $step->sync($this->tmpDir.'/config', 13);

        self::assertSame(['cache.php'], $merged);
    }

    public function test_upstream_config_path_returns_null_for_missing(): void
    {
        $step = new SkeletonStep;

        self::assertNull($step->upstreamConfigPath(99, 'nonexistent'));
    }

    public function test_partial_snapshots_only_reconcile_config_and_never_infer_file_changes(): void
    {
        $root = $this->tmpDir.'/snapshots';
        mkdir($root.'/10', 0777, true);
        mkdir($root.'/11', 0777, true);
        file_put_contents($root.'/MANIFEST.json', json_encode([
            '10' => ['complete' => false],
            '11' => ['complete' => false],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($root.'/10/old.txt', 'old');
        file_put_contents($root.'/11/new.txt', 'new');
        file_put_contents($this->tmpDir.'/old.txt', 'project old');
        file_put_contents($this->tmpDir.'/.env.example', "OLD=value\n");
        file_put_contents($this->tmpDir.'/.env', "OLD=secret\n");
        $collector = new FindingCollector;

        $result = (new SkeletonStep(new SkeletonRepository($root)))->syncProject(
            $this->tmpDir,
            10,
            11,
            $collector
        );

        self::assertSame([], $result['added']);
        self::assertSame([], $result['removed']);
        self::assertSame([], $result['modified']);
        self::assertSame([], $result['renamed']);
        self::assertFileExists($this->tmpDir.'/old.txt');
        self::assertFileDoesNotExist($this->tmpDir.'/new.txt');
        self::assertSame(1, $collector->count());
        self::assertSame('laravelUpgrade.skeletonSyncSkipped', $collector->all()[0]->ruleId);
        self::assertSame("OLD=secret\n", file_get_contents($this->tmpDir.'/.env'));
    }

    public function test_complete_snapshots_merge_env_and_non_php_files_without_writing_env(): void
    {
        $root = $this->tmpDir.'/complete-snapshots';
        mkdir($root.'/12/config', 0777, true);
        mkdir($root.'/13/config', 0777, true);
        file_put_contents($root.'/MANIFEST.json', json_encode([
            '12' => ['complete' => true],
            '13' => ['complete' => true],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($root.'/12/config/session.php', "<?php\nreturn [];\n");
        file_put_contents($root.'/13/config/session.php', "<?php\nreturn ['serialization' => 'json'];\n");
        file_put_contents($root.'/12/.env.example', "APP_NAME=Laravel\n");
        file_put_contents($root.'/13/.env.example', "APP_NAME=Laravel\nNEW_KEY=target\n");
        file_put_contents($root.'/12/.gitignore', "base\n");
        file_put_contents($root.'/13/.gitignore', "target\n");
        file_put_contents($this->tmpDir.'/config/session.php', "<?php\nreturn [];\n");
        file_put_contents($this->tmpDir.'/.env.example', "APP_NAME=Laravel\n");
        file_put_contents($this->tmpDir.'/.env', "APP_KEY=secret\n");
        file_put_contents($this->tmpDir.'/.gitignore', "base\n");
        $collector = new FindingCollector;

        $result = (new SkeletonStep(new SkeletonRepository($root)))->syncProject(
            $this->tmpDir,
            12,
            13,
            $collector
        );

        self::assertContains('config/session.php', $result['changed']);
        self::assertContains('.env.example', $result['changed']);
        self::assertContains('.gitignore', $result['changed']);
        $session = file_get_contents($this->tmpDir.'/config/session.php');
        $envExample = file_get_contents($this->tmpDir.'/.env.example');
        self::assertIsString($session);
        self::assertIsString($envExample);
        self::assertStringContainsString("'serialization' => 'php'", $session);
        self::assertStringContainsString('NEW_KEY=target', $envExample);
        self::assertSame("APP_KEY=secret\n", file_get_contents($this->tmpDir.'/.env'));
        self::assertSame("target\n", file_get_contents($this->tmpDir.'/.gitignore'));
        self::assertSame([], $result['conflicts']);
    }
}
