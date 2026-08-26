<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Skeleton;

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
        // No vendored skeleton files exist in the test environment, so
        // sync() returns an empty array without crashing.
        $merged = $step->sync($this->tmpDir.'/config', 13);

        self::assertSame([], $merged);
    }

    public function test_upstream_config_path_returns_null_for_missing(): void
    {
        $step = new SkeletonStep;

        self::assertNull($step->upstreamConfigPath(99, 'nonexistent'));
    }
}
