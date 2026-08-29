<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;

/** Result of one read-only package-major crossing analysis. */
final class PackageGuideAnalysis
{
    /**
     * @param  list<Finding>  $findings
     * @param  list<array<string, mixed>>  $guides
     */
    public function __construct(
        public readonly array $findings,
        public readonly array $guides,
    ) {}
}
