<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process;

use Symfony\Component\Process\Process;

/** ProcessRunner backed by Symfony Process and an argv array. */
final class SymfonyProcessRunner implements ProcessRunner
{
    public function run(ProcessRequest $request): ProcessResult
    {
        $process = new Process($request->arguments, $request->workingDirectory);
        $process->setTimeout($request->timeout);
        $process->run();

        return new ProcessResult(
            $request->arguments,
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }
}
