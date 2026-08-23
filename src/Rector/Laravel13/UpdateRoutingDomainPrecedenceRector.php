<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateRoutingDomainPrecedenceRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 13: explicit domain routes now take precedence over non-domain routes';

    private string $currentFilePath = '';

    private int $lastProcessedLine = 0;

    private bool $hasSeenDomainRoute = false;

    private bool $hasSeenNonDomainRoute = false;

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        $this->resetStateWhenTraversalRestarts($node);

        $isDomainRoute = $this->resolveRouteType($node->expr);

        if ($isDomainRoute === null) {
            return null;
        }

        $shouldComment = ($isDomainRoute && $this->hasSeenNonDomainRoute) || (! $isDomainRoute && $this->hasSeenDomainRoute);

        if ($isDomainRoute) {
            $this->hasSeenDomainRoute = true;
        } else {
            $this->hasSeenNonDomainRoute = true;
        }

        if (! $shouldComment || $this->hasComment($node)) {
            return null;
        }

        $node->setAttribute('comments', array_merge([
            new Comment('// ' . self::COMMENT_MARKER . ' during matching. Review route behavior if this file mixes both route types.'),
        ], $node->getComments()));

        return $node;
    }

    private function resetStateWhenTraversalRestarts(Expression $expression): void
    {
        $filePath = $this->file->getFilePath();
        $currentLine = $expression->getStartLine();

        if ($filePath !== $this->currentFilePath || $currentLine <= $this->lastProcessedLine) {
            $this->currentFilePath = $filePath;
            $this->lastProcessedLine = 0;
            $this->hasSeenDomainRoute = false;
            $this->hasSeenNonDomainRoute = false;
        }

        $this->lastProcessedLine = $currentLine;
    }

    private function resolveRouteType(Node $node): ?bool
    {
        $hasDomain = false;

        while ($node instanceof MethodCall) {
            if ($this->isName($node->name, 'domain')) {
                $hasDomain = true;
            }

            $node = $node->var;
        }

        if (! $node instanceof StaticCall) {
            return null;
        }

        if ($this->isName($node->name, 'domain')) {
            $hasDomain = true;
        }

        if (! $this->isName($node->class, 'Route') && ! $this->isName($node->class, 'Illuminate\\Support\\Facades\\Route')) {
            return null;
        }

        if ($hasDomain) {
            return true;
        }

        if (! in_array($this->getName($node->name), ['any', 'delete', 'fallback', 'get', 'match', 'options', 'patch', 'post', 'put', 'redirect', 'resource', 'view'], true)) {
            return null;
        }

        return $hasDomain;
    }

    private function hasComment(Expression $expression): bool
    {
        foreach ($expression->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add an advisory comment when a route file mixes explicit-domain and non-domain routes',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
Route::get('/health', HealthController::class);
Route::domain('{account}.example.com')->get('/dashboard', DashboardController::class);
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
Route::get('/health', HealthController::class);
// Laravel 13: explicit domain routes now take precedence over non-domain routes during matching. Review route behavior if this file mixes both route types.
Route::domain('{account}.example.com')->get('/dashboard', DashboardController::class);
CODE_SAMPLE,
                ),
            ],
        );
    }
}
