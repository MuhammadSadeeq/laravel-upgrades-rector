<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\PlanFileWriter;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\ProjectVersionDetector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\UpgradeRuntimeFactory;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\UpgradeRuntimeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Explicit alias for `to --plan`, retaining all operational preview options. */
final class PlanCommand extends Command
{
    public function __construct(
        private readonly UpgradeRuntimeInterface $runtime = new UpgradeRuntimeFactory,
        private readonly ProjectVersionDetector $versionDetector = new ProjectVersionDetector,
        private readonly PlanFileWriter $planWriter = new PlanFileWriter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('plan')
            ->setDescription('Preview the complete upgrade without touching project files')
            ->addArgument('target-major', InputArgument::REQUIRED, 'Target Laravel major version');

        ToCommand::addUpgradeOptions($this, false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $input->getArgument('target-major');
        $target = is_scalar($target) ? (string) $target : '';
        $arguments = [
            'target-major' => $target,
            '--dry-run' => true,
        ];

        foreach ([
            'from-step', 'skip-step', 'no-git', 'allow-dirty', 'no-install',
            'no-tests', 'skip-tests', 'no-pint', 'annotate', 'constraint-policy',
            'structure', 'no-interaction', 'working-dir', 'composer', 'reset',
        ] as $option) {
            $value = $input->getOption($option);

            if ($value === null || $value === false || $value === []) {
                continue;
            }

            $arguments['--'.$option] = $value;
        }

        $to = new ToCommand($this->runtime, $this->versionDetector, $this->planWriter);

        return $to->run(new ArrayInput($arguments), $output);
    }
}
