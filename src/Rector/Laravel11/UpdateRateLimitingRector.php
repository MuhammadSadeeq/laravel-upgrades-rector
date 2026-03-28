<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\Mul;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateRateLimitingRector extends AbstractRector
{
    /** @var array<string, int> */
    private array $constructorTimeArgIndex = [
        'Illuminate\\Cache\\RateLimiting\\GlobalLimit' => 1,
        'Illuminate\\Cache\\RateLimiting\\Limit' => 2,
        'Illuminate\\Queue\\Middleware\\ThrottlesExceptions' => 1,
        'Illuminate\\Queue\\Middleware\\ThrottlesExceptionsWithRedis' => 1,
    ];

    public function getNodeTypes(): array
    {
        return [New_::class, PropertyFetch::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof New_) {
            return $this->refactorNew($node);
        }

        if ($node instanceof PropertyFetch) {
            return $this->refactorPropertyFetch($node);
        }

        return null;
    }

    private function refactorNew(New_ $node): ?Node
    {
        if (! $node->class instanceof Name) {
            return null;
        }

        $argIndex = null;

        foreach ($this->constructorTimeArgIndex as $fqcn => $index) {
            if ($this->isName($node->class, $fqcn)) {
                $argIndex = $index;
                break;
            }
        }

        if ($argIndex === null) {
            return null;
        }

        if (! isset($node->args[$argIndex])) {
            return null;
        }

        $arg = $node->args[$argIndex];

        if (! $arg instanceof Arg) {
            return null;
        }

        if ($this->isAlreadyMultipliedBy60($arg)) {
            return null;
        }

        if (! $arg->value instanceof Int_) {
            return null;
        }

        $arg->value = new Mul($arg->value, new Int_(60));

        return $node;
    }

    private function refactorPropertyFetch(PropertyFetch $node): ?Node
    {
        if (! $this->isName($node->name, 'decayMinutes')) {
            return null;
        }

        // Only match $this->decayMinutes (not arbitrary objects)
        if (! $node->var instanceof Variable || ! $this->isName($node->var, 'this')) {
            return null;
        }

        $node->name = new Identifier('decaySeconds');

        return $node;
    }

    private function isAlreadyMultipliedBy60(Arg $arg): bool
    {
        if (! $arg->value instanceof Mul) {
            return false;
        }

        $mul = $arg->value;

        if ($mul->right instanceof Int_ && $mul->right->value === 60) {
            return true;
        }

        if ($mul->left instanceof Int_ && $mul->left->value === 60) {
            return true;
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update rate limiting classes to use seconds instead of minutes for Laravel 11',
            [
                new CodeSample(
                    'new \Illuminate\Cache\RateLimiting\GlobalLimit(100, 2)',
                    'new \Illuminate\Cache\RateLimiting\GlobalLimit(100, 2 * 60)',
                ),
                new CodeSample(
                    '$this->decayMinutes',
                    '$this->decaySeconds',
                ),
            ]
        );
    }
}
