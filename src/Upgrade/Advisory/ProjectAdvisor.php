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
    /**
     * Packages whose major upgrades have their own guides.
     *
     * @var array<string, array{url: string, key: string}>
     */
    private const PACKAGE_GUIDES = [
        'livewire' => ['url' => 'https://livewire.laravel.com/docs/upgrading', 'key' => 'livewire/livewire'],
        'jetstream' => ['url' => 'https://jetstream.laravel.com', 'key' => 'laravel/jetstream'],
        'inertia' => ['url' => 'https://inertiajs.com/upgrade-guide', 'key' => 'inertiajs/inertia-laravel'],
        'filament' => ['url' => 'https://filamentphp.com/docs/upgrade-guide', 'key' => 'filament/filament'],
    ];

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

        $this->scanInstalledPackages(dirname($this->configDirectory), $collector);

        $this->scanPublishedViews(dirname($this->configDirectory, 2), $collector);
    }

    private function scanInstalledPackages(string $projectDir, FindingCollector $collector): void
    {
        $lockPath = $projectDir.'/composer.lock';

        if (! is_file($lockPath)) {
            return;
        }

        /** @var array{packages?: list<array{name?: string}>} $lock */
        $lock = json_decode((string) file_get_contents($lockPath), true);
        $packages = is_array($lock['packages'] ?? null) ? $lock['packages'] : [];

        foreach ($packages as $package) {
            $name = is_string($package['name'] ?? null) ? $package['name'] : '';

            foreach (self::PACKAGE_GUIDES as $key => $guide) {
                if ($name === $guide['key']) {
                    $collector->add(
                        'laravelUpgrade.'.$key.'UpgradeGuide',
                        Finding::SEVERITY_INFO,
                        $this->targetMajor,
                        'composer.lock',
                        0,
                        sprintf('%s is installed and may require its own upgrade process.', $name),
                        sprintf('Review the %s upgrade guide.', $name)
                    );
                }
            }
        }
    }

    private function scanPublishedViews(string $projectDir, FindingCollector $collector): void
    {
        $viewsDir = $projectDir.'/resources/views/vendor';

        if (! is_dir($viewsDir)) {
            return;
        }

        foreach (self::PACKAGE_GUIDES as $_ => $guide) {
            $vendorName = strtolower(substr($guide['key'], 0));
            $viewDir = $viewsDir.'/'.$vendorName;

            if (is_dir($viewDir)) {
                $collector->add(
                    'laravelUpgrade.publishedVendorViews',
                    Finding::SEVERITY_LOW,
                    $this->targetMajor,
                    'resources/views/vendor/'.$vendorName,
                    0,
                    sprintf('Published vendor views found for "%s".', $vendorName),
                    'Republish views after upgrading to ensure compatibility.'
                );
            }
        }
    }
}
