<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use Closure;
use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory\FindingAnnotator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory\PhpStanConfigGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory\ProjectAdvisor;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/** Runs the target PHPStan advisory rules and project-level advisor. */
final class AdvisoryStep implements StepInterface
{
    /** @var Closure(string, int): ProjectAdvisor */
    private readonly Closure $projectAdvisorFactory;

    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly PhpStanConfigGenerator $configGenerator = new PhpStanConfigGenerator,
        ?callable $projectAdvisorFactory = null,
    ) {
        if ($projectAdvisorFactory === null) {
            $this->projectAdvisorFactory = static fn (string $configDirectory, int $targetMajor): ProjectAdvisor => new ProjectAdvisor($configDirectory, $targetMajor);
        } else {
            $factory = Closure::fromCallable($projectAdvisorFactory);
            $this->projectAdvisorFactory = static function (string $configDirectory, int $targetMajor) use ($factory): ProjectAdvisor {
                $advisor = $factory($configDirectory, $targetMajor);

                if (! $advisor instanceof ProjectAdvisor) {
                    throw new \RuntimeException('The project advisor factory did not return a ProjectAdvisor.');
                }

                return $advisor;
            };
        }
    }

    public function name(): string
    {
        return 'advisories';
    }

    public function execute(UpgradeContext $context): StepResult
    {
        $phpstanBinary = $this->phpstanBinary($context);

        if ($phpstanBinary === null) {
            return StepResult::failed(
                message: 'The project-local PHPStan binary was not found.',
                data: [
                    'check' => 'phpstan-binary',
                    'expected' => rtrim($context->workingDirectory, '/').'/vendor/bin/phpstan',
                ],
                exitCode: 1,
            );
        }

        $paths = $this->analysisPaths($context);

        if ($paths === null) {
            return StepResult::failed(
                message: 'PHPStan analysis paths must exist within the project directory.',
                data: ['check' => 'phpstan-paths'],
                exitCode: 1,
            );
        }

        $temporaryDirectory = null;

        try {
            $outputDirectory = $context->workingDirectory.'/.laravel-upgrade';

            if ($context->isPlanMode()) {
                $temporaryDirectory = $this->temporaryDirectory();
                $outputDirectory = $temporaryDirectory;
            }

            $facts = $this->projectFacts($context);
            $configPath = $this->configGenerator->generate(
                $context->workingDirectory,
                $context->toMajor(),
                $outputDirectory,
                $facts['databaseDrivers'],
                $facts['queueDefault'],
                $facts['sessionSerialization'],
                $facts['localDiskRootConfigured'],
                $facts['localDiskIsDefault'],
            );
            $request = new ProcessRequest(
                array_merge([
                    $phpstanBinary,
                    'analyse',
                    '-c',
                    $configPath,
                    '--error-format=laravelUpgradeJson',
                    '--no-progress',
                    '--memory-limit=-1',
                ], $paths),
                $context->workingDirectory,
                1800.0,
            );
            $process = $this->processRunner->run($request);
            $parsed = $this->parseOutput($process->output);

            if ($parsed === null && $process->errorOutput !== '') {
                $parsed = $this->parseOutput($process->combinedOutput());
            }

            $processData = $this->processData($process);

            if ($parsed === null) {
                return StepResult::failed(
                    message: 'PHPStan did not return valid JSON output.',
                    data: [
                        'check' => 'phpstan-json',
                        'process' => $processData,
                        'output' => $process->combinedOutput(),
                    ],
                    exitCode: $this->processExitCode($process),
                );
            }

            if ($this->hasInternalErrors($parsed)) {
                return StepResult::failed(
                    message: 'PHPStan reported an internal analysis error.',
                    data: [
                        'check' => 'phpstan-internal-error',
                        'process' => $processData,
                        'phpstan' => $parsed,
                    ],
                    exitCode: $this->processExitCode($process),
                );
            }

            $phpstanCollector = new FindingCollector;
            $this->collectPhpStanFindings($parsed, $context, $phpstanCollector);
            $advisorCollector = new FindingCollector;
            $advisor = ($this->projectAdvisorFactory)($context->workingDirectory.'/config', $context->toMajor());

            $advisor->scan($advisorCollector);
            $collector = $this->mergeFindings($phpstanCollector->all(), $advisorCollector->all());
            $findings = $collector->all();
            $serializedFindings = array_map(
                static fn (Finding $finding): array => $finding->toArray(),
                $findings,
            );
            $data = [
                'process' => $processData,
                'phpstan' => $parsed,
                'config' => $configPath,
                'paths' => $paths,
                'facts' => $facts,
                'findings' => $serializedFindings,
                'counts' => $collector->countBySeverity(),
            ];

            if (! $process->isSuccessful() && $process->exitCode !== 1) {
                return StepResult::failed(
                    message: 'PHPStan failed while running advisory analysis.',
                    findingsCount: count($findings),
                    data: $data,
                    exitCode: $this->processExitCode($process),
                );
            }

            if ($context->isPlanMode()) {
                $data['annotation'] = ['status' => 'skipped', 'reason' => 'plan-mode'];
            } else {
                $findingsPath = $outputDirectory.'/findings.jsonl';
                $collector->writeJsonl($findingsPath);
                $data['findingsJsonl'] = $findingsPath;

                if ($context->option('annotate', false) === true) {
                    $annotated = (new FindingAnnotator($context->workingDirectory))->annotateBatch($serializedFindings);
                    $data['annotation'] = ['status' => 'applied', 'count' => $annotated];
                } else {
                    $data['annotation'] = ['status' => 'skipped', 'reason' => 'disabled'];
                }
            }

            return StepResult::successful(
                findingsCount: count($findings),
                message: sprintf('PHPStan advisory analysis completed with %d findings.', count($findings)),
                data: $data,
            );
        } catch (Throwable $exception) {
            return StepResult::failed(
                message: 'Advisory analysis failed: '.$exception->getMessage(),
                data: ['check' => 'advisories', 'exception' => $exception::class],
                exitCode: 1,
            );
        } finally {
            if ($temporaryDirectory !== null) {
                $this->removeDirectory($temporaryDirectory);
            }
        }
    }

    private function phpstanBinary(UpgradeContext $context): ?string
    {
        $configured = $context->option('phpstanBinary');

        if (is_string($configured) && $configured !== '') {
            return $this->projectPath($configured, $context->workingDirectory, true);
        }

        return $this->projectPath('vendor/bin/phpstan', $context->workingDirectory, true);
    }

    /**
     * @return list<string>|null
     */
    private function analysisPaths(UpgradeContext $context): ?array
    {
        $configured = $context->option('phpstanPaths');
        $paths = [];

        if ($configured !== null) {
            if (! is_array($configured)) {
                return null;
            }

            foreach ($configured as $path) {
                if (! is_string($path) || $path === '') {
                    return null;
                }

                $resolved = $this->projectPath($path, $context->workingDirectory, false);

                if ($resolved === null) {
                    return null;
                }

                $paths[] = $resolved;
            }

            return array_values(array_unique($paths));
        }

        foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'tests'] as $relative) {
            $resolved = $this->projectPath($relative, $context->workingDirectory, false);

            if ($resolved !== null) {
                $paths[] = $resolved;
            }
        }

        if ($paths === []) {
            $paths[] = realpath($context->workingDirectory) ?: $context->workingDirectory;
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return array{databaseDrivers: list<string>, queueDefault: ?string, sessionSerialization: ?string, localDiskRootConfigured: bool, localDiskIsDefault: bool}
     */
    private function projectFacts(UpgradeContext $context): array
    {
        $databaseDrivers = $this->stringListOption($context->option('databaseDrivers'));
        $databaseConfig = $this->readProjectFile($context->workingDirectory.'/config/database.php');
        $env = $this->readProjectFile($context->workingDirectory.'/.env');

        if ($databaseDrivers === [] && $databaseConfig !== null) {
            $matched = preg_match_all(
                '/[\'"]driver[\'"]\s*=>\s*(?:env\s*\([^,]+,\s*)?[\'"]([^\'"]+)[\'"]/i',
                $databaseConfig,
                $matches,
            );

            if ($matched !== false) {
                $databaseDrivers = $this->uniqueStrings($matches[1]);
            }

            if (preg_match('/[\'"]default[\'"]\s*=>\s*env\s*\(\s*[\'"]DB_CONNECTION[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i', $databaseConfig, $match) === 1) {
                $databaseDrivers = $this->uniqueStrings(array_merge($databaseDrivers, [$match[1]]));
            }
        }

        if ($env !== null && preg_match('/^\s*DB_CONNECTION\s*=\s*["\']?([^\s"\']+)/im', $env, $match) === 1) {
            $databaseDrivers = $this->uniqueStrings(array_merge($databaseDrivers, [$match[1]]));
        }

        $queueDefault = $this->stringOption($context->option('queueDefault'));
        $queueConfig = $this->readProjectFile($context->workingDirectory.'/config/queue.php');
        $envQueueDefault = $env !== null && preg_match('/^\s*QUEUE_CONNECTION\s*=\s*["\']?([^\s"\']+)/im', $env, $match) === 1
            ? $match[1]
            : null;

        if ($queueDefault === null && $queueConfig !== null
            && preg_match('/[\'"]default[\'"]\s*=>\s*env\s*\(\s*[\'"]QUEUE_CONNECTION[\'"]/i', $queueConfig) === 1
            && $envQueueDefault !== null) {
            $queueDefault = $envQueueDefault;
        }

        if ($queueDefault === null && $queueConfig !== null
            && preg_match('/[\'"]default[\'"]\s*=>\s*(?:env\s*\([^,]+,\s*)?[\'"]([^\'"]+)[\'"]/i', $queueConfig, $match) === 1) {
            $queueDefault = $match[1];
        }

        if ($queueDefault === null && $envQueueDefault !== null) {
            $queueDefault = $envQueueDefault;
        }

        $sessionSerialization = $this->stringOption($context->option('sessionSerialization'));
        $sessionConfig = $this->readProjectFile($context->workingDirectory.'/config/session.php');

        if ($sessionSerialization === null && $sessionConfig !== null
            && preg_match('/[\'"]serialization[\'"]\s*=>\s*(?:env\s*\([^,]+,\s*)?[\'"]([^\'"]+)[\'"]/i', $sessionConfig, $match) === 1) {
            $sessionSerialization = $match[1];
        }

        $filesystemsConfig = $this->readProjectFile($context->workingDirectory.'/config/filesystems.php');
        $localDiskRootConfigured = $this->hasLocalDiskRoot($filesystemsConfig);
        $localDiskIsDefault = $this->localDiskIsDefault(
            $filesystemsConfig,
            $env,
            $context->option('localDiskIsDefault'),
        );

        return [
            'databaseDrivers' => $databaseDrivers,
            'queueDefault' => $queueDefault,
            'sessionSerialization' => $sessionSerialization,
            'localDiskRootConfigured' => $localDiskRootConfigured,
            'localDiskIsDefault' => $localDiskIsDefault,
        ];
    }

    private function localDiskIsDefault(?string $filesystemsConfig, ?string $env, mixed $option): bool
    {
        if (is_bool($option)) {
            return $option;
        }

        $configuredDefault = null;
        $fallbackDefault = null;

        if ($filesystemsConfig !== null
            && preg_match('/[\'\"]default[\'\"]\s*=>\s*[\'\"]([^\'\"]+)[\'\"]/i', $filesystemsConfig, $match) === 1) {
            $configuredDefault = $match[1];
        } elseif ($filesystemsConfig !== null
            && preg_match('/[\'\"]default[\'\"]\s*=>\s*env\s*\(\s*[\'\"]FILESYSTEM_DISK[\'\"]\s*(?:,\s*[\'\"]([^\'\"]+)[\'\"])?\s*\)/i', $filesystemsConfig, $match) === 1) {
            $fallbackDefault = $match[1] ?? null;
        }

        if ($env !== null && preg_match('/^\s*FILESYSTEM_DISK\s*=\s*["\']?([^\s"\']+)/im', $env, $match) === 1) {
            $configuredDefault = $match[1];
        }

        $default = $configuredDefault ?? $fallbackDefault ?? 'local';

        return strtolower($default) === 'local';
    }

    private function hasLocalDiskRoot(?string $filesystemsConfig): bool
    {
        if ($filesystemsConfig === null
            || preg_match('/[\'"]local[\'"]\s*=>\s*\[(?<body>.*?)\]/s', $filesystemsConfig, $match) !== 1) {
            return false;
        }

        return preg_match('/[\'"]root[\'"]\s*=>/', $match['body']) === 1;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function collectPhpStanFindings(array $parsed, UpgradeContext $context, FindingCollector $collector): void
    {
        $files = $parsed['files'] ?? [];

        if (! is_array($files)) {
            return;
        }

        foreach ($files as $file => $fileData) {
            if (! is_array($fileData)) {
                continue;
            }

            $filePath = is_string($file) ? $file : '';
            $messages = $fileData['messages'] ?? [];

            if (! is_array($messages)) {
                continue;
            }

            foreach ($messages as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $message = $this->stringKeyedArray($message);
                $source = $filePath !== '' ? $filePath : (is_string($message['file'] ?? null) ? $message['file'] : '');
                $relativePath = $this->relativeProjectPath($source, $context->workingDirectory);

                if ($relativePath === null) {
                    continue;
                }

                $ruleId = $this->messageString($message, 'identifier')
                    ?? $this->messageString($message, 'id')
                    ?? 'phpstan';

                // Report upgrade advisories only. Every rule this package
                // registers uses a laravelUpgrade.* identifier; anything else is
                // a pre-existing static-analysis result about the project's own
                // code, which the user did not ask this step to audit and which
                // can be a false positive on stock framework files. Analysis
                // failures are surfaced separately from the run's error list.
                if (! str_starts_with($ruleId, 'laravelUpgrade.')) {
                    continue;
                }

                $text = $this->messageString($message, 'message') ?? 'PHPStan advisory reported.';
                $metadata = is_array($message['metadata'] ?? null)
                    ? $this->stringKeyedArray($message['metadata'])
                    : [];
                $severity = $this->severity($message, $metadata);
                $action = $this->messageString($message, 'tip')
                    ?? $this->metadataString($metadata, ['action', 'tip'])
                    ?? '';
                $guideUrl = $this->metadataString($metadata, ['guideUrl', 'guide_url', 'guide']) ?? '';
                $confidence = $this->confidence($metadata);
                $line = is_int($message['line'] ?? null) && $message['line'] >= 0 ? $message['line'] : 0;

                $collector->add(
                    ruleId: $ruleId,
                    severity: $severity,
                    laravelVersion: $context->toMajor(),
                    file: $relativePath,
                    line: $line,
                    message: $text,
                    action: $action,
                    guideUrl: $guideUrl,
                    confidence: $confidence,
                );
            }
        }
    }

    /**
     * @param  list<Finding>  $phpstan
     * @param  list<Finding>  $advisor
     */
    private function mergeFindings(array $phpstan, array $advisor): FindingCollector
    {
        $collector = new FindingCollector;
        $seen = [];

        foreach (array_merge($phpstan, $advisor) as $finding) {
            $key = $finding->identity();

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $collector->addFinding($finding);
        }

        return $collector;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function hasInternalErrors(array $parsed): bool
    {
        $errors = $parsed['errors'] ?? [];

        return ! is_array($errors) || $errors !== [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseOutput(string $output): ?array
    {
        $trimmed = trim($output);

        if ($trimmed === '') {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $start = strpos($output, '{');
            $end = strrpos($output, '}');

            if ($start === false || $end === false || $end < $start) {
                return null;
            }

            try {
                $decoded = json_decode(substr($output, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }
        }

        if (! is_array($decoded)) {
            return null;
        }

        $result = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        if (! is_array($result['files'] ?? null) || ! array_key_exists('errors', $result)) {
            return null;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $metadata
     */
    private function severity(array $message, array $metadata): string
    {
        $candidate = $this->messageString($message, 'severity') ?? $this->metadataString($metadata, ['severity']);

        return is_string($candidate) && in_array($candidate, [
            Finding::SEVERITY_HIGH,
            Finding::SEVERITY_MEDIUM,
            Finding::SEVERITY_LOW,
            Finding::SEVERITY_INFO,
        ], true) ? $candidate : Finding::SEVERITY_MEDIUM;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function confidence(array $metadata): string
    {
        $candidate = $this->metadataString($metadata, ['confidence']);

        return is_string($candidate) && in_array($candidate, ['high', 'medium', 'low'], true)
            ? $candidate
            : 'high';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function messageString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $keys
     */
    private function metadataString(array $metadata, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $metadata[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<mixed, mixed>  $data
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function projectPath(string $path, string $workingDirectory, bool $fileRequired): ?string
    {
        $normalized = str_replace('\\', '/', $path);
        $segments = explode('/', $normalized);

        foreach ($segments as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        $project = realpath($workingDirectory);

        if ($project === false) {
            return null;
        }

        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            $realPath = realpath($normalized);

            if ($realPath === false) {
                return null;
            }

            $normalized = str_replace('\\', '/', $realPath);
        }

        $isAbsolute = str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1;

        if (! $isAbsolute) {
            foreach ($segments as $segment) {
                if ($segment === '' || $segment === '.') {
                    return null;
                }
            }

            $normalized = rtrim(str_replace('\\', '/', $project), '/').'/'.$normalized;
        }

        $candidate = realpath($normalized);

        if ($candidate === false || ! $this->isWithinProject($candidate, $project)) {
            return null;
        }

        if ($fileRequired && ! is_file($candidate)) {
            return null;
        }

        if (! $fileRequired && ! is_file($candidate) && ! is_dir($candidate)) {
            return null;
        }

        return str_replace('\\', '/', $candidate);
    }

    private function relativeProjectPath(string $path, string $workingDirectory): ?string
    {
        $path = str_replace('\\', '/', $path);
        $project = realpath($workingDirectory);

        if ($project === false) {
            return null;
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            $realPath = realpath($path);

            if ($realPath === false) {
                return null;
            }

            $path = str_replace('\\', '/', $realPath);
        }

        $project = rtrim(str_replace('\\', '/', $project), '/');

        if (! str_starts_with($path, '/') && preg_match('/^[A-Za-z]:\\//', $path) !== 1) {
            $segments = explode('/', $path);

            foreach ($segments as $segment) {
                if ($segment === '' || $segment === '.' || $segment === '..') {
                    return null;
                }
            }

            $realPath = realpath($project.'/'.$path);

            if ($realPath !== false) {
                $realPath = str_replace('\\', '/', $realPath);

                if (! $this->isWithinProject($realPath, $project)) {
                    return null;
                }

                $path = substr($realPath, strlen($project) + 1);
            }
        }

        if (str_starts_with($path, $project.'/')) {
            $path = substr($path, strlen($project) + 1);
        } elseif (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            return null;
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path !== '' ? implode('/', $segments) : null;
    }

    private function isWithinProject(string $candidate, string $project): bool
    {
        $candidate = rtrim(str_replace('\\', '/', $candidate), '/');
        $project = rtrim(str_replace('\\', '/', $project), '/');

        return $candidate === $project || str_starts_with($candidate, $project.'/');
    }

    /** @return list<string> */
    private function stringListOption(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $strings[] = strtolower(trim($item));
            }
        }

        return $this->uniqueStrings($strings);
    }

    private function stringOption(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        $strings = [];

        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                $strings[] = strtolower(trim($value));
            }
        }

        $unique = [];

        foreach ($strings as $string) {
            if (! in_array($string, $unique, true)) {
                $unique[] = $string;
            }
        }

        return $unique;
    }

    private function readProjectFile(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/laravel-upgrade-phpstan-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException(sprintf('Could not create temporary PHPStan directory "%s".', $directory));
        }

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo instanceof SplFileInfo) {
                continue;
            }

            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($directory);
    }

    /** @return array<string, mixed> */
    private function processData(ProcessResult $result): array
    {
        return [
            'command' => $result->arguments,
            'exitCode' => $result->exitCode,
            'output' => $result->combinedOutput(),
        ];
    }

    private function processExitCode(ProcessResult $result): int
    {
        return $result->exitCode > 0 ? $result->exitCode : 1;
    }
}
