<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Project;

use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\ProjectVersionDetection;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\ProjectVersionDetector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\SymfonyProcessRunner;

/**
 * Inspects the project being upgraded: Laravel version, PHP version,
 * git state, dependencies and configuration (plan P4-02).
 *
 * Project PHP files are intentionally treated as text here. Reading a config
 * file must not execute application code or trigger Composer side effects.
 */
final class ProjectInspector
{
    private ?ProjectVersionDetection $versionDetection = null;

    /** @var array<string, array{version: ?string, source: string}>|null */
    private ?array $packageMetadata = null;

    private readonly ProjectConfigReader $configReader;

    /**
     * @param  array<string, mixed>  $manifest  decoded composer.json
     */
    public function __construct(
        private readonly string $workingDirectory,
        private readonly array $manifest,
        private readonly ProjectVersionDetector $versionDetector = new ProjectVersionDetector,
        private readonly ProcessRunner $processRunner = new SymfonyProcessRunner,
        ?ProjectConfigReader $configReader = null,
    ) {
        $this->configReader = $configReader ?? new ProjectConfigReader;
    }

    /**
     * Detects the current Laravel major using installed metadata, then the
     * lockfile, then the manifest constraint. The latter two carry a warning.
     */
    public function currentLaravelMajor(): ?int
    {
        return $this->laravelVersion()->major;
    }

    /** Current Laravel minor when local metadata contains a concrete version. */
    public function currentLaravelMinor(): ?int
    {
        return $this->laravelVersion()->minor();
    }

    /** @return ProjectVersionDetection The detection source and fallback warning. */
    public function laravelVersion(): ProjectVersionDetection
    {
        if ($this->versionDetection !== null) {
            return $this->versionDetection;
        }

        $detection = $this->versionDetector->detect($this->workingDirectory);

        if ($detection->major !== null || $this->manifestLaravelConstraint() === null) {
            return $this->versionDetection = $detection;
        }

        // Keep the constructor's historical manifest-only use case working
        // when callers provide decoded data without a composer.json on disk.
        $constraint = $this->manifestLaravelConstraint();
        preg_match('/(?:^|[^0-9])v?(\d+)(?:\.(\d+))?/', $constraint, $matches);
        $major = isset($matches[1]) ? (int) $matches[1] : null;

        if ($major === null || $major < 1) {
            return $this->versionDetection = $detection;
        }

        $version = $major.'.'.($matches[2] ?? '0').'.0';

        return $this->versionDetection = new ProjectVersionDetection(
            $major,
            'composer.json',
            'Laravel '.$major.' was inferred from the supplied composer.json manifest.',
            $version,
        );
    }

    /** Return an immutable snapshot of all P4-02 facts. */
    public function inspect(): ProjectInspection
    {
        $version = $this->laravelVersion();
        $databaseConfig = $this->readProjectFile('config/database.php');
        $queueConfig = $this->readProjectFile('config/queue.php');
        $sessionConfig = $this->readProjectFile('config/session.php');
        $environment = $this->environmentValues($this->readProjectFile('.env'));
        $database = $this->configReader->database($databaseConfig, $environment);
        $session = $this->configReader->session($sessionConfig, $environment);

        return new ProjectInspection(
            $version->major,
            $version->minor(),
            $version->version,
            $version->source,
            $version->warning,
            $this->phpVersionId(),
            $this->composerVersion(),
            $this->isGitRepository(),
            $this->isGitClean(),
            $this->gitBranch(),
            $this->directDependencies(),
            $this->uniqueLowercaseStrings($database['drivers']),
            $this->lowercase($database['default']),
            $this->lowercase($this->configReader->queueDefault($queueConfig, $environment)),
            $this->lowercase($session['driver']),
            $this->lowercase($session['serialization']),
            $this->hasInstalledPackage('laravel/pint') || is_file($this->projectPath('vendor/bin/pint')),
            $this->hasInstalledPackage('larastan/larastan')
                || is_file($this->projectPath('vendor/larastan/larastan/extension.neon')),
            $this->projectType(),
        );
    }

    /** Alias for callers that prefer snapshot terminology. */
    public function snapshot(): ProjectInspection
    {
        return $this->inspect();
    }

    /**
     * @return list<ProjectDependency>
     */
    public function directDependencies(): array
    {
        $dependencies = [];

        foreach (['require' => 'require', 'require-dev' => 'require-dev'] as $manifestKey => $section) {
            $entries = $this->manifest[$manifestKey] ?? null;

            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $name => $constraint) {
                if (! is_string($name) || ! is_string($constraint)) {
                    continue;
                }

                $installed = $this->packageMetadata()[$name] ?? null;
                $dependencies[] = new ProjectDependency(
                    $name,
                    $section,
                    $constraint,
                    $installed['version'] ?? null,
                    $installed['source'] ?? null,
                );
            }
        }

        return $dependencies;
    }

    /**
     * @return list<ProjectDependency>
     */
    public function installedDirectPackages(): array
    {
        return $this->directDependencies();
    }

    /** PHP CLI version as a comparable integer (e.g. 804 for 8.4). */
    public function phpVersionId(): int
    {
        return PHP_VERSION_ID;
    }

    /** Whether the project is inside a git repository. */
    public function isGitRepository(): bool
    {
        return is_dir($this->workingDirectory.'/.git') || is_file($this->workingDirectory.'/.git');
    }

    /** Whether git reports a clean working tree. */
    public function isGitClean(): bool
    {
        if (! $this->isGitRepository()) {
            return false;
        }

        try {
            $result = $this->processRunner->run(new ProcessRequest(
                ['git', 'status', '--porcelain'],
                $this->workingDirectory,
                5.0,
            ));
        } catch (\Throwable) {
            return false;
        }

        return $result->isSuccessful() && trim($result->output) === '';
    }

    /** Current branch name. */
    public function gitBranch(): string
    {
        if (! $this->isGitRepository()) {
            return '';
        }

        try {
            $result = $this->processRunner->run(new ProcessRequest(
                ['git', 'rev-parse', '--abbrev-ref', 'HEAD'],
                $this->workingDirectory,
                5.0,
            ));
        } catch (\Throwable) {
            return '';
        }

        return $result->isSuccessful() ? trim($result->output) : '';
    }

    /** Composer binary version string. */
    public function composerVersion(): string
    {
        try {
            $result = $this->processRunner->run(new ProcessRequest(
                ['composer', '--version'],
                $this->workingDirectory,
                5.0,
            ));
        } catch (\Throwable) {
            return '?';
        }

        if (! $result->isSuccessful()) {
            return '?';
        }

        preg_match('/(\d+\.\d+\.\d+)/', $result->combinedOutput(), $matches);

        return $matches[1] ?? '?';
    }

    /** @return list<string> */
    public function existingProjectPaths(): array
    {
        $paths = [];

        foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'tests'] as $relative) {
            $candidate = rtrim($this->workingDirectory, '/\\').'/'.$relative;

            if (is_dir($candidate)) {
                $paths[] = $candidate;
            }
        }

        return $paths;
    }

    private function manifestLaravelConstraint(): ?string
    {
        $require = $this->manifest['require'] ?? null;
        $constraint = is_array($require) ? ($require['laravel/framework'] ?? null) : null;

        return is_string($constraint) ? $constraint : null;
    }

    /** @return array<string, array{version: ?string, source: string}> */
    private function packageMetadata(): array
    {
        if ($this->packageMetadata !== null) {
            return $this->packageMetadata;
        }

        $metadata = [];
        $directory = rtrim($this->workingDirectory, '/\\');

        foreach ([
            [$directory.'/vendor/composer/installed.json', 'installed'],
            [$directory.'/composer.lock', 'lock'],
        ] as [$path, $source]) {
            $document = $this->readJsonFile($path);

            if ($document === null) {
                continue;
            }

            $entries = [];

            foreach (['packages', 'packages-dev'] as $key) {
                if (is_array($document[$key] ?? null)) {
                    $entries = array_merge($entries, $document[$key]);
                }
            }

            if ($entries === []) {
                $entries = $document;
            }

            foreach ($entries as $package) {
                if (! is_array($package) || ! is_string($package['name'] ?? null)) {
                    continue;
                }

                $name = $package['name'];

                if (array_key_exists($name, $metadata)) {
                    continue;
                }

                $version = null;

                foreach (['pretty_version', 'version'] as $versionKey) {
                    if (is_string($package[$versionKey] ?? null)) {
                        $version = $package[$versionKey];

                        break;
                    }
                }

                $metadata[$name] = [
                    'version' => $version,
                    'source' => $source,
                ];
            }
        }

        return $this->packageMetadata = $metadata;
    }

    private function hasInstalledPackage(string $name): bool
    {
        return ($this->packageMetadata()[$name]['source'] ?? null) === 'installed';
    }

    private function projectPath(string $relative): string
    {
        return rtrim($this->workingDirectory, '/\\').'/'.$relative;
    }

    /** @return array<int|string, mixed>|null */
    private function readJsonFile(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function readProjectFile(string $relative): ?string
    {
        $path = rtrim($this->workingDirectory, '/\\').'/'.$relative;

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : null;
    }

    /** @return array<string, string> */
    private function environmentValues(?string $contents): array
    {
        if ($contents === null) {
            return [];
        }

        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            $value = trim($matches[2]);

            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                $end = strrpos($value, $quote);
                $value = $end !== false && $end > 0 ? substr($value, 1, $end - 1) : '';
            } elseif (($comment = strpos($value, ' #')) !== false) {
                $value = trim(substr($value, 0, $comment));
            }

            $values[$matches[1]] = $value;
        }

        return $values;
    }

    private function lowercase(?string $value): ?string
    {
        return $value === null ? null : strtolower(trim($value));
    }

    /** @param list<string> $values
     * @return list<string>
     */
    private function uniqueLowercaseStrings(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $value = strtolower(trim($value));

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function projectType(): string
    {
        return ($this->manifest['type'] ?? null) === 'library'
            ? ProjectInspection::TYPE_LIBRARY
            : ProjectInspection::TYPE_APP;
    }
}
