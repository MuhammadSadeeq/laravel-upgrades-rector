<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

/** Typed package guide entry loaded from package-guides.json. */
final class PackageGuide
{
    /**
     * @param  array<int, PackageGuideMajor>  $majors
     */
    public function __construct(
        public readonly string $package,
        public readonly string $guideUrl,
        public readonly array $majors,
    ) {}

    public function forMajor(int $major): ?PackageGuideMajor
    {
        return $this->majors[$major] ?? null;
    }
}
