<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

use InvalidArgumentException;

/**
 * Immutable outcome returned by a step.
 */
final class StepResult
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /**
     * @param  list<string>  $changedFiles
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $status = self::STATUS_SUCCESS,
        public readonly array $changedFiles = [],
        public readonly int $findingsCount = 0,
        public readonly string $message = '',
        public readonly array $data = [],
        public readonly ?int $exitCode = null,
    ) {
        if (! in_array($status, [self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_SKIPPED], true)) {
            throw new InvalidArgumentException(sprintf('Unknown step result status "%s".', $status));
        }

        if ($findingsCount < 0) {
            throw new InvalidArgumentException('A step finding count cannot be negative.');
        }

        if ($exitCode !== null && $exitCode < 1) {
            throw new InvalidArgumentException('A step exit code must be positive.');
        }
    }

    /**
     * @param  list<string>  $changedFiles
     * @param  array<string, mixed>  $data
     */
    public static function successful(
        array $changedFiles = [],
        int $findingsCount = 0,
        string $message = '',
        array $data = [],
        ?int $exitCode = null,
    ): self {
        return new self(self::STATUS_SUCCESS, $changedFiles, $findingsCount, $message, $data, $exitCode);
    }

    /**
     * @param  list<string>  $changedFiles
     * @param  array<string, mixed>  $data
     */
    public static function failed(
        string $message = '',
        array $changedFiles = [],
        int $findingsCount = 0,
        array $data = [],
        ?int $exitCode = null,
    ): self {
        return new self(self::STATUS_FAILED, $changedFiles, $findingsCount, $message, $data, $exitCode);
    }

    /**
     * @param  list<string>  $changedFiles
     * @param  array<string, mixed>  $data
     */
    public static function skipped(
        string $message = '',
        array $changedFiles = [],
        int $findingsCount = 0,
        array $data = [],
        ?int $exitCode = null,
    ): self {
        return new self(self::STATUS_SKIPPED, $changedFiles, $findingsCount, $message, $data, $exitCode);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isSuccess(): bool
    {
        return $this->isSuccessful();
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }
}
