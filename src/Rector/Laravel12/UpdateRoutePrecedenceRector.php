<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateRoutePrecedenceRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 12: duplicate route names now resolve to the first registered route';

    private string $currentFilePath = '';

    private int $lastProcessedLine = 0;

    /** @var array<string, true> */
    private array $seenRouteNames = [];

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        $filePath = $this->file->getFilePath();
        $currentLine = $node->getStartLine();

        if ($filePath !== $this->currentFilePath || $currentLine <= $this->lastProcessedLine) {
            $this->currentFilePath = $filePath;
            $this->lastProcessedLine = 0;
            $this->seenRouteNames = [];
        }

        $this->lastProcessedLine = $currentLine;

        $routeName = $this->extractRouteName($node->expr);

        if ($routeName === null) {
            return null;
        }

        if (! isset($this->seenRouteNames[$routeName])) {
            $this->seenRouteNames[$routeName] = true;
            return null;
        }

        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $node->setAttribute('comments', array_merge([
            new Comment('// ' . self::COMMENT_MARKER . '. This later route no longer wins when names collide.'),
        ], $node->getComments()));

        return $node;
    }

    private function extractRouteName(Node $node): ?string
    {
        while ($node instanceof MethodCall) {
            if ($this->isName($node->name, 'name') && isset($node->args[0]) && $node->args[0] instanceof Arg && $node->args[0]->value instanceof String_ && $this->isRouteCallChain($node->var)) {
                return $node->args[0]->value->value;
            }

            $node = $node->var;
        }

        return null;
    }

    private function isRouteCallChain(Node $node): bool
    {
        while ($node instanceof MethodCall) {
            $node = $node->var;
        }

        if (! $node instanceof StaticCall) {
            return false;
        }

        if (! $this->isName($node->class, 'Route') && ! $this->isName($node->class, 'Illuminate\\Support\\Facades\\Route')) {
            return false;
        }

        return in_array($this->getName($node->name), [
            'any',
            'delete',
            'fallback',
            'get',
            'match',
            'options',
            'patch',
            'post',
            'put',
            'redirect',
            'resource',
            'view',
        ], true);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add an advisory comment when the same route name is registered more than once in a file',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
Route::get('/first', FirstController::class)->name('dashboard');
Route::get('/second', SecondController::class)->name('dashboard');
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
Route::get('/first', FirstController::class)->name('dashboard');
// Laravel 12: duplicate route names now resolve to the first registered route. This later route no longer wins when names collide.
Route::get('/second', SecondController::class)->name('dashboard');
CODE_SAMPLE
                ),
            ]
        );
    }
}
