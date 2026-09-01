<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Project;

/** One direct Composer dependency and the locally installed version, if known. */
final class ProjectDependency
{
    public function __construct(
        public readonly string $name,
        public readonly string $section,
        public readonly string $constraint,
        public readonly ?string $installedVersion = null,
        public readonly ?string $installedSource = null,
    ) {}
}
