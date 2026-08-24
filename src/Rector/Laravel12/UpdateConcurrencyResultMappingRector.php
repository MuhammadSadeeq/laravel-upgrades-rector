<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\CommentInserter;
use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\StatementCallFinder;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateConcurrencyResultMappingRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade concurrency-keyed-results';

    public function __construct(
        private readonly StatementCallFinder $statementCallFinder,
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

        $staticCall = null;

        foreach ($this->statementCallFinder->find($node) as $candidate) {
            if ($candidate instanceof StaticCall) {
                $staticCall = $candidate;

                break;
            }
        }

        if (! $staticCall instanceof StaticCall) {
            return null;
        }

        if (! $staticCall->class instanceof Name) {
            return null;
        }

        if (
            ! $this->isName($staticCall->class, 'Concurrency') &&
            ! $this->isName($staticCall->class, 'Illuminate\Support\Facades\Concurrency')
        ) {
            return null;
        }

        if (! $this->isName($staticCall->name, 'run')) {
            return null;
        }

        if (! isset($staticCall->args[0]) || ! $staticCall->args[0] instanceof Arg || ! $staticCall->args[0]->value instanceof Array_) {
            return null;
        }

        if (! $this->hasAssociativeStringKeys($staticCall->args[0]->value)) {
            return null;
        }

        $firstAssociativeItem = $this->findFirstAssociativeStringKeyItem($staticCall->args[0]->value);

        if (! $firstAssociativeItem instanceof ArrayItem) {
            return null;
        }

        if ($this->commentInserter->addComment(
            $firstAssociativeItem,
            self::COMMENT_MARKER,
            'Concurrency::run() now preserves associative array keys in results. Verify your code handles keyed results correctly.'
        )) {
            return $node;
        }

        return null;
    }

    private function hasAssociativeStringKeys(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if ($item->key instanceof String_) {
                return true;
            }
        }

        return false;
    }

    private function findFirstAssociativeStringKeyItem(Array_ $array): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            return $item;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comment for Concurrency::run() result mapping behavior change in Laravel 12',
            [
                new CodeSample(
                    '$results = Concurrency::run([
    \'task-1\' => fn () => 1 + 1,
    \'task-2\' => fn () => 2 + 2,
]);',
                    '$results = Concurrency::run([
    // Laravel 12: Concurrency::run() now preserves associative array keys in results. Verify your code handles keyed results correctly.
    \'task-1\' => fn () => 1 + 1,
    \'task-2\' => fn () => 2 + 2,
]);',
                ),
            ],
        );
    }
}
