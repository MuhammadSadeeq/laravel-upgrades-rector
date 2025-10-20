<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateRequestMergingRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof MethodCall) {
            return null;
        }

        // Check if this is a mergeIfMissing method call on a request object
        if (!$this->isName($node->name, 'mergeIfMissing')) {
            return null;
        }

        // Add a comment indicating the behavior change for nested arrays
        $node->setAttribute('comments', [
            new \PhpParser\Comment\Doc(
                '/** Laravel 12: mergeIfMissing() now supports nested array merging with dot notation. ' .
                'This may change behavior if you were relying on shallow merging. */'
            )
        ]);

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add documentation comments for Request mergeIfMissing() method that now supports nested array merging in Laravel 12',
            [
                new CodeSample(
                    '$request->mergeIfMissing($data)',
                    '/** Laravel 12: mergeIfMissing() now supports nested array merging with dot notation. This may change behavior if you were relying on shallow merging. */
$request->mergeIfMissing($data)'
                ),
            ]
        );
    }
}