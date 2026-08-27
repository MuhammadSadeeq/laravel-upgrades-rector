<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ComposerProcessAdapter;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use Throwable;

/** Installs the planned dependency graph and refreshes Composer autoloading. */
final class InstallStep implements StepInterface
{
    public function __construct(private readonly ComposerProcessAdapter $composer) {}

    public function name(): string
    {
        return 'install';
    }

    public function execute(UpgradeContext $context): StepResult
    {
        if ($context->isPlanMode()) {
            return StepResult::skipped(
                message: 'Plan mode: dependency installation was not run.',
                data: ['skipped' => true],
            );
        }

        if ($context->option('noInstall', false) === true) {
            return StepResult::skipped(
                message: 'Dependency installation disabled by noInstall.',
                data: ['skipped' => true, 'reason' => 'noInstall'],
            );
        }

        $composerBinary = $this->composerBinary($context);

        try {
            $update = $this->composer->update($context->workingDirectory, $composerBinary);
        } catch (Throwable $exception) {
            return $this->failure('Composer update failed: '.$exception->getMessage());
        }

        $processes = [$this->processData($update)];

        if (! $update->isSuccessful()) {
            return $this->failure('Composer update failed.', $processes);
        }

        try {
            $autoload = $this->composer->dumpAutoload($context->workingDirectory, $composerBinary);
        } catch (Throwable $exception) {
            return $this->failure('Composer dump-autoload failed: '.$exception->getMessage(), $processes);
        }

        $processes[] = $this->processData($autoload);

        if (! $autoload->isSuccessful()) {
            return $this->failure('Composer dump-autoload failed.', $processes);
        }

        return StepResult::successful(
            message: 'Dependencies installed and autoload files regenerated.',
            data: ['processes' => $processes],
        );
    }

    private function composerBinary(UpgradeContext $context): ?string
    {
        $binary = $context->option('composerBinary');

        return is_string($binary) && $binary !== '' ? $binary : null;
    }

    /**
     * @param  list<array<string, mixed>>  $processes
     */
    private function failure(string $message, array $processes = []): StepResult
    {
        return StepResult::failed(
            message: $message,
            data: ['processes' => $processes],
            exitCode: 3,
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
}
