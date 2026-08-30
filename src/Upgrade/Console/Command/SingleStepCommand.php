<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

use InvalidArgumentException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\ProjectVersionDetector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\SingleStepRuntimeInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\UpgradeRuntimeFactory;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\SupportPolicy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Shared target validation and option handling for engine-level commands. */
abstract class SingleStepCommand extends Command
{
    public function __construct(
        private readonly SingleStepRuntimeInterface $runtime = new UpgradeRuntimeFactory,
        private readonly ProjectVersionDetector $versionDetector = new ProjectVersionDetector,
        ?SupportPolicy $supportPolicy = null,
    ) {
        parent::__construct();
        $this->supportPolicy = $supportPolicy ?? SupportPolicy::default();
    }

    private readonly SupportPolicy $supportPolicy;

    abstract protected function stepName(): string;

    abstract protected function commandDescription(): string;

    protected function configure(): void
    {
        $this
            ->setName($this->commandName())
            ->setDescription($this->commandDescription())
            ->addArgument('target-major', InputArgument::REQUIRED, 'Target Laravel major version');

        $this->addEngineOptions();
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
            $style->error(sprintf(
                'Target major must be one of: %s.',
                implode(', ', $this->supportPolicy->targetMajors()),
            ));

            return Command::FAILURE;
        }

        $planMode = (bool) $input->getOption('plan') || (bool) $input->getOption('dry-run');
        $structure = $this->stringOption($input, 'structure', 'keep');

        if (! in_array($structure, ['keep', 'modern'], true)) {
            $style->error('Structure must be either keep or modern.');

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

        if ($structure === 'modern' && ($detected->major !== 10 || $targetMajor !== 11)) {
            $style->error('Modern structure mode is supported only for the Laravel 10 to 11 transition.');

            return Command::FAILURE;
        }

        if ($targetMajor !== $detected->major + 1) {
            $style->error(sprintf(
                'Engine commands run one major at a time; detected Laravel %d, requested Laravel %d.',
                $detected->major,
                $targetMajor,
            ));

            return Command::FAILURE;
        }

        try {
            $plan = new UpgradePlan($detected->major, $targetMajor, $planMode, supportPolicy: $this->supportPolicy);
            $result = $this->runtime->runStep(
                $this->stepName(),
                $plan,
                $workingDirectory,
                $this->runOptions($input, $planMode, $structure, $constraintPolicy),
            );
        } catch (InvalidArgumentException $exception) {
            $style->error($exception->getMessage());

            return Command::FAILURE;
        } catch (\Throwable $exception) {
            $style->error(sprintf('%s step could not be started: %s', $this->stepName(), $exception->getMessage()));

            return $this->failureCode();
        }

        $this->renderResult($style, $result, $planMode, $targetMajor);

        if ($result->isFailed()) {
            // Process exit codes are retained in StepResult data for reports,
            // but engine commands expose the documented stable code per step.
            return $this->failureCode();
        }

        return Command::SUCCESS;
    }

    protected function commandName(): string
    {
        return $this->stepName();
    }

    protected function failureCode(): int
    {
        return match ($this->stepName()) {
            'preflight' => 2,
            'dependencies' => 3,
            'skeleton' => 4,
            'verify' => 1,
            default => 1,
        };
    }

    private function addEngineOptions(): void
    {
        $this
            ->addOption('plan', null, InputOption::VALUE_NONE, 'Preview this step without changing project files')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compatibility alias for --plan')
            ->addOption('working-dir', 'd', InputOption::VALUE_REQUIRED, 'Project directory', '.')
            ->addOption('composer', null, InputOption::VALUE_REQUIRED, 'Composer binary or project-contained path')
            ->addOption('no-tests', null, InputOption::VALUE_NONE, 'Skip artisan test verification')
            ->addOption('skip-tests', null, InputOption::VALUE_NONE, 'Compatibility alias for --no-tests')
            ->addOption('no-pint', null, InputOption::VALUE_NONE, 'Do not run Pint after Rector')
            ->addOption('annotate', null, InputOption::VALUE_NONE, 'Write advisory TODO comments when applying advisories')
            ->addOption('constraint-policy', null, InputOption::VALUE_REQUIRED, 'Dependency constraint policy (replace or widen)', 'replace')
            ->addOption('structure', null, InputOption::VALUE_REQUIRED, 'Skeleton structure mode (keep or modern)', 'keep')
            ->addOption('slim-config', null, InputOption::VALUE_NONE, 'Remove only config files identical to the Laravel 10 skeleton in modern mode')
            ->addOption('no-install', null, InputOption::VALUE_NONE, 'Do not install dependencies')
            ->addOption('no-git', null, InputOption::VALUE_NONE, 'Disable git safety for this standalone step')
            ->addOption('allow-dirty', null, InputOption::VALUE_NONE, 'Allow a dirty worktree for this standalone step')
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Do not ask for confirmation');
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

        if (! is_scalar($value) || preg_match('/^\d+$/', (string) $value) !== 1) {
            return null;
        }

        $major = (int) $value;

        return $this->supportPolicy->isSupportedTarget($major) ? $major : null;
    }

    /** @return array<string, mixed> */
    private function runOptions(InputInterface $input, bool $planMode, string $structure, string $constraintPolicy): array
    {
        $composer = $input->getOption('composer');
        $composer = is_string($composer) && $composer !== '' ? $composer : null;
        $noTests = (bool) $input->getOption('no-tests') || (bool) $input->getOption('skip-tests');

        return [
            'git' => false,
            'noGit' => true,
            'allowDirty' => (bool) $input->getOption('allow-dirty'),
            'noInstall' => (bool) $input->getOption('no-install'),
            'noTests' => $noTests,
            'noPint' => (bool) $input->getOption('no-pint'),
            'pint' => ! (bool) $input->getOption('no-pint'),
            'annotate' => (bool) $input->getOption('annotate') && ! $planMode,
            'constraintPolicy' => $constraintPolicy,
            'structure' => $structure,
            'slimConfig' => (bool) $input->getOption('slim-config'),
            'noInteraction' => (bool) $input->getOption('no-interaction'),
            'composerBinary' => $composer,
            'clearCache' => true,
        ];
    }

    private function stringOption(InputInterface $input, string $name, string $default): string
    {
        $value = $input->getOption($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function renderResult(SymfonyStyle $style, StepResult $result, bool $planMode, int $targetMajor): void
    {
        if ($result->isSkipped()) {
            $style->note(sprintf('%s step skipped: %s', $this->stepName(), $result->message));

            return;
        }

        if ($result->isFailed()) {
            $style->error($result->message !== '' ? $result->message : sprintf('%s step failed.', $this->stepName()));

            return;
        }

        $style->success(sprintf(
            'Laravel %d %s step %s.',
            $targetMajor,
            $this->stepName(),
            $planMode ? 'preview completed' : 'completed',
        ));

        if ($result->changedFiles !== []) {
            $style->text(sprintf('Changed files: %d', count($result->changedFiles)));
        }

        if ($result->findingsCount > 0) {
            $style->text(sprintf('Findings: %d', $result->findingsCount));
        }

        if ($result->message !== '') {
            $style->text($result->message);
        }
    }
}
