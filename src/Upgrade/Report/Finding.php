<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report;

/**
 * One upgrade advisory finding, as recorded in the upgrade report.
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
    ) {}

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
     * Return the one identity used when findings cross step/process/report
     * boundaries. Presentation fields are part of the identity so a later
     * transition cannot silently replace a finding with different guidance.
     */
    public function identity(): string
    {
        return implode("\0", [
            $this->ruleId,
            (string) $this->laravelVersion,
            $this->file,
            (string) $this->line,
            $this->message,
            $this->severity,
            $this->action,
            $this->guideUrl,
            $this->autoFixed ? '1' : '0',
            $this->confidence,
        ]);
    }

    /**
     * Build the canonical identity from report/JSONL data, including legacy
     * report arrays that called the major field `major`.
     *
     * @param  array<string, mixed>  $data
     */
    public static function identityFromArray(array $data): string
    {
        return self::fromArray($data)->identity();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? '';
        $ruleId = $data['ruleId'] ?? '';
        $severity = $data['severity'] ?? self::SEVERITY_MEDIUM;
        $laravelVersion = $data['laravelVersion'] ?? null;

        if (! is_int($laravelVersion)) {
            $laravelVersion = is_int($data['major'] ?? null) ? $data['major'] : 0;
        }
        $file = $data['file'] ?? '';
        $line = $data['line'] ?? 0;
        $message = $data['message'] ?? '';
        $action = $data['action'] ?? '';
        $guideUrl = $data['guideUrl'] ?? '';
        $autoFixed = $data['autoFixed'] ?? false;
        $confidence = $data['confidence'] ?? 'high';

        return new self(
            id: is_string($id) ? $id : '',
            ruleId: is_string($ruleId) ? $ruleId : '',
            severity: is_string($severity) ? $severity : self::SEVERITY_MEDIUM,
            laravelVersion: $laravelVersion,
            file: is_string($file) ? $file : '',
            line: is_int($line) ? $line : 0,
            message: is_string($message) ? $message : '',
            action: is_string($action) ? $action : '',
            guideUrl: is_string($guideUrl) ? $guideUrl : '',
            autoFixed: is_bool($autoFixed) ? $autoFixed : false,
            confidence: is_string($confidence) ? $confidence : 'high',
        );
    }
}
