<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console;

use JsonException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeRunResult;
use RuntimeException;

/** Writes the sole project artifact permitted by plan mode. */
final class PlanFileWriter
{
    public function write(
        string $workingDirectory,
        UpgradePlan $plan,
        UpgradeRunResult $result,
    ): string {
        $directory = rtrim($workingDirectory, '/\\').'/.laravel-upgrade';

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the plan output directory.');
        }

        $steps = [];

        foreach ($result->stepResults as $execution) {
            $steps[] = [
                'transition' => $execution->transition,
                'fromMajor' => $execution->fromMajor,
                'toMajor' => $execution->toMajor,
                'step' => $execution->step,
                'status' => $execution->result->status,
                'message' => $execution->result->message,
                'changedFiles' => $execution->result->changedFiles,
                'findingsCount' => $execution->result->findingsCount,
                'data' => $execution->result->data,
            ];
        }

        try {
            $contents = json_encode([
                'schemaVersion' => 1,
                'generatedAt' => gmdate(DATE_ATOM),
                'currentMajor' => $plan->currentMajor,
                'targetMajor' => $plan->targetMajor,
                'transitions' => $plan->transitions(),
                'steps' => $steps,
                'success' => $result->success,
                'exitCode' => $result->exitCode,
                'failure' => $result->failureMessage,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not encode the upgrade plan.', 0, $exception);
        }

        $path = $directory.'/plan.json';
        $temporary = tempnam($directory, 'plan-');

        if ($temporary === false) {
            throw new RuntimeException('Could not create an atomic plan file.');
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents) || ! rename($temporary, $path)) {
                throw new RuntimeException('Could not atomically write the upgrade plan.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return $path;
    }
}
