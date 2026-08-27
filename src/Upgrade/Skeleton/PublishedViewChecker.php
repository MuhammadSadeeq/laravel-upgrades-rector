<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;

/** Finds published vendor views which may shadow a target skeleton update. */
final class PublishedViewChecker
{
    public function scan(string $projectDirectory, int $targetMajor, FindingCollector $collector): void
    {
        $viewsDirectory = rtrim($projectDirectory, '/').'/resources/views/vendor';

        if (! is_dir($viewsDirectory)) {
            return;
        }

        $this->scanPagination($projectDirectory, $targetMajor, $collector);

        foreach (glob($viewsDirectory.'/*', GLOB_ONLYDIR) ?: [] as $vendorDirectory) {
            $relative = 'resources/views/vendor/'.basename($vendorDirectory);

            $collector->add(
                'laravelUpgrade.publishedVendorViews',
                Finding::SEVERITY_LOW,
                $targetMajor,
                $relative,
                0,
                sprintf('Published vendor views found under "%s".', $relative),
                'Republish or diff these views against the target package after upgrading.'
            );
        }
    }

    /**
     * @return list<string>
     */
    public function paginationRenames(string $projectDirectory): array
    {
        $paginationDirectory = rtrim($projectDirectory, '/').'/resources/views/vendor/pagination';
        $renames = [];

        foreach (['default', 'simple-default'] as $name) {
            $path = $paginationDirectory.'/'.$name.'.blade.php';

            if (is_file($path)) {
                $renames[] = $name.'.blade.php';
            }
        }

        return $renames;
    }

    private function scanPagination(string $projectDirectory, int $targetMajor, FindingCollector $collector): void
    {
        if ($targetMajor < 13) {
            return;
        }

        foreach ($this->paginationRenames($projectDirectory) as $file) {
            $collector->add(
                'laravelUpgrade.paginationPublishedView',
                Finding::SEVERITY_MEDIUM,
                $targetMajor,
                'resources/views/vendor/pagination/'.$file,
                1,
                sprintf('Laravel %d no longer ships the published pagination view "%s".', $targetMajor, $file),
                'Rename it to bootstrap-3.blade.php (or republish the target pagination views) after reviewing customizations.'
            );
        }
    }
}
