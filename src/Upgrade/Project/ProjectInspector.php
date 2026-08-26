<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Project;

use Symfony\Component\Process\Process;

/**
 * Inspects the project being upgraded: Laravel version, PHP version,
 * git state and installed packages (plan P4-02).
 */
final class ProjectInspector
{
    /**
     * @param  array<string, mixed>  $manifest  decoded composer.json
     */
    public function __construct(
        private readonly string $workingDirectory,
        private readonly array $manifest,
    ) {}

    /**
     * Detects the current Laravel major from composer.json constraint.
     */
    public function currentLaravelMajor(): ?int
    {
        $constraint = (is_array($this->manifest['require'] ?? null) ? ($this->manifest['require']['laravel/framework'] ?? null) : null);

        if (! is_string($constraint)) {
            return null;
        }

        // Extract major from constraints like "^11.0", "^11.31", ">=10.0 <12.0".
        if (preg_match('/[\^~>]*\s*(\d+)\./', $constraint, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * PHP CLI version as a comparable integer (e.g. 804 for 8.4).
     */
    public function phpVersionId(): int
    {
        return PHP_VERSION_ID;
    }

    /**
     * Whether the project is inside a git repository.
     */
    public function isGitRepository(): bool
    {
        return is_dir($this->workingDirectory.'/.git');
    }

    /**
     * Whether git reports a clean working tree.
     */
    public function isGitClean(): bool
    {
        if (! $this->isGitRepository()) {
            return false;
        }

        $process = new Process(['git', 'status', '--porcelain'], $this->workingDirectory);
        $process->run();

        return trim($process->getOutput()) === '';
    }

    /**
     * Current branch name.
     */
    public function gitBranch(): string
    {
        if (! $this->isGitRepository()) {
            return '';
        }

        $process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $this->workingDirectory);
        $process->run();

        return trim($process->getOutput());
    }

    /**
     * Composer binary version string.
     */
    public function composerVersion(): string
    {
        $process = new Process(['composer', '--version']);
        $process->run();
        preg_match('/(\d+\.\d+\.\d+)/', $process->getOutput(), $m);

        return $m[1] ?? '?';
    }

    /**
     * @return list<string>
     */
    public function existingProjectPaths(): array
    {
        $paths = [];

        foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'tests'] as $relative) {
            $candidate = $this->workingDirectory.'/'.$relative;

            if (is_dir($candidate)) {
                $paths[] = $candidate;
            }
        }

        return $paths;
    }
}
