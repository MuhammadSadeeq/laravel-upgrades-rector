<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ComposerProcessAdapter;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ConstraintPlanner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\DependencyDecision;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ManifestReader;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use Throwable;

/** Computes and, when requested, applies Composer dependency decisions. */
final class DependencyStep implements StepInterface
{
    public function __construct(
        private readonly ConstraintPlanner $planner,
        private readonly ComposerProcessAdapter $composer,
        private readonly ManifestReader $manifestReader = new ManifestReader,
    ) {}

    public function name(): string
    {
        return 'dependencies';
    }

    public function execute(UpgradeContext $context): StepResult
    {
        try {
            $manifest = $this->manifestReader->readComposerJson($context->workingDirectory);
            $lockedPackages = $this->manifestReader->readLockedPackages($context->workingDirectory);
        } catch (Throwable $exception) {
            return $this->failure('Could not read Composer metadata: '.$exception->getMessage());
        }

        $decisions = $this->planner->planAll($context->toMajor(), $manifest, $lockedPackages);
        $decisionData = array_map(
            static fn (DependencyDecision $decision): array => $decision->toArray(),
            $decisions,
        );

        $composerBinary = $this->composerBinary($context);

        if ($context->isPlanMode()) {
            return $this->previewPlan(
                $context,
                $decisions,
                $decisionData,
                $composerBinary,
            );
        }

        $processes = [];
        $removeBySection = ['require' => [], 'require-dev' => []];

        foreach ($decisions as $decision) {
            if ($decision->action === DependencyDecision::ACTION_REMOVE) {
                $removeBySection[$decision->section][] = $decision->package;
            }
        }

        foreach ($removeBySection as $section => $packages) {
            if ($packages === []) {
                continue;
            }

            try {
                $results = $this->composer->removePackages(
                    $context->workingDirectory,
                    $packages,
                    $section === 'require-dev',
                    $composerBinary,
                );
            } catch (Throwable $exception) {
                return $this->failure('Composer dependency removal failed: '.$exception->getMessage(), $decisionData, $processes);
            }

            foreach ($results as $result) {
                $processes[] = $this->processData($result);

                if (! $result->isSuccessful()) {
                    return $this->failure('Composer dependency removal failed.', $decisionData, $processes);
                }
            }
        }

        foreach ($decisions as $decision) {
            if ($decision->action !== DependencyDecision::ACTION_BUMP || $decision->proposed === null) {
                continue;
            }

            try {
                $results = $this->composer->requirePackages(
                    $context->workingDirectory,
                    [$decision->package => $decision->proposed],
                    $decision->section === 'require-dev',
                    $composerBinary,
                );
            } catch (Throwable $exception) {
                return $this->failure('Composer dependency update failed: '.$exception->getMessage(), $decisionData, $processes);
            }

            foreach ($results as $result) {
                $processes[] = $this->processData($result);

                if (! $result->isSuccessful()) {
                    return $this->failure('Composer dependency update failed.', $decisionData, $processes);
                }
            }
        }

        try {
            $validation = $this->composer->validate($context->workingDirectory, $composerBinary);
            $processes[] = $this->processData($validation);

            if (! $validation->isSuccessful()) {
                return $this->failure('composer.json failed after dependency changes.', $decisionData, $processes);
            }

            $solver = $this->composer->solverDryRun($context->workingDirectory, $composerBinary);
            $processes[] = $this->processData($solver);

            if (! $solver->isSuccessful()) {
                return $this->failure('Composer solver dry-run failed.', $decisionData, $processes);
            }
        } catch (Throwable $exception) {
            return $this->failure('Composer dependency validation failed: '.$exception->getMessage(), $decisionData, $processes);
        }

        return StepResult::successful(
            message: 'Dependency decisions applied.',
            data: ['decisions' => $decisionData, 'processes' => $processes],
        );
    }

    /**
     * Plan mode previews bumps through Composer's non-mutating require
     * operation. Dev requirements need their own invocation because Composer
     * applies --dev to the entire require request. Removals are reported but
     * intentionally not passed to the solver preview.
     *
     * @param  list<DependencyDecision>  $decisions
     * @param  list<array<string, mixed>>  $decisionData
     */
    private function previewPlan(
        UpgradeContext $context,
        array $decisions,
        array $decisionData,
        ?string $composerBinary,
    ): StepResult {
        $bumps = ['require' => [], 'require-dev' => []];
        $removals = [];

        foreach ($decisions as $decision) {
            if ($decision->action === DependencyDecision::ACTION_BUMP && $decision->proposed !== null) {
                $bumps[$decision->section][$decision->package] = $decision->proposed;
            }

            if ($decision->action === DependencyDecision::ACTION_REMOVE) {
                $removals[] = $decision->package;
            }
        }

        $processes = [];

        foreach ($bumps as $section => $constraints) {
            if ($constraints === []) {
                continue;
            }

            try {
                $preview = $this->composer->previewRequirements(
                    $context->workingDirectory,
                    $constraints,
                    $section === 'require-dev',
                    $composerBinary,
                );
            } catch (Throwable $exception) {
                return $this->failure(
                    'Composer dependency preview failed: '.$exception->getMessage(),
                    $decisionData,
                    $processes,
                    ['notSolverPreviewed' => ['removals' => $removals]],
                );
            }

            $processes[] = $this->processData($preview);

            if (! $preview->isSuccessful()) {
                return $this->failure(
                    'Composer dependency preview failed.',
                    $decisionData,
                    $processes,
                    ['notSolverPreviewed' => ['removals' => $removals]],
                );
            }
        }

        if ($processes === [] && $context->option('solverDryRun', false) === true) {
            try {
                $solver = $this->composer->solverDryRun($context->workingDirectory, $composerBinary);
            } catch (Throwable $exception) {
                return $this->failure('Composer solver dry-run failed: '.$exception->getMessage(), $decisionData);
            }

            $processes[] = $this->processData($solver);

            if (! $solver->isSuccessful()) {
                return $this->failure('Composer solver dry-run failed.', $decisionData, $processes);
            }
        }

        return StepResult::successful(
            message: 'Dependency decisions calculated; composer.json was not changed.',
            data: [
                'decisions' => $decisionData,
                'processes' => $processes,
                'notSolverPreviewed' => ['removals' => $removals],
            ],
        );
    }

    private function composerBinary(UpgradeContext $context): ?string
    {
        $binary = $context->option('composerBinary');

        return is_string($binary) && $binary !== '' ? $binary : null;
    }

    /**
     * @param  list<array<string, mixed>>  $decisions
     * @param  list<array<string, mixed>>  $processes
     * @param  array<string, mixed>  $extra
     */
    private function failure(
        string $message,
        array $decisions = [],
        array $processes = [],
        array $extra = [],
    ): StepResult {
        return StepResult::failed(
            message: $message,
            data: array_merge(['decisions' => $decisions, 'processes' => $processes], $extra),
            exitCode: 3,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function processData(ProcessResult $result): array
    {
        return [
            'command' => $result->arguments,
            'exitCode' => $result->exitCode,
            'output' => $result->combinedOutput(),
        ];
    }
}
