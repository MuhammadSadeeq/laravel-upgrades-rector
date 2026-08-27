<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

use InvalidArgumentException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\ConsoleUpgradeObserver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\UpgradeRuntimeFactory;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\UpgradeRuntimeInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Journal\StateStore;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Resumes an interrupted schema-v1 upgrade from its first incomplete step. */
final class ContinueCommand extends Command
{
    public function __construct(private readonly UpgradeRuntimeInterface $runtime = new UpgradeRuntimeFactory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('continue')
            ->setDescription('Resume an interrupted upgrade from the journal')
            ->addOption('working-dir', 'd', InputOption::VALUE_REQUIRED, 'Project directory', '.')
            ->addOption('no-tests', null, InputOption::VALUE_NONE, 'Skip the artisan test verification')
            ->addOption('skip-tests', null, InputOption::VALUE_NONE, 'Compatibility alias for --no-tests')
            ->addOption('no-pint', null, InputOption::VALUE_NONE, 'Do not run Pint after Rector')
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Do not ask for confirmation')
            ->addOption('composer', null, InputOption::VALUE_REQUIRED, 'Composer binary or project-contained path')
            ->addOption('no-git', null, InputOption::VALUE_NONE, 'Disable git safety and checkpoint commits')
            ->addOption('allow-dirty', null, InputOption::VALUE_NONE, 'Allow a dirty worktree')
            ->addOption('no-install', null, InputOption::VALUE_NONE, 'Do not run Composer update/install')
            ->addOption('annotate', null, InputOption::VALUE_NONE, 'Write advisory TODO comments into source files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $directory = $this->workingDirectory($input);

        if ($directory === null) {
            $style->error('The working directory does not exist.');

            return Command::FAILURE;
        }

        $store = new StateStore($directory);
        $state = $store->load();

        if ($state === null) {
            $style->error(is_file($store->path()) ? 'state.json is corrupt or unsupported.' : 'No interrupted upgrade found (.laravel-upgrade/state.json missing).');

            return Command::FAILURE;
        }

        if (($state['status'] ?? null) === StateStore::STATUS_COMPLETED) {
            $style->error('The upgrade journal is already completed; there is nothing to continue.');

            return Command::FAILURE;
        }

        $target = $state['target'] ?? null;
        $current = $state['currentMajor'] ?? null;
        $persisted = is_array($state['options'] ?? null) ? $state['options'] : [];
        $fromStep = is_string($persisted['fromStep'] ?? null) && $persisted['fromStep'] !== ''
            ? $persisted['fromStep']
            : null;
        $skipSteps = [];

        if (is_array($persisted['skipSteps'] ?? null)) {
            foreach ($persisted['skipSteps'] as $skipStep) {
                if (is_string($skipStep)) {
                    $skipSteps[] = $skipStep;
                }
            }
        }

        if (! is_int($target) || ! is_int($current)) {
            $style->error('state.json is missing a valid target or current major.');

            return Command::FAILURE;
        }

        if ($current >= $target || ($state['currentTransition'] ?? null) !== UpgradePlan::transitionLabel($current, $current + 1)) {
            $style->error('state.json does not describe a valid active one-major transition.');

            return Command::FAILURE;
        }

        try {
            $plan = new UpgradePlan($current, $target, false, $fromStep, $skipSteps);
        } catch (InvalidArgumentException $exception) {
            $style->error('The upgrade journal cannot be resumed: '.$exception->getMessage());

            return Command::FAILURE;
        }

        $overrides = $this->overrides($input);
        $style->title(sprintf('Resuming Laravel %d → Laravel %d', $current, $target));

        if (! (bool) $input->getOption('no-interaction') && $input->isInteractive()) {
            if (! $style->confirm(sprintf('Resume the Laravel %d upgrade now?', $target), true)) {
                $style->note('Aborted by user.');

                return Command::SUCCESS;
            }
        }

        try {
            $result = $this->runtime->run(
                $plan,
                $directory,
                $overrides,
                new ConsoleUpgradeObserver($output),
            );
        } catch (\Throwable $exception) {
            $style->error('Upgrade continuation could not be started: '.$exception->getMessage());

            return Command::FAILURE;
        }

        if (! $result->success) {
            $style->error($result->failureMessage ?? 'Upgrade continuation failed.');

            return $result->exitCode > 0 ? $result->exitCode : Command::FAILURE;
        }

        $style->success(sprintf('Upgrade to Laravel %d complete.', $target));

        return Command::SUCCESS;
    }

    private function workingDirectory(InputInterface $input): ?string
    {
        $value = $input->getOption('working-dir');
        $directory = is_string($value) && $value !== '' ? $value : '.';
        $resolved = realpath($directory);

        return $resolved !== false && is_dir($resolved) ? $resolved : null;
    }

    /** @return array<string, mixed> */
    private function overrides(InputInterface $input): array
    {
        $overrides = [];

        if ((bool) $input->getOption('no-tests') || (bool) $input->getOption('skip-tests')) {
            $overrides['noTests'] = true;
        }

        if ((bool) $input->getOption('no-pint')) {
            $overrides['noPint'] = true;
            $overrides['pint'] = false;
        }

        if ((bool) $input->getOption('no-interaction')) {
            $overrides['noInteraction'] = true;
        }

        if ((bool) $input->getOption('no-git')) {
            $overrides['noGit'] = true;
            $overrides['git'] = false;
        }

        if ((bool) $input->getOption('allow-dirty')) {
            $overrides['allowDirty'] = true;
        }

        if ((bool) $input->getOption('no-install')) {
            $overrides['noInstall'] = true;
        }

        if ((bool) $input->getOption('annotate')) {
            $overrides['annotate'] = true;
        }

        $composer = $input->getOption('composer');

        if (is_string($composer) && $composer !== '') {
            $overrides['composerBinary'] = $composer;
        }

        return $overrides;
    }
}
