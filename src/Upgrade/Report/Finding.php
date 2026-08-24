<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report;

/**
 * One upgrade advisory finding (plan P3-01, Appendix D schema).
 * Immutable value object; collected across steps into report.json.
 */
final class Finding
{
    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_INFO = 'info';

    public function __construct(
        public readonly string $id,
        public readonly string $ruleId,
        public readonly string $severity,
        public readonly int $laravelVersion,
        public readonly string $file,
        public readonly int $line,
        public readonly string $message,
        public readonly string $action,
        public readonly string $guideUrl,
        public readonly bool $autoFixed = false,
        public readonly string $confidence = 'high',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ruleId' => $this->ruleId,
            'severity' => $this->severity,
            'laravelVersion' => $this->laravelVersion,
            'file' => $this->file,
            'line' => $this->line,
            'message' => $this->message,
            'action' => $this->action,
            'guideUrl' => $this->guideUrl,
            'autoFixed' => $this->autoFixed,
            'confidence' => $this->confidence,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            ruleId: (string) ($data['ruleId'] ?? ''),
            severity: (string) ($data['severity'] ?? self::SEVERITY_MEDIUM),
            laravelVersion: (int) ($data['laravelVersion'] ?? 0),
            file: (string) ($data['file'] ?? ''),
            line: (int) ($data['line'] ?? 0),
            message: (string) ($data['message'] ?? ''),
            action: (string) ($data['action'] ?? ''),
            guideUrl: (string) ($data['guideUrl'] ?? ''),
            autoFixed: (bool) ($data['autoFixed'] ?? false),
            confidence: (string) ($data['confidence'] ?? 'high'),
        );
    }
}
