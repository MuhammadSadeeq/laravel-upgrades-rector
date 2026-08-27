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
    ) {}
}
