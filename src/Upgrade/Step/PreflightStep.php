<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\BinaryResolver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use Throwable;

/** Read-only environment checks required before an upgrade starts. */
final class PreflightStep implements StepInterface
{
    private readonly string $compatibilityPath;

    /**
     * @param  array<string, bool>|null  $loadedExtensions  Optional test/runtime
     *                                                      override. When omitted, extension_loaded() is used.
     */
    public function __construct(
        private readonly ProcessRunner $processRunner,
        ?string $compatibilityPath = null,
        private readonly ?BinaryResolver $binaryResolver = null,
        private readonly ?int $phpVersionId = null,
        private readonly ?string $curlVersion = null,
        private readonly ?array $loadedExtensions = null,
    ) {
        $this->compatibilityPath = $compatibilityPath
            ?? dirname(__DIR__, 3).'/resources/compat/php.json';
    }

    public function name(): string
    {
        return 'preflight';
    }

    public function execute(UpgradeContext $context): StepResult
    {
        $targetMajor = $context->toMajor();
        $compatibility = $this->readCompatibility($targetMajor);

        if ($compatibility === null) {
            return $this->failure(
                'php-compatibility',
                sprintf('No PHP compatibility data exists for Laravel %d.', $targetMajor),
                ['targetMajor' => $targetMajor, 'path' => $this->compatibilityPath],
            );
        }

        $minimum = $compatibility['minimum'] ?? null;

        if (! is_string($minimum) || $minimum === '') {
            return $this->failure(
                'php-compatibility',
                sprintf('PHP compatibility data for Laravel %d is incomplete.', $targetMajor),
                ['targetMajor' => $targetMajor],
            );
        }

        $configuredPhpVersionId = $context->option('phpVersionId');
        $actualPhpVersionId = $this->phpVersionId
            ?? (is_int($configuredPhpVersionId) ? $configuredPhpVersionId : PHP_VERSION_ID);
        $requiredPhpVersionId = $this->versionId($minimum);
        $actualPhpVersion = $this->versionString($actualPhpVersionId);

        if ($requiredPhpVersionId === null || $actualPhpVersionId < $requiredPhpVersionId) {
            return $this->failure(
                'php',
                sprintf('Laravel %d requires PHP %s; PHP %s is running.', $targetMajor, $minimum, $actualPhpVersion),
                [
                    'targetMajor' => $targetMajor,
                    'required' => $minimum,
                    'actual' => $actualPhpVersion,
                ],
            );
        }

        $requiredExtensions = $this->requiredExtensions($compatibility, $context);
        $missingExtensions = array_values(array_filter(
            $requiredExtensions,
            fn (string $extension): bool => ! $this->extensionLoaded($extension),
        ));

        if ($missingExtensions !== []) {
            return $this->failure(
                'extensions',
                'Required PHP extensions are missing: '.implode(', ', $missingExtensions).'.',
                ['missing' => $missingExtensions, 'targetMajor' => $targetMajor],
            );
        }

        $composerBinary = $this->binaryResolver()->composerBinary(
            $this->stringOption($context, 'composerBinary'),
        );

        try {
            $composerVersion = $this->processRunner->run(new ProcessRequest(
                [$composerBinary, '--version'],
                $context->workingDirectory,
            ));
        } catch (Throwable $exception) {
            return $this->failure('composer', 'Could not execute Composer: '.$exception->getMessage());
        }

        if (! $composerVersion->isSuccessful()) {
            return $this->failure(
                'composer',
                'Composer could not be executed.',
                $this->processData($composerVersion),
            );
        }

        $version = $this->parseVersion($composerVersion->combinedOutput());

        if ($version === null || version_compare($version, '2.2.0', '<')) {
            return $this->failure(
                'composer-version',
                'Composer 2.2 or newer is required.',
                ['required' => '2.2.0', 'actual' => $version, 'output' => $composerVersion->combinedOutput()],
            );
        }

        $composerPath = rtrim($context->workingDirectory, '/').'/composer.json';

        if (! is_file($composerPath)) {
            return $this->failure('composer-json', 'No composer.json exists in the project directory.', ['path' => $composerPath]);
        }

        try {
            $validation = $this->processRunner->run(new ProcessRequest(
                [$composerBinary, 'validate', '--strict', '--no-check-lock'],
                $context->workingDirectory,
            ));
        } catch (Throwable $exception) {
            return $this->failure('composer-json', 'Could not validate composer.json: '.$exception->getMessage());
        }

        if (! $validation->isSuccessful()) {
            return $this->failure(
                'composer-json',
                'composer.json failed Composer validation.',
                $this->processData($validation),
            );
        }

        if ($targetMajor === 11 && ! $this->curlMeetsMinimum()) {
            return $this->failure(
                'curl',
                'Laravel 11 requires cURL 7.34 or newer.',
                ['required' => '7.34.0', 'actual' => $this->currentCurlVersion()],
            );
        }

        if ($this->sqliteIsConfigured($context) && ! $this->sqliteMeetsMinimum($context)) {
            return $this->failure(
                'sqlite',
                'Configured SQLite must be version 3.26 or newer.',
                ['required' => '3.26.0', 'actual' => $this->sqliteVersion($context)],
            );
        }

        if ($context->option('git', false) === true && $context->option('allowDirty', false) !== true) {
            $gitFailure = $this->dirtyGitFailure($context);

            if ($gitFailure !== null) {
                return $gitFailure;
            }
        }

        return StepResult::successful(
            message: sprintf('Preflight checks passed for Laravel %d.', $targetMajor),
            data: [
                'targetMajor' => $targetMajor,
                'php' => $actualPhpVersion,
                'composer' => $version,
                'checkedExtensions' => $requiredExtensions,
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readCompatibility(int $targetMajor): ?array
    {
        if (! is_file($this->compatibilityPath)) {
            return null;
        }

        $contents = file_get_contents($this->compatibilityPath);

        if ($contents === false) {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $data = $decoded['php'] ?? $decoded;

        if (! is_array($data)) {
            return null;
        }

        $entry = $data[(string) $targetMajor] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        /** @var array<string, mixed> $entry */
        return $entry;
    }

    /**
     * @param  array<string, mixed>  $compatibility
     * @return list<string>
     */
    private function requiredExtensions(array $compatibility, UpgradeContext $context): array
    {
        $configured = $context->option('requiredExtensions');

        if (is_array($configured)) {
            return array_values(array_filter($configured, 'is_string'));
        }

        $extensions = $compatibility['requiredExtensions'] ?? [];

        if (! is_array($extensions)) {
            return [];
        }

        return array_values(array_filter($extensions, 'is_string'));
    }

    private function extensionLoaded(string $extension): bool
    {
        if ($this->loadedExtensions !== null && array_key_exists($extension, $this->loadedExtensions)) {
            return $this->loadedExtensions[$extension];
        }

        return extension_loaded($extension);
    }

    private function curlMeetsMinimum(): bool
    {
        $version = $this->currentCurlVersion();

        return $version !== null && version_compare($version, '7.34.0', '>=');
    }

    private function currentCurlVersion(): ?string
    {
        if ($this->curlVersion !== null) {
            return $this->curlVersion;
        }

        if (! function_exists('curl_version')) {
            return null;
        }

        $version = curl_version()['version'] ?? null;

        return is_string($version) ? $version : null;
    }

    private function sqliteIsConfigured(UpgradeContext $context): bool
    {
        $configured = $context->option('sqliteConfigured');

        if (is_bool($configured)) {
            return $configured;
        }

        $environmentFile = rtrim($context->workingDirectory, '/').'/.env';

        if (is_file($environmentFile)) {
            $environment = file_get_contents($environmentFile);

            if ($environment !== false && preg_match('/^\s*DB_CONNECTION\s*=\s*(["\']?)sqlite\1\s*$/mi', $environment) === 1) {
                return true;
            }
        }

        $databaseConfig = rtrim($context->workingDirectory, '/').'/config/database.php';

        if (! is_file($databaseConfig)) {
            return false;
        }

        $contents = file_get_contents($databaseConfig);

        return $contents !== false && str_contains(strtolower($contents), 'sqlite');
    }

    private function sqliteMeetsMinimum(UpgradeContext $context): bool
    {
        $version = $this->sqliteVersion($context);

        return $version !== null && version_compare($version, '3.26.0', '>=');
    }

    private function sqliteVersion(UpgradeContext $context): ?string
    {
        $configured = $context->option('sqliteVersion');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        try {
            $result = $this->processRunner->run(new ProcessRequest(
                [
                    $this->binaryResolver()->phpBinary(),
                    '-r',
                    'echo (new PDO("sqlite::memory:"))->query("select sqlite_version()")->fetchColumn();',
                ],
                $context->workingDirectory,
            ));
        } catch (Throwable) {
            return null;
        }

        return $result->isSuccessful() ? $this->parseVersion($result->combinedOutput()) : null;
    }

    private function dirtyGitFailure(UpgradeContext $context): ?StepResult
    {
        try {
            $result = $this->processRunner->run(new ProcessRequest(
                [$this->binaryResolver()->gitBinary(), 'status', '--porcelain'],
                $context->workingDirectory,
            ));
        } catch (Throwable $exception) {
            return $this->failure('git', 'Could not inspect git status: '.$exception->getMessage());
        }

        if (! $result->isSuccessful()) {
            return $this->failure('git', 'Could not inspect git status.', $this->processData($result));
        }

        if (trim($result->combinedOutput()) !== '') {
            return $this->failure('git', 'The working tree is not clean.', $this->processData($result));
        }

        return null;
    }

    private function binaryResolver(): BinaryResolver
    {
        return $this->binaryResolver ?? new BinaryResolver;
    }

    private function stringOption(UpgradeContext $context, string $name): ?string
    {
        $option = $context->option($name);

        return is_string($option) && $option !== '' ? $option : null;
    }

    private function versionId(string $version): ?int
    {
        if (preg_match('/^(\d+)\.(\d+)(?:\.(\d+))?/', $version, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1] * 10000) + ((int) $matches[2] * 100) + (int) ($matches[3] ?? 0);
    }

    private function versionString(int $versionId): string
    {
        return sprintf('%d.%d.%d', intdiv($versionId, 10000), intdiv($versionId % 10000, 100), $versionId % 100);
    }

    private function parseVersion(string $output): ?string
    {
        if (preg_match('/(?<!\d)(\d+\.\d+(?:\.\d+)?)(?!\d)/', $output, $matches) !== 1) {
            return null;
        }

        return $matches[1];
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

    /**
     * @param  array<string, mixed>  $details
     */
    private function failure(string $check, string $message, array $details = []): StepResult
    {
        return StepResult::failed(
            message: $message,
            data: ['check' => $check, 'details' => $details],
            exitCode: 2,
        );
    }
}
