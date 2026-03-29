<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateComposerDependenciesRector extends AbstractRector
{
    /** @var array<string, string> */
    private array $dependencyMap = [
        'laravel/framework' => '^12.0',
        'phpunit/phpunit' => '^11.0',
        'pestphp/pest' => '^3.0',
        'nunomaduro/collision' => '^8.1',
        'laravel/breeze' => '^2.0',
        'laravel/cashier' => '^15.0',
        'laravel/dusk' => '^8.0',
        'laravel/jetstream' => '^5.0',
        'laravel/passport' => '^12.0',
        'laravel/sanctum' => '^4.0',
        'laravel/scout' => '^10.0',
        'laravel/telescope' => '^5.0',
        'livewire/livewire' => '^3.4',
        'inertiajs/inertia-laravel' => '^2.0',
    ];

    public function getNodeTypes(): array
    {
        return [Array_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Array_) {
            return null;
        }

        $changed = false;

        foreach ($node->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if (! $item->key instanceof String_) {
                continue;
            }

            if (! $item->value instanceof String_) {
                continue;
            }

            $package = $item->key->value;

            if (! isset($this->dependencyMap[$package])) {
                continue;
            }

            $newVersion = $this->dependencyMap[$package];

            if (! $this->shouldUpdateVersion($item->value->value, $newVersion)) {
                continue;
            }

            $item->value = new String_($newVersion);
            $changed = true;
        }

        return $changed ? $node : null;
    }

    private function shouldUpdateVersion(string $currentConstraint, string $targetConstraint): bool
    {
        $current = $this->extractVersion($currentConstraint);
        $target = $this->extractVersion($targetConstraint);

        if ($current === null || $target === null) {
            return false;
        }

        return version_compare($current, $target, '<');
    }

    private function extractVersion(string $constraint): ?string
    {
        if (preg_match('/(\d+(?:\.\d+)*)/', $constraint, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update dependency version strings for Laravel 12 compatibility',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
<?php

$require = [
    "laravel/framework" => "^11.31",
    "phpunit/phpunit" => "^10.5",
];
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
<?php

$require = [
    "laravel/framework" => "^12.0",
    "phpunit/phpunit" => "^11.0",
];
CODE_SAMPLE,
                ),
            ]
        );
    }
}
