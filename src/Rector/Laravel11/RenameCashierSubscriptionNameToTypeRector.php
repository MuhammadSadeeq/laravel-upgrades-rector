<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Cashier 15 renamed the subscription "name" column to "type". Code reading
 * `$subscription->name` on a typed Laravel\Cashier\Subscription receiver must
 * read `$subscription->type` instead.
 *
 * Only property fetches on confirmed Subscription instances are touched.
 */
final class RenameCashierSubscriptionNameToTypeRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Node\Expr\PropertyFetch::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Node\Expr\PropertyFetch || ! $this->isName($node->name, 'name')) {
            return null;
        }

        $scope = $node->var->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return null;
        }

        try {
            $type = $scope->getType($node->var);
        } catch (\Throwable) {
            return null;
        }

        if (! (new ObjectType('Laravel\Cashier\Subscription'))->isSuperTypeOf($type)->yes()) {
            return null;
        }

        $node->name = new Identifier('type');

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rename the Cashier subscription "name" column to "type" on typed receivers',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$type = $subscription->name;
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$type = $subscription->type;
CODE_SAMPLE,
                ),
            ],
        );
    }
}
