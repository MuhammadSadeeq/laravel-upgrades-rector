<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateComposerDependenciesRector extends AbstractRector
{
    private static bool $hasRun = false;

    /** @var array<string, string> */
    private array $dependencyUpdates = [
        'laravel/framework' => '^12.0',
        'phpunit/phpunit' => '^11.0',
        'pestphp/pest' => '^3.0',
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
        }

        // Update dependencies in 'require-dev' section
        if (isset($composer['require-dev']) && is_array($composer['require-dev'])) {
            foreach ($this->dependencyUpdates as $package => $version) {
                if (isset($composer['require-dev'][$package])) {
                    $composer['require-dev'][$package] = $version;
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
            'Update composer.json dependencies for Laravel 12 compatibility',
            [
                new CodeSample(
                    '"laravel/framework": "^11.31"',
                    '"laravel/framework": "^12.0"'
                ),
                new CodeSample(
                    '"phpunit/phpunit": "^10.0"',
                    '"phpunit/phpunit": "^11.0"'
                ),
            ]
        );
    }
}