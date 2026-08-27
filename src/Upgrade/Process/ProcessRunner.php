<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process;

interface ProcessRunner
{
    public function run(ProcessRequest $request): ProcessResult;
}
