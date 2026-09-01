<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console;

/** Result of detecting the installed Laravel framework major. */
final class ProjectVersionDetection
{
    public function __construct(
        public readonly ?int $major,
        public readonly string $source,
        public readonly ?string $warning = null,
        public readonly ?string $version = null,
    ) {}

    public function minor(): ?int
    {
        if ($this->version === null
            || preg_match('/(?:^|[^0-9])\d+\.(\d+)/', $this->version, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
