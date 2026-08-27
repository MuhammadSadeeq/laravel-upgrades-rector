<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Git;

/** Result of a git safety/checkpoint operation. */
final class GitCheckpointResult
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message = '',
        public readonly array $data = [],
        public readonly ?int $exitCode = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function successful(string $message = '', array $data = []): self
    {
        return new self(self::STATUS_SUCCESS, $message, $data);
    }

    /** @param array<string, mixed> $data */
    public static function skipped(string $message = '', array $data = []): self
    {
        return new self(self::STATUS_SKIPPED, $message, $data);
    }

    /** @param array<string, mixed> $data */
    public static function failed(string $message = '', array $data = [], int $exitCode = 1): self
    {
        return new self(self::STATUS_FAILED, $message, $data, $exitCode);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
