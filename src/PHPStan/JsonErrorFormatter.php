<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * JSON formatter used by AdvisoryStep so PHPStan rule metadata survives the
 * process boundary. PHPStan's built-in JSON formatter omits RuleError
 * metadata, including the confidence value used by upgrade findings.
 */
final class JsonErrorFormatter implements ErrorFormatter
{
    public function __construct(private readonly bool $pretty = false) {}

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        /** @var array<string, array{errors: int, messages: list<array<string, mixed>>}> $fileErrors */
        $fileErrors = [];
        $tipFormatter = new OutputFormatter(false);

        foreach ($analysisResult->getFileSpecificErrors() as $error) {
            $file = $error->getFile();

            if (! isset($fileErrors[$file])) {
                $fileErrors[$file] = ['errors' => 0, 'messages' => []];
            }

            $fileErrors[$file]['errors']++;
            $message = [
                'message' => $error->getMessage(),
                'line' => $error->getLine(),
                'ignorable' => $error->canBeIgnored(),
            ];

            if ($error->getTip() !== null) {
                $message['tip'] = $tipFormatter->format($error->getTip());
            }

            if ($error->getIdentifier() !== null) {
                $message['identifier'] = $error->getIdentifier();
            }

            // AnalysisResult wraps RuleErrors in Error objects. Keep this
            // method_exists guard for PHPStan 1.x compatibility, where the
            // metadata accessor was not present on every supported error.
            if (method_exists($error, 'getMetadata')) {
                $metadata = $error->getMetadata();

                if ($metadata !== []) {
                    $message['metadata'] = $metadata;
                }
            }

            $fileErrors[$file]['messages'][] = $message;
        }

        $errors = [
            'totals' => [
                'errors' => count($analysisResult->getNotFileSpecificErrors()),
                'file_errors' => count($analysisResult->getFileSpecificErrors()),
            ],
            'files' => (object) $fileErrors,
            'errors' => $analysisResult->getNotFileSpecificErrors(),
        ];

        $flags = $this->pretty ? JSON_PRETTY_PRINT : 0;
        $output->writeRaw((string) json_encode($errors, JSON_THROW_ON_ERROR | $flags));

        return $analysisResult->hasErrors() ? 1 : 0;
    }
}
