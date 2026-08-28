<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\PublishedViewChecker;
use PHPUnit\Framework\TestCase;

final class PublishedViewCheckerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/views-'.bin2hex(random_bytes(6));
        mkdir($this->tmpDir.'/resources/views/vendor/pagination', 0777, true);
        file_put_contents($this->tmpDir.'/resources/views/vendor/pagination/default.blade.php', '<nav />');
        file_put_contents($this->tmpDir.'/resources/views/vendor/pagination/simple-default.blade.php', '<nav />');
        mkdir($this->tmpDir.'/resources/views/vendor/telescope', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->tmpDir);
    }

    public function test_reports_published_views_and_laravel_13_pagination_names(): void
    {
        $collector = new FindingCollector;
        (new PublishedViewChecker)->scan($this->tmpDir, 13, $collector);

        self::assertSame(['default.blade.php', 'simple-default.blade.php'], (new PublishedViewChecker)->paginationRenames($this->tmpDir));
        self::assertCount(4, $collector->all());
        self::assertSame('laravelUpgrade.paginationPublishedView', $collector->all()[0]->ruleId);
        self::assertSame('laravelUpgrade.paginationPublishedView', $collector->all()[1]->ruleId);
        self::assertSame('laravelUpgrade.publishedVendorViews', $collector->all()[2]->ruleId);
    }

    public function test_reports_a_direct_published_vendor_entry(): void
    {
        file_put_contents($this->tmpDir.'/resources/views/vendor/custom.blade.php', '<div />');
        file_put_contents($this->tmpDir.'/resources/views/vendor/.hidden.blade.php', '<div />');
        $collector = new FindingCollector;

        (new PublishedViewChecker)->scan($this->tmpDir, 12, $collector);

        $findings = array_values(array_filter(
            $collector->all(),
            static fn ($finding): bool => $finding->ruleId === 'laravelUpgrade.publishedVendorViews'
                && $finding->file === 'resources/views/vendor/custom.blade.php',
        ));
        self::assertCount(1, $findings);

        $hidden = array_values(array_filter(
            $collector->all(),
            static fn ($finding): bool => $finding->file === 'resources/views/vendor/.hidden.blade.php',
        ));
        self::assertCount(1, $hidden);
    }

    public function test_pagination_rename_scan_ignores_symlinked_views(): void
    {
        $target = $this->tmpDir.'/real-default.blade.php';
        file_put_contents($target, '<nav />');
        unlink($this->tmpDir.'/resources/views/vendor/pagination/default.blade.php');
        self::assertTrue(symlink($target, $this->tmpDir.'/resources/views/vendor/pagination/default.blade.php'));

        self::assertSame(['simple-default.blade.php'], (new PublishedViewChecker)->paginationRenames($this->tmpDir));
    }
}
