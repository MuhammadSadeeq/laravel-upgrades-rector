<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

use InvalidArgumentException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\ConsoleUpgradeObserver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\PlanFileWriter;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\ProjectVersionDetector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\UpgradeRuntimeFactory;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\UpgradeRuntimeInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Journal\StateStore;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Runs the real, journaled upgrade orchestrator for one or more majors. */
final class ToCommand extends Command
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
            ->setName('to')
            ->setDescription('Run the full Laravel upgrade flow up to the target major')
            ->addArgument('target-major', InputArgument::REQUIRED, 'Target Laravel major version (e.g. 11)');

        self::addUpgradeOptions($this);
    }

    /** Add the common options to `to` and the explicit `plan` alias. */
    public static function addUpgradeOptions(Command $command, bool $includePlan = true): void
    {
        if ($includePlan) {
            $command->addOption('plan', null, InputOption::VALUE_NONE, 'Preview the complete upgrade without project mutations');
        }

        $command
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compatibility alias for --plan')
            ->addOption('from-step', null, InputOption::VALUE_REQUIRED, 'Resume the first transition at this canonical step')
            ->addOption('skip-step', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Skip a canonical step (repeatable or comma-separated)')
            ->addOption('no-git', null, InputOption::VALUE_NONE, 'Disable git safety and checkpoint commits')
            ->addOption('allow-dirty', null, InputOption::VALUE_NONE, 'Allow a dirty worktree while protecting baseline paths')
            ->addOption('no-install', null, InputOption::VALUE_NONE, 'Do not run Composer update/install')
            ->addOption('no-tests', null, InputOption::VALUE_NONE, 'Skip the artisan test verification')
            ->addOption('skip-tests', null, InputOption::VALUE_NONE, 'Compatibility alias for --no-tests')
            ->addOption('no-pint', null, InputOption::VALUE_NONE, 'Do not run Pint after Rector')
            ->addOption('annotate', null, InputOption::VALUE_NONE, 'Write advisory TODO comments into PHP source files')
            ->addOption('constraint-policy', null, InputOption::VALUE_REQUIRED, 'Dependency constraint policy (replace or widen)', 'replace')
            ->addOption('structure', null, InputOption::VALUE_REQUIRED, 'Skeleton structure mode (keep or modern)', 'keep')
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Do not ask for confirmation')
            ->addOption('working-dir', 'd', InputOption::VALUE_REQUIRED, 'Project directory', '.')
            ->addOption('composer', null, InputOption::VALUE_REQUIRED, 'Composer binary or project-contained path')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Reset a conflicting active journal explicitly');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $workingDirectory = $this->workingDirectory($input);

        if ($workingDirectory === null) {
            $style->error('The working directory does not exist.');

            return Command::FAILURE;
        }

        $targetMajor = $this->targetMajor($input);

        if ($targetMajor === null) {
            $style->error('Target major must be one of: 11, 12, 13.');

            return Command::FAILURE;
        }

        $planMode = (bool) $input->getOption('plan') || (bool) $input->getOption('dry-run');
        $structure = $input->getOption('structure');

        if (! is_string($structure) || ! in_array($structure, ['keep', 'modern'], true)) {
            $style->error('Structure must be either keep or modern.');

            return Command::FAILURE;
        }

        if ($structure === 'modern') {
            $style->error('Modern structure mode is not available yet (Phase 6).');

            return Command::FAILURE;
        }

        $constraintPolicy = $this->stringOption($input, 'constraint-policy', 'replace');

        if (! in_array($constraintPolicy, ['replace', 'widen'], true)) {
            $style->error('Constraint policy must be either replace or widen.');

            return Command::FAILURE;
        }

        $detected = $this->versionDetector->detect($workingDirectory);

        if ($detected->major === null) {
            $style->error($detected->warning ?? 'Could not detect the current Laravel major.');

            return Command::FAILURE;
        }

        if ($detected->warning !== null) {
            $style->warning($detected->warning);
        }

        if ($detected->major === $targetMajor) {
            $style->success(sprintf('Already on Laravel %d — nothing to do.', $targetMajor));

            return Command::SUCCESS;
        }

        try {
            $skipSteps = $this->skipSteps($input->getOption('skip-step'));
            $fromStep = $input->getOption('from-step');
            $fromStep = is_string($fromStep) && $fromStep !== '' ? $fromStep : null;
            $plan = new UpgradePlan($detected->major, $targetMajor, $planMode, $fromStep, $skipSteps);
        } catch (InvalidArgumentException $exception) {
            $style->error($exception->getMessage());

            return Command::FAILURE;
        }

        $options = $this->runOptions($input, $workingDirectory, $plan, $skipSteps, $fromStep);

        if (! $planMode) {
            $stateStore = new StateStore($workingDirectory);
            $statePathExists = is_file($stateStore->path());
            $state = $stateStore->load();

            if ($statePathExists && $state === null) {
                if (! (bool) $input->getOption('reset')) {
                    $style->error('The upgrade state file is corrupt; pass --reset to discard it explicitly.');

                    return Command::FAILURE;
                }

                $stateStore->reset();
            } elseif (is_array($state)
                && ($state['status'] ?? StateStore::STATUS_RUNNING) !== StateStore::STATUS_COMPLETED
                && ($state['target'] ?? null) !== $targetMajor) {
                if (! (bool) $input->getOption('reset')) {
                    $style->error(sprintf(
                        'An active Laravel %s upgrade exists; pass --reset before starting Laravel %d.',
                        $this->displayValue($state['target'] ?? '?'),
                        $targetMajor,
                    ));

                    return Command::FAILURE;
                }

                $stateStore->reset();
            }
        }

        $style->title(sprintf('Laravel %d → Laravel %d', $detected->major, $targetMajor));
        $style->table(
            ['Setting', 'Value'],
            [
                ['Working directory', $workingDirectory],
                ['Current major', (string) $detected->major],
                ['Target major', (string) $targetMajor],
                ['Mode', $planMode ? 'plan' : 'apply'],
                ['Git', $options['git'] === true ? 'enabled' : 'disabled'],
                ['Tests', $options['noTests'] === true ? 'skipped' : 'enabled'],
            ],
        );

        if (! $planMode && ! (bool) $input->getOption('no-interaction') && $input->isInteractive()) {
            if (! $style->confirm(sprintf('Upgrade to Laravel %d now?', $targetMajor), true)) {
                $style->note('Aborted by user.');

                return Command::SUCCESS;
            }
        }

        try {
            $result = $this->runtime->run(
                $plan,
                $workingDirectory,
                $options,
                new ConsoleUpgradeObserver($output),
            );
        } catch (\Throwable $exception) {
            $style->error('Upgrade could not be started: '.$exception->getMessage());

            return Command::FAILURE;
        }

        if ($planMode) {
            try {
                $path = $this->planWriter->write($workingDirectory, $plan, $result);
                $style->text(sprintf('Plan written: %s', $path));
            } catch (\Throwable $exception) {
                $style->error('Could not write plan.json: '.$exception->getMessage());

                return Command::FAILURE;
            }
        }

        if (! $result->success) {
            $style->error($result->failureMessage ?? 'Upgrade failed.');

            return $result->exitCode > 0 ? $result->exitCode : Command::FAILURE;
        }

        $style->success($planMode ? 'Upgrade plan completed.' : sprintf('Upgrade to Laravel %d complete.', $targetMajor));

        return Command::SUCCESS;
    }

    public static function advisoryConfigPath(int $targetMajor): string
    {
        return dirname(__DIR__, 4).'/resources/phpstan/upgrade-'.$targetMajor.'.neon';
    }

    private function workingDirectory(InputInterface $input): ?string
    {
        $value = $input->getOption('working-dir');
        $directory = is_string($value) && $value !== '' ? $value : '.';
        $resolved = realpath($directory);

        return $resolved !== false && is_dir($resolved) ? $resolved : null;
    }

    private function targetMajor(InputInterface $input): ?int
    {
        $value = $input->getArgument('target-major');

        if (! is_scalar($value) || preg_match('/^(11|12|13)$/', (string) $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    /** @return list<string> */
    private function skipSteps(mixed $raw): array
    {
        $values = is_array($raw) ? $raw : [$raw];
        $steps = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            foreach (explode(',', $value) as $step) {
                $step = trim($step);

                if ($step !== '' && ! in_array($step, $steps, true)) {
                    $steps[] = $step;
                }
            }
        }

        return $steps;
    }

    /** @param list<string> $skipSteps
     * @return array<string, mixed>
     */
    private function runOptions(
        InputInterface $input,
        string $workingDirectory,
        UpgradePlan $plan,
        array $skipSteps,
        ?string $fromStep,
    ): array {
        $composer = $input->getOption('composer');
        $composer = is_string($composer) && $composer !== '' ? $composer : null;
        $noTests = (bool) $input->getOption('no-tests') || (bool) $input->getOption('skip-tests');
        $noGit = (bool) $input->getOption('no-git');

        return [
            'workingDirectory' => $workingDirectory,
            'git' => ! $noGit,
            'noGit' => $noGit,
            'allowDirty' => (bool) $input->getOption('allow-dirty'),
            'noInstall' => (bool) $input->getOption('no-install'),
            'noTests' => $noTests,
            'noPint' => (bool) $input->getOption('no-pint'),
            'pint' => ! (bool) $input->getOption('no-pint'),
            'annotate' => (bool) $input->getOption('annotate') && ! $plan->isPlanMode(),
            'constraintPolicy' => $this->stringOption($input, 'constraint-policy', 'replace'),
            'structure' => $this->stringOption($input, 'structure', 'keep'),
            'noInteraction' => (bool) $input->getOption('no-interaction'),
            'composerBinary' => $composer,
            'clearCache' => true,
            'fromStep' => $fromStep,
            'skipSteps' => $skipSteps,
        ];
    }

    private function stringOption(InputInterface $input, string $name, string $default): string
    {
        $value = $input->getOption($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function displayValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '?';
    }
}
