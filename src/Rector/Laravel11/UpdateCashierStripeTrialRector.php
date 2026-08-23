<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\CommentInserter;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateCashierStripeTrialRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade cashier-trial-cancel';

    public function __construct(
        private readonly CommentInserter $commentInserter,
    ) {}

    /** @var array<int, string> */
    private array $cancelMethods = [
        'cancel',
        'cancelNow',
        'cancelAt',
    ];

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        if (! $node->expr instanceof MethodCall) {
            return null;
        }

        $methodName = $this->getName($node->expr->name);

        if ($methodName === null) {
            return null;
        }

        if (! in_array($methodName, $this->cancelMethods, true)) {
            return null;
        }

        if (! $this->isObjectType($node->expr->var, new ObjectType('Laravel\\Cashier\\Subscription'))) {
            return null;
        }

        if (! $this->commentInserter->addComment(
            $node,
            self::COMMENT_MARKER,
            $methodName . '() now always ends subscription trials immediately'
        )) {
            return null;
        }

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Warn about Cashier Stripe 15.0 trial ending behavior when canceling subscriptions',
            [
                new CodeSample(
                    '$subscription->cancel()',
                    <<<'CODE_SAMPLE'
// Cashier Stripe 15: cancel() now always ends subscription trials immediately
$subscription->cancel()
CODE_SAMPLE
                ),
            ]
        );
    }
}
