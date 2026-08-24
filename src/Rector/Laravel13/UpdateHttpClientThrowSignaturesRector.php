<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\TodoNopFactory;
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

/**
 * Laravel 13 added optional callback parameters to
 * Illuminate\Http\Client\Response::throw() and ->throwIf():
 *
 *   throw($callback = null)
 *   throwIf($condition, $callback = null)
 *
 * Overrides of these methods must accept at least the same parameters. This
 * rule only APPENDS the missing named parameters; it never renames or removes
 * existing ones (decision D7). If an override already declares a parameter in
 * the target position under a different name, it is left untouched and a TODO
 * comment is inserted instead.
 */
final class UpdateHttpClientThrowSignaturesRector extends AbstractRector
{
    private const TARGET_PARENT_CLASS = 'Illuminate\Http\Client\Response';

    private const MARKER = '@laravel-upgrade http-client-throw';

    /**
     * @var array<string, array<int, string>>
     */
    private const REQUIRED_PARAMS = [
        'throw' => ['callback'],
        'throwIf' => ['condition', 'callback'],
    ];

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        if (! $this->extendsTargetClass($node)) {
            return null;
        }

        $hasChanges = false;

        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof ClassMethod) {
                continue;
            }

            $methodName = $this->getName($stmt->name);

            if ($methodName === null || ! isset(self::REQUIRED_PARAMS[$methodName])) {
                continue;
            }

            if ($this->appendMissingParams($stmt, self::REQUIRED_PARAMS[$methodName])) {
                $hasChanges = true;
            }
        }

        return $hasChanges ? $node : null;
    }

    /**
     * Appends missing trailing parameters by name. Returns false when nothing
     * is missing or when the signature cannot be safely extended.
     *
     * @param  list<string>  $requiredNames
     */
    private function appendMissingParams(ClassMethod $method, array $requiredNames): bool
    {
        $existingNames = [];

        foreach ($method->params as $param) {
            if ($param->var instanceof Variable && is_string($param->var->name)) {
                $existingNames[] = $param->var->name;
            } else {
                // Variadics, dynamic or destructured names — do not touch.
                return $this->leaveTodoInstead($method);
            }
        }

        if ($existingNames === []) {
            return $this->addTrailingParams($method, $requiredNames);
        }

        // Verify positional compatibility: every existing name must match the
        // expected name at that position (prefix match for overrides that add
        // extra optional params of their own).
        $expectedPrefix = array_slice($requiredNames, 0, count($existingNames));
        $positionallyCompatible = true;

        foreach ($expectedPrefix as $index => $expectedName) {
            if (($existingNames[$index] ?? null) !== $expectedName) {
                $positionallyCompatible = false;

                break;
            }
        }

        if (! $positionallyCompatible) {
            return $this->leaveTodoInstead($method);
        }

        return $this->addTrailingParams($method, array_slice(
            $requiredNames,
            count($existingNames)
        ));
    }

    /**
     * @param  list<string>  $names
     */
    private function addTrailingParams(ClassMethod $method, array $names): bool
    {
        if ($names === []) {
            return false;
        }

        foreach ($names as $name) {
            $method->params[] = new Param(new Variable($name), new ConstFetch(new Name('null')));
        }

        // Keep parent::throw()/parent::throwIf() calls forwarding everything.
        $this->forwardCallbackToParentCall($method, (string) $this->getName($method->name));

        return true;
    }

    private function leaveTodoInstead(ClassMethod $method): bool
    {
        foreach ($method->getComments() as $comment) {
            if (str_contains($comment->getText(), self::MARKER)) {
                return false;
            }
        }

        $nop = TodoNopFactory::create(sprintf(
            '%s Laravel 13 changed this signature to accept a nullable callback; '
            .'the override does not match positionally — reconcile it manually.',
            self::MARKER
        ));

        $comments = $method->getComments();
        $comments[] = $nop->getComments()[0];
        $method->setAttribute(AttributeKey::COMMENTS, $comments);

        return true;
    }

    private function forwardCallbackToParentCall(ClassMethod $method, string $methodName): void
    {
        $expectedArgumentCount = $methodName === 'throw' ? 1 : 2;

        $this->traverseNodesWithCallable($method->stmts ?? [], function (Node $node) use ($methodName, $expectedArgumentCount): ?int {
            if (! $node instanceof StaticCall) {
                return null;
            }

            if (! $this->isName($node->class, 'parent') || ! $this->isName($node->name, $methodName)) {
                return null;
            }

            // Forward any arguments the parent call is still missing,
            // matched positionally to the interface parameter names.
            $argumentCount = count($node->args);

            for ($index = $argumentCount; $index < $expectedArgumentCount; $index++) {
                $variableName = self::REQUIRED_PARAMS[$methodName][$index] ?? 'callback';
                $node->args[] = new Arg(new Variable($variableName));
            }

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        });
    }

    private function extendsTargetClass(Class_ $node): bool
    {
        if ($node->extends === null) {
            return false;
        }

        $parentName = $this->getName($node->extends);

        if ($parentName === self::TARGET_PARENT_CLASS) {
            return true;
        }

        // A short "Response" is ambiguous without an import of the framework class.
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

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Append the Laravel 13 callback parameters to HTTP Client Response throw()/throwIf() overrides',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Http\Client\Response;

class CustomResponse extends Response
{
    public function throw()
    {
        logger('throwing');
        parent::throw();
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Http\Client\Response;

class CustomResponse extends Response
{
    public function throw($callback = null)
    {
        logger('throwing');
        parent::throw($callback);
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
