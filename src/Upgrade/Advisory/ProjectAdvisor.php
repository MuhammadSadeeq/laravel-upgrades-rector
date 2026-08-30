<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory;

use Closure;
use FilesystemIterator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\PublishedViewChecker;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Scans project configuration and other project-level upgrade surfaces.
 *
 * This class deliberately only reads the project. In particular, checking the
 * globally installed Laravel installer must not execute Composer as a side
 * effect of an advisory scan; callers may inject a checker when they already
 * have a trusted Composer integration.
 */
final class ProjectAdvisor
{
    /** @var array<int, list<string>> */
    private const REMOVED_SKELETON_FILES = [
        11 => [
            'app/Console/Kernel.php', 'app/Exceptions/Handler.php', 'app/Http/Kernel.php',
            'app/Http/Middleware/Authenticate.php', 'app/Http/Middleware/EncryptCookies.php',
            'app/Http/Middleware/PreventRequestsDuringMaintenance.php', 'app/Http/Middleware/RedirectIfAuthenticated.php',
            'app/Http/Middleware/TrimStrings.php', 'app/Http/Middleware/TrustHosts.php',
            'app/Http/Middleware/TrustProxies.php', 'app/Http/Middleware/ValidateSignature.php',
            'app/Http/Middleware/VerifyCsrfToken.php', 'app/Providers/AuthServiceProvider.php',
            'app/Providers/BroadcastServiceProvider.php', 'app/Providers/EventServiceProvider.php',
            'app/Providers/RouteServiceProvider.php', 'config/broadcasting.php', 'config/cors.php',
            'config/hashing.php', 'config/sanctum.php', 'config/view.php', 'routes/api.php',
            'routes/channels.php', 'tests/CreatesApplication.php',
        ],
        12 => ['postcss.config.js', 'tailwind.config.js'],
        13 => ['resources/js/bootstrap.js'],
    ];

    /** @var array<string, array{pattern: string, ruleId: string, message: string, action: string, severity: string, minMajor: int, guideUrl?: string}> */
    private const CONFIG_CHECKS = [
        'database.php' => [
            'pattern' => '/DB_CONNECTION.*sqlite/i', 'ruleId' => 'laravelUpgrade.sqliteConnection',
            'message' => 'SQLite connection detected.', 'action' => 'Verify SQLite >= 3.26 for Laravel 11+.',
            'severity' => Finding::SEVERITY_MEDIUM, 'minMajor' => 11,
        ],
        'session.php' => [
            'pattern' => '/serialization.*(?:php|serialize)/i',
            'ruleId' => 'laravelUpgrade.sessionSerialization',
            'message' => 'Session serialization uses the legacy PHP format.',
            'action' => 'Laravel 13 defaults to JSON; switch during a maintenance window because active sessions become invalid.',
            'severity' => Finding::SEVERITY_HIGH, 'minMajor' => 13,
            'guideUrl' => 'https://laravel.com/docs/13.x/upgrade#session-serialization',
        ],
    ];

    /** @var array<int, array<string, string>> */
    private const RENAMED_ENV_KEYS = [
        11 => ['CACHE_DRIVER' => 'CACHE_STORE', 'BROADCAST_DRIVER' => 'BROADCAST_CONNECTION'],
        12 => [], 13 => [],
    ];

    private readonly string $projectDirectory;

    private readonly ?Closure $installerVersionChecker;

    public function __construct(
        private readonly string $configDirectory,
        private readonly int $targetMajor,
        ?callable $installerVersionChecker = null
    ) {
        $this->projectDirectory = dirname(rtrim($configDirectory, '/'));
        $this->installerVersionChecker = $installerVersionChecker === null
            ? null
            : Closure::fromCallable($installerVersionChecker);
    }

    public function scan(FindingCollector $collector): void
    {
        $this->scanConfig($collector);
        $envContents = $this->readFile($this->projectDirectory.'/.env');

        if ($envContents !== null && preg_match('/^\s*DB_CONNECTION\s*=\s*["\']?sqlite\b/im', $envContents) === 1) {
            $collector->add(
                'laravelUpgrade.sqliteConnection', Finding::SEVERITY_MEDIUM, $this->targetMajor, '.env',
                $this->lineForPattern($envContents, '/^\s*DB_CONNECTION\s*=\s*["\']?sqlite\b/im'),
                'SQLite database connection configured.', 'Verify SQLite >= 3.26 for Laravel 11+.'
            );
        }

        $this->scanQueueContext($collector, $envContents);
        $this->scanEnvironmentRenames($collector);
        $this->scanInstalledPackages($collector);
        $this->scanRemovedSkeletonFiles($collector);
        $this->scanGlobalInstaller($collector);

        // PublishedViewChecker is the sole owner of published-view findings.
        if (is_dir($this->projectDirectory)) {
            (new PublishedViewChecker)->scan($this->projectDirectory, $this->targetMajor, $collector);
        }
    }

    private function scanConfig(FindingCollector $collector): void
    {
        if (is_dir($this->configDirectory)) {
            foreach (self::CONFIG_CHECKS as $file => $check) {
                if ($this->targetMajor < $check['minMajor']) {
                    continue;
                }

                $contents = $this->readFile(rtrim($this->configDirectory, '/').'/'.$file);

                if ($contents === null || preg_match($check['pattern'], $contents) !== 1) {
                    continue;
                }

                $collector->add(
                    $check['ruleId'], $check['severity'], $this->targetMajor, 'config/'.$file,
                    $this->lineForPattern($contents, $check['pattern']), $check['message'], $check['action'],
                    $check['guideUrl'] ?? ''
                );
            }
        }

        $database = $this->readFile($this->configDirectory.'/database.php');

        if ($database !== null) {
            $drivers = $this->configuredDrivers($database);
            $env = $this->readFile($this->projectDirectory.'/.env');

            if ($env !== null && preg_match('/^\s*DB_CONNECTION\s*=\s*["\']?([a-z0-9_-]+)/im', $env, $match) === 1) {
                $drivers[] = strtolower($match[1]);
            }

            $drivers = array_values(array_unique($drivers));

            if ($drivers !== []) {
                $collector->add(
                    'laravelUpgrade.databaseDrivers', Finding::SEVERITY_INFO, $this->targetMajor,
                    'config/database.php', $this->lineForPattern($database, "/['\"]driver['\"]\s*=>/"),
                    sprintf('Configured database drivers: %s.', implode(', ', $drivers)),
                    'Review driver-specific Laravel upgrade notes, especially SQLite and MariaDB compatibility.'
                );
            }
        }

        $this->scanCachePrefix($collector);
    }

    private function scanCachePrefix(FindingCollector $collector): void
    {
        if ($this->targetMajor < 11) {
            return;
        }

        $contents = $this->readFile($this->configDirectory.'/cache.php');

        if ($contents === null || preg_match('/[\'\"]prefix[\'\"]\s*=>/i', $contents) !== 1) {
            return;
        }

        $collector->add(
            'laravelUpgrade.cachePrefix', Finding::SEVERITY_INFO, $this->targetMajor, 'config/cache.php',
            $this->lineForPattern($contents, '/[\'\"]prefix[\'\"]\s*=>/i'),
            'An explicit cache prefix is configured; Laravel 11 no longer appends a colon automatically.',
            'Review cache key compatibility before and after the upgrade.',
            'https://laravel.com/docs/11.x/upgrade#cache-prefix'
        );
    }

    private function scanQueueContext(FindingCollector $collector, ?string $envContents): void
    {
        $queue = $this->readFile($this->configDirectory.'/queue.php');

        if ($queue === null || $this->targetMajor < 11) {
            return;
        }

        $sync = preg_match('/[\'\"]default[\'\"]\s*=>\s*(?:env\s*\(\s*[\'\"]QUEUE_CONNECTION[\'\"]\s*,\s*)?[\'\"]sync[\'\"]/i', $queue) === 1;

        if (! $sync && $envContents !== null) {
            $sync = preg_match('/^\s*QUEUE_CONNECTION\s*=\s*["\']?sync\b/im', $envContents) === 1
                && preg_match('/[\'\"]default[\'\"]\s*=>\s*env\s*\(\s*[\'\"]QUEUE_CONNECTION[\'\"]/i', $queue) === 1;
        }

        if (! $sync) {
            return;
        }

        $collector->add(
            'laravelUpgrade.queueDefaultSync', Finding::SEVERITY_INFO, $this->targetMajor, 'config/queue.php',
            $this->lineForPattern($queue, '/[\'\"]default[\'\"]\s*=>/'),
            'The default queue connection resolves to the synchronous driver.',
            'Review after-commit queue behavior when upgrading Laravel.'
        );

        if (preg_match('/\bafterCommit\b|\$afterCommit\b|[\'\"]after_commit[\'\"]\s*=>/i', $queue) === 1
            || $this->projectContains('/\bafterCommit\b|\$afterCommit\b/')) {
            $collector->add(
                'laravelUpgrade.afterCommitWithSyncQueue', Finding::SEVERITY_MEDIUM, $this->targetMajor,
                'config/queue.php', $this->lineForPattern($queue, '/\bafterCommit\b|\$afterCommit\b|[\'\"]after_commit[\'\"]\s*=>/i'),
                'Laravel 11 synchronous queue jobs now respect after-commit settings.',
                'Review transaction timing; use beforeCommit() or remove afterCommit when immediate execution is required.'
            );
        }
    }

    private function scanEnvironmentRenames(FindingCollector $collector): void
    {
        $renamedKeys = [];

        foreach (self::RENAMED_ENV_KEYS as $major => $keys) {
            if ($this->targetMajor >= $major) {
                $renamedKeys = array_merge($renamedKeys, $keys);
            }
        }

        foreach (['.env', '.env.example'] as $relative) {
            $contents = $this->readFile($this->projectDirectory.'/'.$relative);

            if ($contents === null) {
                continue;
            }

            foreach ($renamedKeys as $old => $new) {
                if (preg_match('/^\s*(?:export\s+)?'.preg_quote($old, '/').'\s*=/m', $contents) !== 1
                    || preg_match('/^\s*(?:export\s+)?'.preg_quote($new, '/').'\s*=/m', $contents) === 1) {
                    continue;
                }

                $collector->add(
                    'laravelUpgrade.envKeyRenamed', Finding::SEVERITY_MEDIUM, $this->targetMajor, $relative,
                    $this->lineForPattern($contents, '/^\s*(?:export\s+)?'.preg_quote($old, '/').'\s*=/m'),
                    sprintf('Laravel %d renamed the environment key %s to %s.', $this->targetMajor, $old, $new),
                    sprintf('Update %s and the application configuration from %s to %s when adopting the Laravel %d skeleton.', $relative, $old, $new, $this->targetMajor)
                );
            }
        }
    }

    private function scanInstalledPackages(FindingCollector $collector): void
    {
        $contents = $this->readFile($this->projectDirectory.'/composer.lock');

        if ($contents === null) {
            return;
        }

        /** @var array<string, mixed> $lock */
        $lock = json_decode($contents, true) ?: [];
        $packages = [];

        foreach (['packages', 'packages-dev'] as $section) {
            if (! is_array($lock[$section] ?? null)) {
                continue;
            }

            foreach ($lock[$section] as $package) {
                if (is_array($package) && is_string($package['name'] ?? null)) {
                    $packages[$package['name']] = is_string($package['version'] ?? null) ? $package['version'] : '';
                }
            }
        }

        if (array_key_exists('laravel/boost', $packages)) {
            $collector->add(
                'laravelUpgrade.boostAvailability', Finding::SEVERITY_INFO, $this->targetMajor,
                'composer.lock', 0, 'Laravel Boost is available in this project for assisted upgrade guidance.',
                'Review Boost upgrade guidance as an optional complement to this tool.',
                'https://laravel.com/docs/13.x/upgrade#laravel-boost'
            );
        }
    }

    private function scanRemovedSkeletonFiles(FindingCollector $collector): void
    {
        foreach (self::REMOVED_SKELETON_FILES as $major => $files) {
            if ($major > $this->targetMajor) {
                continue;
            }

            foreach ($files as $relative) {
                if (! is_file($this->projectDirectory.'/'.$relative)) {
                    continue;
                }

                $collector->add(
                    'laravelUpgrade.skeletonFileRemoved', Finding::SEVERITY_INFO, $major, $relative, 0,
                    sprintf('The Laravel %d skeleton removed "%s", but the project still contains it.', $major, $relative),
                    'Delete it only after confirming that your application no longer references or customizes it.'
                );
            }
        }
    }

    private function scanGlobalInstaller(FindingCollector $collector): void
    {
        if ($this->installerVersionChecker === null) {
            $collector->add(
                'laravelUpgrade.laravelInstallerCheck', Finding::SEVERITY_INFO, $this->targetMajor,
                'composer.json', 0, 'The globally installed Laravel installer version was not checked automatically.',
                'Run `composer global show laravel/installer` and update it before creating or upgrading Laravel projects.',
                'https://laravel.com/docs/13.x/installation'
            );

            return;
        }

        try {
            $version = ($this->installerVersionChecker)();

            if (is_scalar($version) && trim((string) $version) !== '') {
                $collector->add(
                    'laravelUpgrade.laravelInstaller', Finding::SEVERITY_INFO, $this->targetMajor,
                    'composer.json', 0, sprintf('Global Laravel installer version detected: %s.', trim((string) $version)),
                    'Confirm the installer supports the target Laravel major.', 'https://laravel.com/docs/13.x/installation'
                );

                return;
            }
        } catch (Throwable) {
            // A failed injected checker is still an explicit actionable item.
        }

        $collector->add(
            'laravelUpgrade.laravelInstallerCheck', Finding::SEVERITY_INFO, $this->targetMajor,
            'composer.json', 0, 'The globally installed Laravel installer version could not be determined.',
            'Run `composer global show laravel/installer` and update it before creating or upgrading Laravel projects.',
            'https://laravel.com/docs/13.x/installation'
        );
    }

    /** @return list<string> */
    private function configuredDrivers(string $contents): array
    {
        preg_match_all('/[\'\"]driver[\'\"]\s*=>\s*[\'\"]([^\'\"]+)[\'\"]/i', $contents, $matches);

        return array_map('strtolower', $matches[1]);
    }

    private function projectContains(string $pattern): bool
    {
        foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'tests'] as $directory) {
            $root = $this->projectDirectory.'/'.$directory;

            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (! $fileInfo instanceof SplFileInfo || ! $fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
                    continue;
                }

                $contents = $this->readFile($fileInfo->getPathname());

                if ($contents !== null && preg_match($pattern, $contents) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function readFile(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function lineForPattern(string $contents, string $pattern): int
    {
        foreach (preg_split('/\R/', $contents) ?: [] as $index => $line) {
            if (preg_match($pattern, $line) === 1) {
                return $index + 1;
            }
        }

        return 0;
    }
}
