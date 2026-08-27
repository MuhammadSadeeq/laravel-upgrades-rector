<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\SkeletonStep;
use Throwable;

/** Runs the existing skeleton/config synchronizer for one major transition. */
final class SkeletonSyncStep implements StepInterface
{
    public function __construct(private readonly SkeletonStep $skeleton = new SkeletonStep) {}

    public function name(): string
    {
        return 'skeleton';
    }

    public function execute(UpgradeContext $context): StepResult
    {
        $structureOption = $context->option('structure', 'keep');
        $structure = is_string($structureOption) && in_array($structureOption, ['keep', 'modern'], true)
            ? $structureOption
            : 'keep';
        $forceConfig = $context->option('forceConfig', $context->option('force-config', false)) === true;
        $collector = new FindingCollector;

        try {
            $sync = $this->skeleton->syncProject(
                $context->workingDirectory,
                $context->fromMajor(),
                $context->toMajor(),
                $collector,
                $context->isPlanMode(),
                $structure,
            );
        } catch (Throwable $exception) {
            return StepResult::failed(
                message: 'Skeleton synchronization failed: '.$exception->getMessage(),
                data: ['structure' => $structure, 'forceConfig' => $forceConfig],
                exitCode: 4,
            );
        }

        if ($sync['conflicts'] !== []) {
            return StepResult::failed(
                message: sprintf(
                    'Skeleton synchronization has unresolved conflicts: %s.',
                    implode(', ', $sync['conflicts']),
                ),
                changedFiles: $sync['changed'],
                findingsCount: $collector->count(),
                data: [
                    'sync' => $sync,
                    'findings' => $collector->all(),
                    'structure' => $structure,
                    'forceConfig' => $forceConfig,
                ],
                exitCode: 4,
            );
        }

        return StepResult::successful(
            changedFiles: $sync['changed'],
            findingsCount: $collector->count(),
            message: $context->isPlanMode()
                ? 'Skeleton synchronization previewed; project files were not changed.'
                : 'Skeleton synchronization completed.',
            data: [
                'sync' => $sync,
                'findings' => $collector->all(),
                'structure' => $structure,
                'forceConfig' => $forceConfig,
            ],
        );
    }
}
