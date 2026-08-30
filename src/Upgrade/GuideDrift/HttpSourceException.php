<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift;

use RuntimeException;
use Throwable;

/**
 * Structured failure from an HTTP source.
 *
 * Callers must use the status/transient fields for retry policy. The message
 * remains useful to humans, but is deliberately not a machine protocol.
 *
 * @phpstan-type NormalizedHeaders array<string, string>
 */
final class HttpSourceException extends RuntimeException
{
    /**
     * @param  array<string, string>  $headers  Lower-case header names.
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly array $headers = [],
        public readonly bool $transient = false,
        public readonly ?string $transientReason = null,
        public readonly ?string $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
