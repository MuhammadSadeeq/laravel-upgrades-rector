<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\BinaryResolver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use RuntimeException;
use Throwable;

/** Runs data-driven post-install commands after the code and advisory steps. */
final class PostStep implements StepInterface
{
    private readonly string $postInstallPath;

    public function __construct(
        private readonly ProcessRunner $processRunner,
        ?string $postInstallPath = null,
        private readonly ?BinaryResolver $binaryResolver = null,
    ) {
        $this->postInstallPath = $postInstallPath
            ?? dirname(__DIR__, 3).'/resources/compat/post-install-steps.json';
    }

    public function name(): string
    {
        return 'post';
    }

    public function execute(UpgradeContext $context): StepResult
    {
        try {
            $steps = $this->readSteps($context->toMajor());
        } catch (Throwable $exception) {
            return StepResult::failed(
                message: 'Post-install step configuration is invalid: '.$exception->getMessage(),
                data: ['check' => 'post-install-data', 'path' => $this->postInstallPath],
                exitCode: 1,
            );
        }

        $installedPackages = $this->installedPackages($context);
        $migrationDirectory = $this->migrationDirectory($context->workingDirectory);
        $artisan = $this->artisanPath($context->workingDirectory);
        $commands = [];
        $collector = new FindingCollector;

        try {
            foreach ($steps['migration'] as $migration) {
                $package = $migration['package'];
                $id = $migration['id'];
                $base = [
                    'id' => $id,
                    'package' => $package,
                    'tag' => $migration['tag'],
                    'command' => [$this->binaryResolver()->phpBinary(), 'artisan', 'vendor:publish', '--tag='.$migration['tag']],
                ];

                if (! isset($installedPackages[$package])) {
                    $commands[] = $base + ['status' => 'skipped', 'reason' => 'package-not-installed'];

                    continue;
                }

                if ($artisan === null) {
                    $commands[] = $base + ['status' => 'skipped', 'reason' => 'artisan-not-found'];

                    continue;
                }

                $ignoreMigrationsRemoved = $this->ignoreMigrationsRemoved($context, $package);
                $migrationState = $this->migrationExists($migration['migrationMarkers'], $migrationDirectory);

                if ($migrationState === null && ! $ignoreMigrationsRemoved) {
                    $commands[] = $base + ['status' => 'skipped', 'reason' => 'migration-check-unavailable'];

                    continue;
                }

                if ($migrationState === true && ! $ignoreMigrationsRemoved) {
                    $commands[] = $base + ['status' => 'skipped', 'reason' => 'migration-already-published'];

                    continue;
                }

                $commands[] = $this->runOrPreview(
                    context: $context,
                    command: $base['command'],
                    id: $id,
                    file: 'database/migrations',
                    metadata: $base + ['artisan' => $artisan, 'ignoreMigrationsRemoved' => $ignoreMigrationsRemoved],
                    collector: $collector,
                );
            }

            foreach ($steps['command'] as $commandDefinition) {
                $command = $this->resolveCommand($commandDefinition['argv'], $context);
                $id = $commandDefinition['id'];
                $isArtisan = $commandDefinition['argv'][0] === 'php';
                $base = [
                    'id' => $id,
                    'command' => $command,
                    'type' => $isArtisan ? 'artisan' : 'composer',
                ];

                if ($isArtisan && $artisan === null) {
                    $commands[] = $base + ['status' => 'skipped', 'reason' => 'artisan-not-found'];

                    continue;
                }

                $commands[] = $this->runOrPreview(
                    context: $context,
                    command: $command,
                    id: $id,
                    file: $isArtisan ? 'artisan' : 'composer.json',
                    metadata: $base,
                    collector: $collector,
                );
            }
        } catch (Throwable $exception) {
            $findings = array_map(
                static fn (Finding $finding): array => $finding->toArray(),
                $collector->all(),
            );
            $data = [
                'commands' => $commands,
                'installedPackages' => array_keys($installedPackages),
                'findings' => $findings,
                'migrationDirectory' => $migrationDirectory,
                'caveats' => $steps['caveats'],
                'check' => 'process',
            ];

            if (! $context->isPlanMode()) {
                try {
                    $this->persistFindings($context, $collector, $data);
                    $data['findingsJsonl'] = $context->workingDirectory.'/.laravel-upgrade/findings.jsonl';
                } catch (Throwable $persistenceException) {
                    $data['persistenceError'] = $persistenceException->getMessage();
                }
            }

            return StepResult::failed(
                message: 'Post-install command execution failed: '.$exception->getMessage(),
                findingsCount: count($findings),
                data: $data,
                exitCode: 1,
            );
        }

        $findings = array_map(
            static fn (Finding $finding): array => $finding->toArray(),
            $collector->all(),
        );
        $data = [
            'commands' => $commands,
            'installedPackages' => array_keys($installedPackages),
            'findings' => $findings,
            'migrationDirectory' => $migrationDirectory,
            'caveats' => $steps['caveats'],
        ];

        if ($context->isPlanMode()) {
            return StepResult::successful(
                findingsCount: 0,
                message: 'Post-install commands previewed; no commands were run.',
                data: $data,
            );
        }

        try {
            $this->persistFindings($context, $collector, $data);
        } catch (Throwable $exception) {
            return StepResult::failed(
                message: 'Post-install findings could not be persisted: '.$exception->getMessage(),
                findingsCount: count($findings),
                data: $data + ['check' => 'findings'],
                exitCode: 1,
            );
        }

        $data['findingsJsonl'] = $context->workingDirectory.'/.laravel-upgrade/findings.jsonl';
        $data['findingPersistence'] = 'applied';

        return StepResult::successful(
            findingsCount: count($findings),
            message: $findings === []
                ? 'Post-install commands completed.'
                : sprintf('Post-install commands completed with %d command finding(s).', count($findings)),
            data: $data,
        );
    }

    /**
     * @return array{migration: list<array{id: string, package: string, tag: string, migrationMarkers: list<string>}>, command: list<array{id: string, argv: list<string>}>, caveats: list<string>}
     */
    private function readSteps(int $targetMajor): array
    {
        if (! is_file($this->postInstallPath)) {
            throw new RuntimeException('The post-install data file does not exist.');
        }

        $contents = file_get_contents($this->postInstallPath);

        if ($contents === false) {
            throw new RuntimeException('The post-install data file could not be read.');
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The post-install data file is not valid JSON.', previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('The post-install data file must contain an object.');
        }

        foreach ($decoded as $major => $entries) {
            $majorKey = sprintf('%s', $major);

            if ($majorKey === '*' || $majorKey === 'caveats') {
                continue;
            }

            if (preg_match('/^\d+$/', $majorKey) !== 1) {
                throw new RuntimeException('Post-install data contains an unsupported top-level key.');
            }

            $this->migrationDefinitions($entries);
        }

        $migrations = $this->migrationDefinitions($decoded[(string) $targetMajor] ?? []);
        $commands = $this->commandDefinitions($decoded['*'] ?? null);
        $caveats = $decoded['caveats'] ?? [];

        if (! is_array($caveats)) {
            throw new RuntimeException('Post-install caveats must be a list of non-empty strings.');
        }

        $validCaveats = [];

        foreach ($caveats as $caveat) {
            if (! is_string($caveat) || trim($caveat) === '') {
                throw new RuntimeException('Post-install caveats must be a list of non-empty strings.');
            }

            $validCaveats[] = $caveat;
        }

        return ['migration' => $migrations, 'command' => $commands, 'caveats' => $validCaveats];
    }

    /**
     * @return list<array{id: string, package: string, tag: string, migrationMarkers: list<string>}>
     */
    private function migrationDefinitions(mixed $entries): array
    {
        if (! is_array($entries)) {
            throw new RuntimeException('Migration post-install steps must be a list.');
        }

        $definitions = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)
                || ($entry['type'] ?? null) !== 'migration'
                || ! is_string($entry['id'] ?? null)
                || trim($entry['id']) === ''
                || preg_match('/^[a-z0-9_.-]+$/i', $entry['id']) !== 1
                || ! is_string($entry['package'] ?? null)
                || preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/i', $entry['package']) !== 1
                || ! is_string($entry['tag'] ?? null)
                || trim($entry['tag']) === ''
                || preg_match('/^[a-z0-9_.:-]+$/i', $entry['tag']) !== 1) {
                throw new RuntimeException('Every migration post-install step needs a valid id, package, and tag.');
            }

            $markers = $entry['migrationMarkers'] ?? [];

            if (! is_array($markers) || $markers === []) {
                throw new RuntimeException(sprintf('Migration step "%s" needs migration markers.', $entry['id']));
            }

            $validMarkers = [];

            foreach ($markers as $marker) {
                if (! is_string($marker) || $marker === '' || str_contains($marker, '..') || str_contains($marker, '/') || str_contains($marker, '\\')) {
                    throw new RuntimeException(sprintf('Migration step "%s" has an unsafe migration marker.', $entry['id']));
                }

                $validMarkers[] = $marker;
            }

            $definitions[] = [
                'id' => trim($entry['id']),
                'package' => strtolower($entry['package']),
                'tag' => trim($entry['tag']),
                'migrationMarkers' => array_values(array_unique($validMarkers)),
            ];
        }

        return $definitions;
    }

    /**
     * @return list<array{id: string, argv: list<string>}>
     */
    private function commandDefinitions(mixed $entries): array
    {
        if (! is_array($entries)) {
            throw new RuntimeException('Common post-install steps must be a list.');
        }

        $definitions = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)
                || ($entry['type'] ?? null) !== 'command'
                || ! is_string($entry['id'] ?? null)
                || trim($entry['id']) === ''
                || preg_match('/^[a-z0-9_.-]+$/i', $entry['id']) !== 1
                || ! is_array($entry['argv'] ?? null)
                || $entry['argv'] === []) {
                throw new RuntimeException('Every common post-install step needs an id and argv.');
            }

            $argv = [];

            foreach ($entry['argv'] as $argument) {
                if (! is_string($argument) || $argument === '' || str_contains($argument, "\0")) {
                    throw new RuntimeException(sprintf('Post-install command "%s" has an invalid argv item.', $entry['id']));
                }

                $argv[] = $argument;
            }

            if (! in_array($argv[0], ['composer', 'php'], true)) {
                throw new RuntimeException(sprintf('Post-install command "%s" has an unsupported executable.', $entry['id']));
            }

            if (($argv[0] === 'php' && ($argv[1] ?? null) !== 'artisan')
                || ($argv[0] === 'composer' && ($argv[1] ?? null) !== 'dump-autoload')) {
                throw new RuntimeException(sprintf('Post-install command "%s" has an unsupported target.', $entry['id']));
            }

            $definitions[] = ['id' => trim($entry['id']), 'argv' => $argv];
        }

        return $definitions;
    }

    /**
     * @param  list<string>  $argv
     * @return list<string>
     */
    private function resolveCommand(array $argv, UpgradeContext $context): array
    {
        if ($argv[0] === 'composer') {
            $argv[0] = $this->binaryResolver()->composerBinary($this->stringOption($context->option('composerBinary')));
        } else {
            $argv[0] = $this->binaryResolver()->phpBinary();
        }

        return $argv;
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function runOrPreview(
        UpgradeContext $context,
        array $command,
        string $id,
        string $file,
        array $metadata,
        FindingCollector $collector,
    ): array {
        if ($context->isPlanMode()) {
            return $metadata + ['status' => 'preview'];
        }

        try {
            $result = $this->processRunner->run(new ProcessRequest($command, $context->workingDirectory, 300.0));
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf('Could not launch post-install command "%s": %s', $id, $exception->getMessage()), previous: $exception);
        }

        $data = $metadata + [
            'status' => $result->isSuccessful() ? 'success' : 'failed',
            'exitCode' => $result->exitCode,
            'output' => $result->combinedOutput(),
        ];

        if (! $result->isSuccessful()) {
            $collector->add(
                ruleId: 'laravelUpgrade.post.'.$id,
                severity: Finding::SEVERITY_MEDIUM,
                laravelVersion: $context->toMajor(),
                file: $file,
                line: 0,
                message: sprintf('Post-install command "%s" failed with exit code %d.', $id, $result->exitCode),
                action: $result->combinedOutput() !== ''
                    ? 'Review the command output: '.$result->combinedOutput()
                    : 'Run the command manually after resolving the post-install failure.',
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistFindings(UpgradeContext $context, FindingCollector $collector, array &$data): void
    {
        $directory = $context->workingDirectory.'/.laravel-upgrade';

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the .laravel-upgrade directory.');
        }

        $path = $directory.'/findings.jsonl';
        $collector->writeJsonl($path);
    }

    /** @return array<string, true> */
    private function installedPackages(UpgradeContext $context): array
    {
        $configured = $context->option('installedPackages');

        if (is_array($configured)) {
            return $this->packageNames($configured);
        }

        foreach ([$context->workingDirectory.'/composer.lock', $context->workingDirectory.'/vendor/composer/installed.json'] as $path) {
            $packages = $this->readPackageFile($path);

            if ($packages !== []) {
                return $packages;
            }
        }

        return [];
    }

    /** @return array<string, true> */
    private function readPackageFile(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $entries = [];

        foreach (['packages', 'packages-dev'] as $section) {
            if (is_array($decoded[$section] ?? null)) {
                $entries = array_merge($entries, $decoded[$section]);
            }
        }

        return $this->packageNames($entries);
    }

    /**
     * @param  array<mixed, mixed>  $entries
     * @return array<string, true>
     */
    private function packageNames(array $entries): array
    {
        /** @var array<string, bool> $names */
        $names = [];

        foreach ($entries as $key => $entry) {
            if (is_string($entry)) {
                $names[strtolower($entry)] = true;

                continue;
            }

            if (is_string($key) && is_bool($entry)) {
                $names[strtolower($key)] = $entry;

                continue;
            }

            if (is_array($entry) && is_string($entry['name'] ?? null)) {
                $names[strtolower($entry['name'])] = true;
            }
        }

        $installed = [];

        foreach ($names as $name => $isInstalled) {
            if ($isInstalled) {
                $installed[$name] = true;
            }
        }

        return $installed;
    }

    private function artisanPath(string $workingDirectory): ?string
    {
        $project = realpath($workingDirectory);

        if ($project === false) {
            return null;
        }

        $artisan = realpath($project.'/artisan');

        if ($artisan === false || ! is_file($artisan) || ! $this->withinProject($artisan, $project)) {
            return null;
        }

        return str_replace('\\', '/', $artisan);
    }

    private function migrationDirectory(string $workingDirectory): ?string
    {
        $project = realpath($workingDirectory);

        if ($project === false) {
            return null;
        }

        $directory = realpath($project.'/database/migrations');

        if ($directory === false) {
            return is_dir($project.'/database/migrations') ? null : str_replace('\\', '/', $project.'/database/migrations');
        }

        return $this->withinProject($directory, $project) ? str_replace('\\', '/', $directory) : null;
    }

    /**
     * @param  list<string>  $markers
     */
    private function migrationExists(array $markers, ?string $migrationDirectory): ?bool
    {
        if ($migrationDirectory === null || ! is_dir($migrationDirectory)) {
            return $migrationDirectory === null ? null : false;
        }

        foreach ($markers as $marker) {
            $matches = glob($migrationDirectory.'/'.$marker, GLOB_NOSORT);

            if ($matches !== false && $matches !== []) {
                return true;
            }
        }

        return false;
    }

    private function ignoreMigrationsRemoved(UpgradeContext $context, string $package): bool
    {
        foreach (['ignoreMigrationsRemoved', 'removedIgnoreMigrations'] as $optionName) {
            $value = $context->option($optionName);

            if ($value === true) {
                return true;
            }

            if (is_string($value) && strtolower($value) === strtolower($package)) {
                return true;
            }

            if (! is_array($value)) {
                continue;
            }

            foreach ($value as $key => $entry) {
                if (is_string($key) && strtolower($key) === strtolower($package) && $entry === true) {
                    return true;
                }

                if (is_string($entry) && strtolower($entry) === strtolower($package)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function binaryResolver(): BinaryResolver
    {
        return $this->binaryResolver ?? new BinaryResolver;
    }

    private function stringOption(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function withinProject(string $candidate, string $project): bool
    {
        $candidate = rtrim(str_replace('\\', '/', $candidate), '/');
        $project = rtrim(str_replace('\\', '/', $project), '/');

        return $candidate === $project || str_starts_with($candidate, $project.'/');
    }
}
