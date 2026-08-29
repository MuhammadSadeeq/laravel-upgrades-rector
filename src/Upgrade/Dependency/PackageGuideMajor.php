<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

/** The guidance attached to one package major. */
final class PackageGuideMajor
{
    /**
     * @param  list<PackageGuideItem>  $items
     */
    public function __construct(
        public readonly int $major,
        public readonly string $guideUrl,
        public readonly array $items,
        public readonly ?PackageGuideCounter $counter = null,
        public readonly string $status = 'supported',
        public readonly ?string $notes = null,
    ) {}

    public function isFuture(): bool
    {
        return $this->status === 'future';
    }
}
