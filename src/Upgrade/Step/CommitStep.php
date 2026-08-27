<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Git\GitCheckpointService;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\StepExecutionResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
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
        $transition = UpgradePlan::transitionLabel($context->fromMajor(), $context->toMajor());
        $prepared = StepResult::successful(
            message: 'Final upgrade report prepared before the git checkpoint.',
            data: ['reportState' => 'prepared'],
        );

        try {
            // The report must be complete before git stages it. The runner
            // therefore treats this step as report-owned and does not rewrite
            // it after a successful finalization.
            $this->reportGenerator->recordStep($context, new StepExecutionResult(
                transition: $transition,
                fromMajor: $context->fromMajor(),
                toMajor: $context->toMajor(),
                step: 'commit',
                result: $prepared,
            ));
            $report = $this->reportGenerator->generate($context);
            $this->reportGenerator->recordStep($context, new StepExecutionResult(
                transition: $transition,
                fromMajor: $context->fromMajor(),
                toMajor: $context->toMajor(),
                step: 'commit',
                result: StepResult::successful(
                    message: 'Final upgrade report prepared before the git checkpoint.',
                    data: ['reportState' => 'prepared', 'reportGeneration' => $report],
                ),
            ));
        } catch (\Throwable $exception) {
            return StepResult::failed(
                message: 'Upgrade report generation failed: '.$exception->getMessage(),
                data: ['check' => 'report-generation', 'reportHandled' => true],
                exitCode: 1,
            );
        }

        $result = $this->git->finalize($context);

        if ($result->isFailed()) {
            $failure = StepResult::failed(
                message: $result->message,
                data: [
                    'git' => $result->data,
                    'reportGeneration' => $report,
                    'reportHandled' => true,
                ],
                exitCode: $result->exitCode ?? 1,
            );
            $this->recordFinalResult($context, $transition, $failure);

            return $failure;
        }

        if ($result->isSkipped()) {
            $skipped = StepResult::skipped(
                message: $result->message,
                data: [
                    'git' => $result->data,
                    'reportGeneration' => $report,
                    'reportHandled' => true,
                ],
            );
            $this->recordFinalResult($context, $transition, $skipped);

            return $skipped;
        }

        return StepResult::successful(
            message: $result->message,
            data: [
                'git' => $result->data,
                'reportGeneration' => $report,
                'reportHandled' => true,
            ],
        );
    }

    private function recordFinalResult(UpgradeContext $context, string $transition, StepResult $result): void
    {
        try {
            $this->reportGenerator->recordStep($context, new StepExecutionResult(
                transition: $transition,
                fromMajor: $context->fromMajor(),
                toMajor: $context->toMajor(),
                step: 'commit',
                result: $result,
            ));
        } catch (\Throwable) {
            // The original finalization result is authoritative. The runner
            // journals it, while report persistence remains best effort here.
        }
    }
}
