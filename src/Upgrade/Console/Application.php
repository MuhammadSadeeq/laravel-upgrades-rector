<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\ContinueCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\DepsCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\PlanCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\ReportCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\ToCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public const NAME = 'Laravel Upgrade';

    public const VERSION = '1.1.0';

    public function __construct(?UpgradeRuntimeInterface $runtime = null)
    {
        parent::__construct(self::NAME, self::VERSION);

        $runtime ??= new UpgradeRuntimeFactory;

        $this->add(new ContinueCommand($runtime));
        $this->add(new DepsCommand);
        $this->add(new ToCommand($runtime));
        $this->add(new PlanCommand($runtime));
        $this->add(new ReportCommand);
    }
}
