<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateHttpClientThrowSignaturesRector extends AbstractRector
{
    private const TARGET_PARENT_CLASS = 'Illuminate\Http\Client\Response';

    /** @var string[] */
    private const TARGET_METHODS = ['throw', 'throwIf'];

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Class_) {
            return null;
        }

        if (!$this->extendsTargetClass($node)) {
            return null;
        }

        $hasChanges = false;

        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof ClassMethod) {
                continue;
            }

            $methodName = $this->getName($stmt->name);

            if ($methodName === null || !in_array($methodName, self::TARGET_METHODS, true)) {
                continue;
            }

            $this->configureMethodSignature($stmt, $methodName);
            $this->forwardCallbackToParentCall($stmt, $methodName);
            $this->removeAdvisoryComment($stmt);
            $stmt->setAttribute(AttributeKey::ORIGINAL_NODE, null);
            $hasChanges = true;
        }

        return $hasChanges ? $node : null;
    }

    private function extendsTargetClass(Class_ $node): bool
    {
        if ($node->extends === null) {
            return false;
        }

        $parentName = $node->extends->toString();

        if ($parentName === self::TARGET_PARENT_CLASS) {
            return true;
        }

        return $parentName === 'Response' && $this->fileHasImport(self::TARGET_PARENT_CLASS);
    }

    private function fileHasImport(string $fullyQualifiedName): bool
    {
        $hasImport = false;

        $this->traverseNodesWithCallable($this->file->getNewStmts(), function (Node $node) use ($fullyQualifiedName, &$hasImport): ?int {
            if (! $node instanceof Use_) {
                return null;
            }

            foreach ($node->uses as $use) {
                if ($use->name->toString() === $fullyQualifiedName) {
                    $hasImport = true;
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
            }

            return null;
        });

        return $hasImport;
    }

    private function configureMethodSignature(ClassMethod $method, string $methodName): void
    {
        if ($methodName === 'throw') {
            $method->params = [
                new Param(new Variable('callback'), new ConstFetch(new Name('null'))),
            ];

            return;
        }

        $method->params = [
            new Param(new Variable('condition')),
            new Param(new Variable('callback'), new ConstFetch(new Name('null'))),
        ];
    }

    private function forwardCallbackToParentCall(ClassMethod $method, string $methodName): void
    {
        $expectedArgumentCount = $methodName === 'throw' ? 1 : 2;

        $this->traverseNodesWithCallable($method->stmts ?? [], function (Node $node) use ($methodName, $expectedArgumentCount): ?int {
            if (!$node instanceof StaticCall) {
                return null;
            }

            if (!$this->isName($node->class, 'parent') || !$this->isName($node->name, $methodName)) {
                return null;
            }

            if ($methodName === 'throwIf' && $node->args === []) {
                $node->args[] = new Arg(new Variable('condition'));
            }

            if (count($node->args) < $expectedArgumentCount) {
                $node->args[] = new Arg(new Variable('callback'));
            }

            return null;
        });
    }

    private function removeAdvisoryComment(ClassMethod $node): void
    {
        $comments = [];

        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), 'Laravel 13: The throw()/throwIf() method signatures have changed.')) {
                continue;
            }

            $comments[] = $comment;
        }

        $node->setAttribute('comments', $comments);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update HTTP Client throw()/throwIf() override signatures for Laravel 13',
            [
                new CodeSample(
                    'class CustomResponse extends Response {
    public function throw() { /* ... */ }
}',
                    'class CustomResponse extends Response {
    public function throw($callback = null) { /* ... */ }
}'
                ),
            ]
        );
    }
}
