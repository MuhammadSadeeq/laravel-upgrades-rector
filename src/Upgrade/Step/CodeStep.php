<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Rector\RectorConfigGenerator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/** Runs the target-major Rector set and optionally formats changed PHP files. */
final class CodeStep implements StepInterface
{
    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly RectorConfigGenerator $configGenerator = new RectorConfigGenerator,
    ) {}

    public function name(): string
    {
        return 'code';
    }

    public function execute(UpgradeContext $context): StepResult
    {
        $rectorBinary = $this->rectorBinary($context);

        if ($rectorBinary === null) {
            return $this->failure(
                'The project-local Rector binary was not found.',
                data: [
                    'check' => 'rector-binary',
                    'expected' => rtrim($context->workingDirectory, '/').'/vendor/bin/rector',
                ],
            );
        }

        $temporaryDirectory = null;

        try {
            if ($context->isPlanMode()) {
                $temporaryDirectory = $this->temporaryDirectory();
                $configPath = $this->configGenerator->generate(
                    $context->workingDirectory,
                    $context->toMajor(),
                    $temporaryDirectory,
                );
            } else {
                $configPath = $this->configGenerator->generate(
                    $context->workingDirectory,
                    $context->toMajor(),
                );
            }

            $arguments = [
                $rectorBinary,
                'process',
                '--config',
                $configPath,
                '--output-format=json',
                '--no-progress-bar',
            ];

            // Make the project's Composer classes available before Rector
            // loads optional PHPStan extensions (notably Larastan). The
            // project-local binary normally provides this already, but the
            // explicit option keeps generated configs portable when invoked
            // through a wrapper or from a different Composer installation.
            $autoloadFile = rtrim($context->workingDirectory, '/\\').'/vendor/autoload.php';

            if (is_file($autoloadFile)) {
                $arguments[] = '--autoload-file';
                $arguments[] = $autoloadFile;
            }

            if ($context->option('clearCache', true) === true) {
                $arguments[] = '--clear-cache';
            }

            if ($context->isPlanMode()) {
                $arguments[] = '--dry-run';
            }

            $rectorResult = $this->processRunner->run(new ProcessRequest(
                $arguments,
                $context->workingDirectory,
                1800.0,
            ));
            $parsed = $this->parseOutput($rectorResult->output);

            if ($parsed === null && $rectorResult->errorOutput !== '') {
                $parsed = $this->parseOutput($rectorResult->combinedOutput());
            }
            $processData = $this->processData($rectorResult);

            if ($parsed === null) {
                return $this->failure(
                    'Rector did not return valid JSON output.',
                    data: ['process' => $processData, 'output' => $rectorResult->combinedOutput()],
                    exitCode: $this->processExitCode($rectorResult),
                );
            }

            $changedFiles = $this->changedFiles($parsed, $context->workingDirectory);
            $appliedRules = $this->appliedRules($parsed);
            $appliedRuleCounts = $this->appliedRuleCounts($appliedRules);
            $errors = $this->errors($parsed);
            $details = [
                'process' => $processData,
                'rector' => $parsed,
                'changedFiles' => $changedFiles,
                'appliedRules' => $appliedRules,
                'appliedRuleCounts' => $appliedRuleCounts,
                'errors' => $errors,
            ];

            $dryRunChangeExit = $context->isPlanMode()
                && $rectorResult->exitCode === 2
                && $errors === []
                && $this->hasChangedFiles($parsed, $changedFiles);

            if (! $rectorResult->isSuccessful() && ! $dryRunChangeExit) {
                return $this->failure(
                    'Rector failed while processing the project.',
                    $changedFiles,
                    $details,
                    $this->processExitCode($rectorResult),
                );
            }

            if ($errors !== []) {
                return $this->failure(
                    'Rector reported processing errors.',
                    $changedFiles,
                    $details,
                );
            }

            if ($context->isPlanMode()) {
                return StepResult::successful(
                    changedFiles: $changedFiles,
                    message: 'Rector changes previewed; project files were not changed.',
                    data: $details,
                );
            }

            return $this->formatChangedFiles(
                $context,
                $changedFiles,
                $details,
            );
        } catch (Throwable $exception) {
            return $this->failure(
                'Code transformation failed: '.$exception->getMessage(),
                data: ['check' => 'rector', 'exception' => $exception::class],
            );
        } finally {
            if ($temporaryDirectory !== null) {
                $this->removeDirectory($temporaryDirectory);
            }
        }
    }

    private function rectorBinary(UpgradeContext $context): ?string
    {
        $configured = $context->option('rectorBinary');

        if (is_string($configured) && $configured !== '') {
            return $this->projectPath($configured, $context->workingDirectory);
        }

        return $this->projectPath('vendor/bin/rector', $context->workingDirectory);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return list<string>
     */
    private function changedFiles(array $parsed, string $workingDirectory): array
    {
        $paths = [];
        $topLevel = $parsed['changed_files'] ?? $parsed['changedFiles'] ?? [];

        if (is_array($topLevel)) {
            foreach ($topLevel as $path) {
                if (is_string($path)) {
                    $normalized = $this->projectRelativePath($path, $workingDirectory);

                    if ($normalized !== null) {
                        $paths[] = $normalized;
                    }
                }
            }
        }

        $files = $parsed['files'] ?? $parsed['file_diffs'] ?? [];

        if (is_array($files)) {
            foreach ($files as $path => $details) {
                if (is_string($path)) {
                    $normalized = $this->projectRelativePath($path, $workingDirectory);

                    if ($normalized !== null) {
                        $paths[] = $normalized;
                    }
                }

                if (is_array($details)) {
                    $file = $details['file'] ?? null;

                    if (is_string($file)) {
                        $normalized = $this->projectRelativePath($file, $workingDirectory);

                        if ($normalized !== null) {
                            $paths[] = $normalized;
                        }
                    }
                }
            }
        }

        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return list<string>
     */
    private function appliedRules(array $parsed): array
    {
        $rules = [];
        $this->appendRules($rules, $parsed['applied_rules'] ?? $parsed['appliedRules'] ?? []);

        $files = $parsed['files'] ?? $parsed['file_diffs'] ?? [];

        if (is_array($files)) {
            foreach ($files as $details) {
                if (is_array($details)) {
                    $this->appendRules($rules, $details['applied_rectors'] ?? $details['applied_rules'] ?? []);
                }
            }
        }

        sort($rules);

        return $rules;
    }

    /**
     * @param  list<string>  $rules
     * @return array<string, int>
     */
    private function appliedRuleCounts(array $rules): array
    {
        $counts = [];

        foreach ($rules as $rule) {
            $counts[$rule] = ($counts[$rule] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * A Rector dry-run returns exit 2 when it found changes. The JSON payload
     * is the authority here; an exit 2 with no changed files remains a failure.
     *
     * @param  array<string, mixed>  $parsed
     * @param  list<string>  $changedFiles
     */
    private function hasChangedFiles(array $parsed, array $changedFiles): bool
    {
        if ($changedFiles !== []) {
            return true;
        }

        $totals = $parsed['totals'] ?? [];

        return is_array($totals)
            && is_int($totals['changed_files'] ?? null)
            && $totals['changed_files'] > 0;
    }

    /**
     * @param  list<string>  $rules
     */
    private function appendRules(array &$rules, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $rule) {
            if (is_string($rule) && $rule !== '') {
                $rules[] = $rule;
            } elseif (is_string($key) && $key !== '' && (is_int($rule) || is_float($rule))) {
                $rules[] = $key;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return list<mixed>
     */
    private function errors(array $parsed): array
    {
        $errors = $parsed['errors'] ?? [];

        if (is_array($errors)) {
            /** @var list<mixed> $errors */
            $normalized = array_values($errors);
        } else {
            $normalized = [];
        }

        $totals = $parsed['totals'] ?? [];

        if (is_array($totals) && is_int($totals['errors'] ?? null) && $totals['errors'] > 0 && $normalized === []) {
            $normalized = array_fill(0, $totals['errors'], 'Rector reported an error.');
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseOutput(string $output): ?array
    {
        $objectStart = strpos($output, '{');
        $arrayStart = strpos($output, '[');

        if ($objectStart === false && $arrayStart === false) {
            return null;
        }

        if ($objectStart !== false) {
            $start = $objectStart;
            $endCharacter = '}';
        } else {
            $start = $arrayStart;
            $endCharacter = ']';
        }

        $end = strrpos($output, $endCharacter);

        if ($end === false || $end < $start) {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode(substr($output, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function projectRelativePath(string $path, string $workingDirectory): ?string
    {
        $path = str_replace('\\', '/', $path);
        $project = rtrim(str_replace('\\', '/', $workingDirectory), '/');

        if (str_starts_with($path, $project.'/')) {
            $path = substr($path, strlen($project) + 1);
        } elseif (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            return null;
        }

        if ($path === '' || str_starts_with($path, '../') || $path === '..') {
            return null;
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        if ($segments === []) {
            return null;
        }

        return implode('/', $segments);
    }

    /**
     * @param  list<string>  $changedFiles
     * @param  array<string, mixed>  $details
     */
    private function formatChangedFiles(
        UpgradeContext $context,
        array $changedFiles,
        array $details,
    ): StepResult {
        $phpFiles = array_values(array_filter(
            $changedFiles,
            static fn (string $file): bool => str_ends_with(strtolower($file), '.php'),
        ));

        if ($context->option('noPint', false) === true || $context->option('pint', true) === false) {
            $details['pint'] = ['status' => 'skipped', 'reason' => 'disabled'];

            return StepResult::successful(
                changedFiles: $changedFiles,
                message: 'Rector changes applied; Pint was disabled.',
                data: $details,
            );
        }

        if ($phpFiles === []) {
            $details['pint'] = ['status' => 'skipped', 'reason' => 'no-changed-php'];

            return StepResult::successful(
                changedFiles: $changedFiles,
                message: 'Rector changes applied; no changed PHP files required Pint.',
                data: $details,
            );
        }

        $pintBinary = $this->pintBinary($context);

        if ($pintBinary === null) {
            $details['pint'] = ['status' => 'skipped', 'reason' => 'not-installed'];

            return StepResult::successful(
                changedFiles: $changedFiles,
                message: 'Rector changes applied; Pint is not installed.',
                data: $details,
            );
        }

        $pintArguments = array_merge([$pintBinary], $phpFiles);

        try {
            $pintResult = $this->processRunner->run(new ProcessRequest(
                $pintArguments,
                $context->workingDirectory,
                600.0,
            ));
        } catch (Throwable $exception) {
            return $this->failure(
                'Pint failed: '.$exception->getMessage(),
                $changedFiles,
                $details,
            );
        }

        $details['pint'] = $this->processData($pintResult);

        if (! $pintResult->isSuccessful()) {
            return $this->failure(
                'Pint failed while formatting changed PHP files.',
                $changedFiles,
                $details,
                $this->processExitCode($pintResult),
            );
        }

        return StepResult::successful(
            changedFiles: $changedFiles,
            message: 'Rector changes applied and changed PHP files formatted.',
            data: $details,
        );
    }

    private function pintBinary(UpgradeContext $context): ?string
    {
        $configured = $context->option('pintBinary');

        if (is_string($configured) && $configured !== '') {
            return $this->projectPath($configured, $context->workingDirectory);
        }

        return $this->projectPath('vendor/bin/pint', $context->workingDirectory);
    }

    private function projectPath(string $path, string $workingDirectory): ?string
    {
        $normalized = str_replace('\\', '/', $path);
        $segments = explode('/', $normalized);

        foreach ($segments as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        if (! str_starts_with($normalized, '/') && preg_match('/^[A-Za-z]:\//', $normalized) !== 1) {
            $normalized = rtrim(str_replace('\\', '/', $workingDirectory), '/').'/'.$normalized;
        }

        $project = realpath($workingDirectory);
        $candidate = realpath($normalized);

        if ($project === false || $candidate === false || ! is_file($candidate)) {
            return null;
        }

        $project = str_replace('\\', '/', rtrim($project, '/'));
        $candidate = str_replace('\\', '/', $candidate);

        if ($candidate !== $project && ! str_starts_with($candidate, $project.'/')) {
            return null;
        }

        return $normalized;
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/laravel-upgrade-rector-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException(sprintf('Could not create temporary Rector directory "%s".', $directory));
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
            /** @var SplFileInfo $fileInfo */
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($directory);
    }

    /**
     * @param  list<string>  $changedFiles
     * @param  array<string, mixed>  $data
     */
    private function failure(
        string $message,
        array $changedFiles = [],
        array $data = [],
        int $exitCode = 1,
    ): StepResult {
        return StepResult::failed(
            message: $message,
            changedFiles: $changedFiles,
            data: $data,
            exitCode: $exitCode > 0 ? $exitCode : 1,
        );
    }

    /**
     * @return array<string, mixed>
     */
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
