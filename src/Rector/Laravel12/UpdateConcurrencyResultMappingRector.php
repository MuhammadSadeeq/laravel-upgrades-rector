<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\StaticCallExtractor;
use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateConcurrencyResultMappingRector extends AbstractRector
{
    public function __construct(
        private readonly StaticCallExtractor $staticCallExtractor,
    ) {}

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Expression) {
            return null;
        }

        $staticCall = $this->staticCallExtractor->extract($node);

        if ($staticCall === null) {
            return null;
        }

        if (!$staticCall->class instanceof Name) {
            return null;
        }

        if (
            !$this->isName($staticCall->class, 'Concurrency') &&
            !$this->isName($staticCall->class, 'Illuminate\Support\Facades\Concurrency')
        ) {
            return null;
        }

        if (!$this->isName($staticCall->name, 'run')) {
            return null;
        }

        if (! isset($staticCall->args[0]) || ! $staticCall->args[0] instanceof Arg || ! $staticCall->args[0]->value instanceof Array_) {
            return null;
        }

        if (! $this->hasAssociativeStringKeys($staticCall->args[0]->value)) {
            return null;
        }

        $existingComments = $node->getComments();
        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), 'Laravel 12:')) {
                return null;
            }
        }

        $newComment = new Comment(
            '// Laravel 12: Concurrency::run() now preserves associative array keys in results. Verify your code handles keyed results correctly.'
        );

        $node->setAttribute('comments', array_merge([$newComment], $existingComments));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
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
                    '// Laravel 12: Concurrency::run() now preserves associative array keys in results. Verify your code handles keyed results correctly.
$results = Concurrency::run([
    \'task-1\' => fn () => 1 + 1,
    \'task-2\' => fn () => 2 + 2,
]);',
                ),
            ],
        );
    }
}
