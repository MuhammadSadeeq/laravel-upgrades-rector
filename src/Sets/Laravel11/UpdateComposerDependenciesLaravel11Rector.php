<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateComposerDependenciesLaravel11Rector extends AbstractRector
{
    private static bool $hasRun = false;

    /** @var array<string, string> */
    private array $dependencyUpdates = [
        'laravel/framework' => '^11.0',
        'nunomaduro/collision' => '^8.1',
        'laravel/breeze' => '^2.0',
        'laravel/cashier' => '^15.0',
        'laravel/dusk' => '^8.0',
        'laravel/jetstream' => '^5.0',
        'laravel/octane' => '^2.3',
        'laravel/passport' => '^12.0',
        'laravel/sanctum' => '^4.0',
        'laravel/scout' => '^10.0',
        'laravel/spark-stripe' => '^5.0',
        'laravel/telescope' => '^5.0',
        'livewire/livewire' => '^3.4',
        'inertiajs/inertia-laravel' => '^1.0',
    ];

    /** @var array<int, string> */
    private array $packagesToRemove = [
        'doctrine/dbal',
        'spatie/once',
    ];

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        // Only run once per Rector execution
        if (self::$hasRun) {
            return null;
        }

        // Mark as run
        self::$hasRun = true;

        // Find composer.json in the project root
        $composerPath = getcwd() . '/composer.json';

        if (!file_exists($composerPath)) {
            return null;
        }

        // Read composer.json
        $composerContent = file_get_contents($composerPath);
        if ($composerContent === false) {
            return null;
        }

        $composer = json_decode($composerContent, true);
        if (!is_array($composer)) {
            return null;
        }

        $hasChanges = false;

        // Update dependencies in 'require' section
        if (isset($composer['require']) && is_array($composer['require'])) {
            foreach ($this->dependencyUpdates as $package => $version) {
                if (isset($composer['require'][$package])) {
                    $composer['require'][$package] = $version;
                    $hasChanges = true;
                }
            }

            // Remove packages from 'require'
            foreach ($this->packagesToRemove as $package) {
                if (isset($composer['require'][$package])) {
                    unset($composer['require'][$package]);
                    $hasChanges = true;
                }
            }
        }

        // Update dependencies in 'require-dev' section
        if (isset($composer['require-dev']) && is_array($composer['require-dev'])) {
            foreach ($this->dependencyUpdates as $package => $version) {
                if (isset($composer['require-dev'][$package])) {
                    $composer['require-dev'][$package] = $version;
                    $hasChanges = true;
                }
            }

            // Remove packages from 'require-dev'
            foreach ($this->packagesToRemove as $package) {
                if (isset($composer['require-dev'][$package])) {
                    unset($composer['require-dev'][$package]);
                    $hasChanges = true;
                }
            }
        }

        // Write back to composer.json if changes were made
        if ($hasChanges) {
            $newContent = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            file_put_contents($composerPath, $newContent);
        }

        // Return null as we're not modifying any PHP nodes
        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update composer.json dependencies for Laravel 11 compatibility',
            [
                new CodeSample(
                    '"laravel/framework": "^10.0"',
                    '"laravel/framework": "^11.0"'
                ),
                new CodeSample(
                    '"doctrine/dbal": "^3.0"',
                    '// Package removed - no longer needed in Laravel 11'
                ),
            ]
        );
    }
}
