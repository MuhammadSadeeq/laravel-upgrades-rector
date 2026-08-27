<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Git\GitCheckpointService;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\UpgradeReportGenerator;

/**
 * Creates the final report checkpoint commit when git safety is enabled.
 *
 * The report is materialised before the final checkpoint so a normal apply
 * run always has a report available for GitCheckpointService to stage.
 */
final class CommitStep implements StepInterface
{
    public function __construct(
        private readonly GitCheckpointService $git,
        private readonly UpgradeReportGenerator $reportGenerator = new UpgradeReportGenerator,
    ) {}

    public function name(): string
    {
        return 'commit';
    }

    public function execute(UpgradeContext $context): StepResult
    {
        try {
            $report = $this->reportGenerator->generate($context);
        } catch (\Throwable $exception) {
            return StepResult::failed(
                message: 'Upgrade report generation failed: '.$exception->getMessage(),
                data: ['check' => 'report-generation'],
                exitCode: 1,
            );
        }

        $result = $this->git->finalize($context);

        if ($result->isFailed()) {
            return StepResult::failed(
                message: $result->message,
                data: [
                    'git' => $result->data,
                    'reportGeneration' => $report,
                ],
                exitCode: $result->exitCode ?? 1,
            );
        }

        if ($result->isSkipped()) {
            return StepResult::skipped(
                message: $result->message,
                data: [
                    'git' => $result->data,
                    'reportGeneration' => $report,
                ],
            );
        }

        return StepResult::successful(
            message: $result->message,
            data: [
                'git' => $result->data,
                'reportGeneration' => $report,
            ],
        );
    }
}
