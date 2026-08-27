<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Git\GitCheckpointService;

/**
 * Creates the final report checkpoint commit when git safety is enabled.
 *
 * ReportWriter remains the owner of report generation; this boundary stages
 * an already-generated UPGRADE-REPORT.md and records that contract in the
 * result instead of inventing report contents without the run's findings.
 */
final class CommitStep implements StepInterface
{
    public function __construct(private readonly GitCheckpointService $git) {}

    public function name(): string
    {
        return 'commit';
    }

    public function execute(UpgradeContext $context): StepResult
    {
        $result = $this->git->finalize($context);

        if ($result->isFailed()) {
            return StepResult::failed(
                message: $result->message,
                data: [
                    'git' => $result->data,
                    'reportGeneration' => 'ReportWriter must generate UPGRADE-REPORT.md before the final checkpoint.',
                ],
                exitCode: $result->exitCode ?? 1,
            );
        }

        if ($result->isSkipped()) {
            return StepResult::skipped(
                message: $result->message,
                data: [
                    'git' => $result->data,
                    'reportGeneration' => 'ReportWriter must generate UPGRADE-REPORT.md before the final checkpoint.',
                ],
            );
        }

        return StepResult::successful(
            message: $result->message,
            data: [
                'git' => $result->data,
                'reportGeneration' => 'ReportWriter generated UPGRADE-REPORT.md before the final checkpoint.',
            ],
        );
    }
}
