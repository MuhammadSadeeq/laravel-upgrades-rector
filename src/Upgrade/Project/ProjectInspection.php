<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Project;

/** Immutable read-only snapshot of the project facts needed by the CLI. */
final class ProjectInspection
{
    public const TYPE_APP = 'app';

    public const TYPE_LIBRARY = 'library';

    /**
     * @param  list<ProjectDependency>  $directDependencies
     * @param  list<string>  $databaseDrivers
     */
    public function __construct(
        public readonly ?int $laravelMajor,
        public readonly ?int $laravelMinor,
        public readonly ?string $laravelVersion,
        public readonly string $laravelVersionSource,
        public readonly ?string $laravelVersionWarning,
        public readonly int $phpVersionId,
        public readonly string $composerVersion,
        public readonly bool $gitRepository,
        public readonly bool $gitClean,
        public readonly string $gitBranch,
        public readonly array $directDependencies,
        public readonly array $databaseDrivers,
        public readonly ?string $databaseDefault,
        public readonly ?string $queueDefault,
        public readonly ?string $sessionDriver,
        public readonly ?string $sessionSerialization,
        public readonly bool $pintPresent,
        public readonly bool $larastanPresent,
        public readonly string $projectType,
    ) {}
}
