<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

/** One actionable item in a package-major upgrade guide. */
final class PackageGuideItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $severity,
        public readonly string $message,
        public readonly string $action,
        public readonly ?string $guideUrl = null,
    ) {}
}
