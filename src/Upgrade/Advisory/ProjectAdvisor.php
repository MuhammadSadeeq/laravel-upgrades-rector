<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;

/**
 * Scans project configuration files for known upgrade-sensitive patterns
 * (plan P3-04). Produces structured findings that complement the AST-level
 * PHPStan rules.
 */
final class ProjectAdvisor
{
    private const CONFIG_CHECKS = [
        'database.php' => [
            'pattern' => '/DB_CONNECTION.*sqlite/',
            'ruleId' => 'laravelUpgrade.sqliteConnection',
            'message' => 'SQLite connection detected.',
            'action' => 'Verify SQLite >= 3.26 for Laravel 11+.',
            'severity' => Finding::SEVERITY_MEDIUM,
        ],
        'session.php' => [
            'pattern' => '/serialization.*(?:php|serialize)/',
            'ruleId' => 'laravelUpgrade.sessionSerialization',
            'message' => 'Session serialization uses PHP format.',
            'action' => 'Laravel 13 defaults to JSON; switching invalidates active sessions.',
            'severity' => Finding::SEVERITY_HIGH,
        ],
    ];

    public function __construct(
        private readonly string $configDirectory,
        private readonly int $targetMajor,
    ) {}

    public function scan(FindingCollector $collector): void
    {
        if (! is_dir($this->configDirectory)) {
            return;
        }

        foreach (self::CONFIG_CHECKS as $file => $check) {
            $path = $this->configDirectory.'/'.$file;

            if (! is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            if (preg_match($check['pattern'], $contents) === 1) {
                $collector->add(
                    $check['ruleId'],
                    $check['severity'],
                    $this->targetMajor,
                    'config/'.$file,
                    0,
                    $check['message'],
                    $check['action']
                );
            }
        }

        // Check .env for sqlite.
        $envPath = dirname($this->configDirectory).'/.env';

        if (is_file($envPath)) {
            $envContents = file_get_contents($envPath);

            if ($envContents !== false && str_contains($envContents, 'DB_CONNECTION=sqlite')) {
                $collector->add(
                    'laravelUpgrade.sqliteConnection',
                    self::CONFIG_CHECKS['database.php']['severity'],
                    $this->targetMajor,
                    '.env',
                    0,
                    'SQLite database connection configured.',
                    'Verify SQLite >= 3.26 for Laravel 11+.'
                );
            }
        }
    }
}
