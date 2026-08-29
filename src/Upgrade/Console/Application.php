<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console;

use MuhammadSadeeq\LaravelUpgradesRector\PackageInfo;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\AdviseCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\CodeCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\ContinueCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\DepsCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\PlanCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\PostCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\ReportCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\SkeletonCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\ToCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\VerifyCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public const NAME = 'Laravel Upgrade';

    public const VERSION = PackageInfo::VERSION;

    public function __construct(
        ?UpgradeRuntimeInterface $runtime = null,
        ?SingleStepRuntimeInterface $stepRuntime = null,
    ) {
        parent::__construct(self::NAME, self::VERSION);

        $runtime ??= new UpgradeRuntimeFactory;
        $stepRuntime ??= $runtime instanceof SingleStepRuntimeInterface
            ? $runtime
            : new UpgradeRuntimeFactory;

        $this->add(new ContinueCommand($runtime));
        $this->add(new DepsCommand);
        $this->add(new SkeletonCommand($stepRuntime));
        $this->add(new CodeCommand($stepRuntime));
        $this->add(new AdviseCommand($stepRuntime));
        $this->add(new PostCommand($stepRuntime));
        $this->add(new VerifyCommand($stepRuntime));
        $this->add(new ToCommand($runtime));
        $this->add(new PlanCommand($runtime));
        $this->add(new ReportCommand);
    }
}
