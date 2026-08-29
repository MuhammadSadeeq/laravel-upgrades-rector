<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

use MuhammadSadeeq\LaravelUpgradesRector\Support\Compat\CompatFileNotFoundException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\CompatibilityMatrix;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ComposerCli;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ConstraintPlanner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\DependencyDecision;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ManifestReader;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\PackageGuideAnalyzer;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\PackageGuideCatalog;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Computes and applies the dependency changes required to move a project to
 * a target Laravel major. Replaces the former UpdateComposerDependencies*
 * Rector rules, which rewrote composer.json from inside node visitors.
 */
final class DepsCommand extends Command
{
    /**
     * @var string|null
     */
    protected static $defaultName = 'deps';

    /**
     * @var string|null
     */
    protected static $defaultDescription = 'Compute (or apply) composer.json dependency changes for a Laravel major upgrade';

    private const SUPPORTED_MAJORS = [11, 12, 13];

    protected function configure(): void
    {
        $this
            ->setName('deps')
            ->setDescription('Compute (or apply) composer.json dependency changes for a Laravel major upgrade')
            ->addArgument('target-major', InputArgument::REQUIRED, 'Target Laravel major version (e.g. 11)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE,
                'Print the planned commands without touching anything')
            ->addOption('working-dir', 'd', InputOption::VALUE_REQUIRED, 'Project directory', '.')
            ->setHelp(
                <<<'HELP'
Reads the project's composer.json, decides per direct dependency whether it
already admits versions that support the target major, must be bumped, or can
be removed. Applies decisions through the Composer CLI so formatting is kept.

Examples:
  vendor/bin/laravel-upgrade deps 11 --dry-run   # print plan only, write nothing
  vendor/bin/laravel-upgrade deps 11             # edit composer.json + validate
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $workingDirectoryOption = $input->getOption('working-dir');
        $workingDirectory = is_string($workingDirectoryOption) && $workingDirectoryOption !== ''
            ? $workingDirectoryOption
            : '.';

        $dryRun = (bool) $input->getOption('dry-run');

        $targetArgument = $input->getArgument('target-major');
        $targetArgumentString = is_scalar($targetArgument) ? (string) $targetArgument : '';

        $targetMajor = $this->resolveTargetMajor($targetArgumentString);

        if ($targetMajor === null) {
            $style->error(sprintf(
                'Unsupported target major "%s". Supported: %s.',
                $targetArgumentString,
                implode(', ', self::SUPPORTED_MAJORS)
            ));

            return Command::FAILURE;
        }

        try {
            return $this->runDeps($style, $workingDirectory, $targetMajor, $dryRun);
        } catch (CompatFileNotFoundException $exception) {
            $style->error($exception->getMessage());

            return Command::FAILURE;
        } catch (ProcessFailedException $exception) {
            // A require/remove step failed mid-apply: the manifest may already
            // be partially edited. Report the captured output instead of a raw
            // stack trace so the user can fix and re-run.
            $style->error(sprintf(
                "A Composer command failed while applying decisions:\n%s\n\n"
                ."The manifest may be partially edited — fix the cause and run `deps %d` again.\n",
                $this->indent($exception->getMessage()),
                $targetMajor
            ));

            return 3; // dependency resolution failure
        } catch (\RuntimeException $exception) {
            $style->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function runDeps(SymfonyStyle $style, string $workingDirectory, int $targetMajor, bool $dryRun): int
    {
        $reader = new ManifestReader;
        $manifest = $reader->readComposerJson($workingDirectory);
        $lockedPackages = $reader->readLockedPackages($workingDirectory);

        $packageJsonPath = dirname(__DIR__, 4).'/resources/compat/packages.json';
        $removalsJsonPath = dirname(__DIR__, 4).'/resources/compat/removals.json';

        $planner = new ConstraintPlanner(
            new CompatibilityMatrix($packageJsonPath),
            $removalsJsonPath
        );

        /** @var list<DependencyDecision> $decisions */
        $decisions = $planner->planAll($targetMajor, $manifest, $lockedPackages);
        $guideAnalysis = (new PackageGuideAnalyzer(new PackageGuideCatalog(
            dirname(__DIR__, 4).'/resources/compat/package-guides.json',
        )))->analyze($decisions, $targetMajor, $workingDirectory);

        if ($decisions === []) {
            $style->warning('composer.json declares no dependencies — nothing to do.');

            return Command::SUCCESS;
        }

        $this->renderDecisions($style, $targetMajor, $decisions);
        $this->renderPackageGuides($style, $guideAnalysis->guides);

        $bumps = array_filter(
            $decisions,
            static fn (DependencyDecision $decision): bool => $decision->action === DependencyDecision::ACTION_BUMP
        );
        $removals = array_filter(
            $decisions,
            static fn (DependencyDecision $decision): bool => $decision->action === DependencyDecision::ACTION_REMOVE
        );

        if ($bumps === [] && $removals === []) {
            $style->success('All dependencies already admit the target major.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $style->section('Commands that would run');
            $this->renderPlannedCommands($style, $bumps, $removals);
            $style->note('Dry run — nothing was written.');

            return Command::SUCCESS;
        }

        $composerCli = new ComposerCli($workingDirectory);

        $this->applyDecisions($composerCli, $bumps, $removals);

        $validation = $composerCli->validate();

        if ($validation->isSuccessful()) {
            $style->success('composer validate --strict passed.');
        } else {
            $style->error("composer validate --strict failed:\n".$this->indent($validation->output));
        }

        $solverResult = $composerCli->updateDryRun();

        if ($solverResult->isSuccessful()) {
            $style->success('Dependency solver succeeded (composer update --dry-run -W).');
        } else {
            $style->error("Dependency solver failed:\n".$this->indent($this->trimSolverOutput($solverResult->output)));

            $whyNotFramework = $composerCli->whyNot('laravel/framework', '^'.$targetMajor.'.0');

            if (! $whyNotFramework->isSuccessful() || trim($whyNotFramework->output) !== '') {
                $style->warning(
                    "composer why-not laravel/framework ^{$targetMajor}.0:\n"
                    .$this->indent($this->trimSolverOutput($whyNotFramework->output))
                );
            }

            return 3; // dependency resolution failure
        }

        return $validation->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param  iterable<DependencyDecision>  $bumps
     * @param  iterable<DependencyDecision>  $removals
     */
    private function applyDecisions(ComposerCli $composerCli, iterable $bumps, iterable $removals): void
    {
        $requireConstraints = ['require' => [], 'require-dev' => []];
        $removePackages = ['require' => [], 'require-dev' => []];

        foreach ($bumps as $decision) {
            $requireConstraints[$decision->section][$decision->package] = (string) $decision->proposed;
        }

        foreach ($removals as $decision) {
            $removePackages[$decision->section][] = $decision->package;
        }

        foreach (['require' => false, 'require-dev' => true] as $section => $dev) {
            if ($removePackages[$section] !== []) {
                $composerCli->removePackages($removePackages[$section], $dev);
            }

            if ($requireConstraints[$section] !== []) {
                $composerCli->requirePackages($requireConstraints[$section], $dev);
            }
        }
    }

    /**
     * @param  iterable<DependencyDecision>  $bumps
     * @param  iterable<DependencyDecision>  $removals
     */
    private function renderPlannedCommands(SymfonyStyle $style, iterable $bumps, iterable $removals): void
    {
        $lines = [];

        foreach ($removals as $decision) {
            $devSuffix = $decision->section === 'require-dev' ? ' --dev' : '';
            $lines[] = sprintf('composer remove %s%s --no-update', escapeshellarg($decision->package), $devSuffix);
        }

        foreach ($bumps as $decision) {
            $devSuffix = $decision->section === 'require-dev' ? ' --dev' : '';
            $lines[] = sprintf(
                'composer require %s%s --no-update',
                escapeshellarg(sprintf('%s:%s', $decision->package, $decision->proposed)),
                $devSuffix
            );
        }

        $style->listing($lines);
    }

    /**
     * @param  list<DependencyDecision>  $decisions
     */
    private function renderDecisions(SymfonyStyle $style, int $targetMajor, array $decisions): void
    {
        $style->title(sprintf('Laravel %d dependency plan', $targetMajor));

        $rows = [];

        foreach ($decisions as $decision) {
            $rows[] = [
                $decision->package,
                $decision->section,
                $decision->current ?? '-',
                match ($decision->action) {
                    DependencyDecision::ACTION_BUMP => (string) $decision->proposed,
                    DependencyDecision::ACTION_KEEP => '(unchanged)',
                    DependencyDecision::ACTION_REMOVE => '(removed)',
                    DependencyDecision::ACTION_UNKNOWN => '?',
                    default => '?',
                },
                $decision->reason,
            ];
        }

        $style->table(['Package', 'Section', 'Current', 'Proposed', 'Reason'], $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $guides
     */
    private function renderPackageGuides(SymfonyStyle $style, array $guides): void
    {
        if ($guides === []) {
            return;
        }

        $style->section('Package upgrade guides');
        $rows = [];

        foreach ($guides as $guide) {
            $package = is_string($guide['package'] ?? null) ? $guide['package'] : 'package';
            $fromMajor = is_int($guide['fromMajor'] ?? null) ? (string) $guide['fromMajor'] : '?';
            $toMajor = is_int($guide['toMajor'] ?? null) ? (string) $guide['toMajor'] : '?';
            $guideMajor = is_int($guide['guideMajor'] ?? null) ? (string) $guide['guideMajor'] : '?';
            $url = is_string($guide['guideUrl'] ?? null) ? $guide['guideUrl'] : '';
            $items = is_int($guide['items'] ?? null) ? (string) $guide['items'] : '0';
            $componentCount = $guide['componentCount'] ?? null;
            $componentLabel = is_string($guide['componentLabel'] ?? null) ? $guide['componentLabel'] : 'components';
            $count = is_int($componentCount) ? sprintf('%d %s', $componentCount, $componentLabel) : '-';
            $messages = is_array($guide['messages'] ?? null) ? $guide['messages'] : [];
            $actions = is_array($guide['actions'] ?? null) ? $guide['actions'] : [];
            $advice = [];
            $status = is_string($guide['status'] ?? null) ? $guide['status'] : 'supported';
            $notes = is_string($guide['notes'] ?? null) ? $guide['notes'] : null;

            if ($status === 'future' && $notes !== null) {
                $advice[] = 'Manual/future guide: '.$notes;
            }

            foreach ($messages as $index => $message) {
                if (! is_string($message) || ! is_string($actions[$index] ?? null)) {
                    continue;
                }

                $advice[] = $message.' Action: '.$actions[$index];
            }

            $rows[] = [$package, $fromMajor.' → '.$toMajor, $guideMajor, $items, $count, implode("\n", $advice), $url];
        }

        $style->table(['Package', 'Crossing', 'Guide major', 'Items', 'Count', 'Advice', 'Guide'], $rows);
    }

    private function resolveTargetMajor(string $argument): ?int
    {
        if (preg_match('/^\d+$/', $argument) !== 1) {
            return null;
        }

        $major = (int) $argument;

        return in_array($major, self::SUPPORTED_MAJORS, true) ? $major : null;
    }

    private function indent(string $text): string
    {
        $lines = explode("\n", rtrim($text, "\n"));

        return implode("\n", array_map(static fn (string $line): string => '  '.$line, $lines));
    }

    private function trimSolverOutput(string $output): string
    {
        $lines = explode("\n", $output);
        $relevant = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));

        return implode("\n", array_slice($relevant, -30));
    }
}
