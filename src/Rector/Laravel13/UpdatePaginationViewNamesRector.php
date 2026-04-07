<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdatePaginationViewNamesRector extends AbstractRector
{
    /** @var array<string, string> */
    private const VIEW_MAP = [
        'pagination::default' => 'pagination::bootstrap-3',
        'pagination::simple-default' => 'pagination::simple-bootstrap-3',
    ];

    /** @var array<int, string> */
    private const PAGINATION_METHODS = [
        'defaultSimpleView',
        'defaultView',
        'links',
        'render',
    ];

    public function getNodeTypes(): array
    {
        return [MethodCall::class, StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof MethodCall && ! $node instanceof StaticCall) {
            return null;
        }

        $methodName = $this->getName($node->name);

        if ($methodName === null || ! in_array($methodName, self::PAGINATION_METHODS, true)) {
            return null;
        }

        $firstArg = $node->args[0] ?? null;

        if (! $firstArg instanceof Arg || ! $firstArg->value instanceof String_) {
            return null;
        }

        if (! isset(self::VIEW_MAP[$firstArg->value->value])) {
            return null;
        }

        $firstArg->value->value = self::VIEW_MAP[$firstArg->value->value];

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rename deprecated pagination view names to their Bootstrap 3 equivalents in Laravel 13',
            [
                new CodeSample(
                    "\$paginator->links('pagination::default');",
                    "\$paginator->links('pagination::bootstrap-3');",
                ),
                new CodeSample(
                    "\$paginator->links('pagination::simple-default');",
                    "\$paginator->links('pagination::simple-bootstrap-3');",
                ),
            ]
        );
    }
}
