<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\CommentInserter;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdatePublishedServiceProviderRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade published-provider';

    public function __construct(
        private readonly CommentInserter $commentInserter,
    ) {}

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        if (! $this->containsConfigAppReference($node) || ! $this->containsPublishesCall($node)) {
            return null;
        }

        if (! $this->commentInserter->addComment(
            $node,
            self::COMMENT_MARKER,
            'publish service providers via ServiceProvider::addProviderToBootstrapFile() instead of modifying config/app.php directly.'
        )) {
            return null;
        }

        return $node;
    }

    private function containsConfigAppReference(Expression $expression): bool
    {
        $containsReference = false;

        $this->traverseNodesWithCallable($expression->expr, function (Node $node) use (&$containsReference): ?Node {
            if ($node instanceof String_ && $node->value === 'config/app.php') {
                $containsReference = true;
            }

            if ($node instanceof FuncCall && $this->isName($node->name, 'config_path')) {
                $firstArg = $node->args[0] ?? null;

                if ($firstArg instanceof Node\Arg && $firstArg->value instanceof String_ && $firstArg->value->value === 'app.php') {
                    $containsReference = true;
                }
            }

            return null;
        });

        return $containsReference;
    }

    private function containsPublishesCall(Expression $expression): bool
    {
        $containsCall = false;

        $this->traverseNodesWithCallable($expression->expr, function (Node $node) use (&$containsCall): ?Node {
            if ($node instanceof Node\Expr\MethodCall && $this->isName($node->name, 'publishes')) {
                $containsCall = true;
            }

            return null;
        });

        return $containsCall;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Warn when a package publishes or edits config/app.php directly for provider registration',
            [
                new CodeSample(
                    '$this->publishes([__DIR__ . \'/stubs/app.php\' => config_path(\'app.php\')]);',
                    '// Laravel 11: publish service providers via ServiceProvider::addProviderToBootstrapFile() instead of modifying config/app.php directly.
$this->publishes([__DIR__ . \'/stubs/app.php\' => config_path(\'app.php\')]);'
                ),
            ]
        );
    }
}
