<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use Symfony\Component\Process\Process;

/**
 * All composer.json mutations go through the Composer CLI so that
 * indentation, key order and trailing newlines are preserved by Composer's
 * own JsonManipulator (decision D1). This class never decodes/encodes
 * composer.json for writing.
 */
final class ComposerCli
{
    public function __construct(
        private readonly string $workingDirectory,
        private readonly ?string $composerBinary = null,
    ) {
    }

    /**
     * @param array<string, string> $constraints package => constraint, applied as one command per pair
     * @return list<string>
     */
    public function requirePackages(array $constraints, bool $dev): array
    {
        $commands = [];

        foreach ($constraints as $package => $constraint) {
            $arguments = ['require', sprintf('%s:%s', $package, $constraint), '--no-update', '--no-interaction'];

            if ($dev) {
                $arguments[] = '--dev';
            }

            $commands[] = sprintf('composer %s', implode(' ', array_map('escapeshellarg', $arguments)));

            $this->run($arguments);
        }

        return $commands;
    }

    /**
     * @param list<string> $packages
     * @return list<string>
     */
    public function removePackages(array $packages, bool $dev): array
    {
        if ($packages === []) {
            return [];
        }

        $arguments = array_merge(['remove'], $packages, ['--no-update', '--no-interaction']);

        if ($dev) {
            $arguments[] = '--dev';
        }

        $command = sprintf('composer %s', implode(' ', array_map('escapeshellarg', $arguments)));
        $this->run($arguments);

        return [$command];
    }

    public function validate(): ProcessResult
    {
        return $this->capture(['validate', '--strict', '--no-check-lock']);
    }

    public function updateDryRun(): ProcessResult
    {
        return $this->capture(['update', '--dry-run', '--with-all-dependencies', '--no-interaction']);
    }

    public function whyNot(string $package, string $constraint): ProcessResult
    {
        return $this->capture(['why-not', $package, $constraint]);
    }

    /**
     * @param list<string> $arguments
     */
    private function run(array $arguments): void
    {
        $process = new Process(
            array_merge([$this->resolveComposerBinary()], $arguments),
            $this->workingDirectory
        );
        $process->setTimeout(300);
        $process->mustRun();
    }

    /**
     * @param list<string> $arguments
     */
    private function capture(array $arguments): ProcessResult
    {
        $displayArguments = sprintf('composer %s', implode(' ', array_map('escapeshellarg', $arguments)));

        $process = new Process(
            array_merge([$this->resolveComposerBinary()], $arguments),
            $this->workingDirectory
        );
        $process->setTimeout(300);
        $process->run();

        return new ProcessResult(
            $displayArguments,
            $process->getExitCode() ?? 0,
            $process->getOutput() . $process->getErrorOutput()
        );
    }

    private function resolveComposerBinary(): string
    {
        $envBinary = getenv('COMPOSER_BINARY');

        if (is_string($envBinary) && $envBinary !== '') {
            return $envBinary;
        }

        return $this->composerBinary ?? 'composer';
    }
}
