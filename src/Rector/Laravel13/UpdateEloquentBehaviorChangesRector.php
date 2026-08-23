<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\CommentInserter;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateEloquentBehaviorChangesRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade eloquent-behavior';

    public function __construct(
        private readonly CommentInserter $commentInserter,
    ) {}

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        $changed = false;
        $className = $this->getName($node);

        if ($this->extendsClass($node, 'Illuminate\\Database\\Eloquent\\Model', 'Model')) {
            foreach ($node->getMethods() as $method) {
                if (! $this->isBootMethod($method) || ! $this->containsNestedInstantiation($method, $className)) {
                    continue;
                }

                if (! $this->commentInserter->addComment(
                    $method,
                    self::COMMENT_MARKER,
                    'instantiating models during boot() is now disallowed. Move this logic outside the model boot cycle.'
                )) {
                    continue;
                }

                $changed = true;
            }
        }

        if ($this->extendsClass($node, 'Illuminate\\Database\\Eloquent\\Relations\\MorphPivot', 'MorphPivot') && ! $this->hasTableProperty($node) && $this->commentInserter->addComment(
            $node,
            self::COMMENT_MARKER,
            'inferred morph pivot table names are now pluralized. Define protected $table explicitly if you relied on the previous singular inferred name.'
        )) {
            $changed = true;
        }

        if ($this->implementsInterface($node, 'Illuminate\\Contracts\\Queue\\ShouldQueue', 'ShouldQueue') && $this->hasEloquentCollectionProperty($node) && $this->commentInserter->addComment(
            $node,
            self::COMMENT_MARKER,
            'serialized Eloquent collections now restore eager-loaded relations. Review queued-job logic that expected relations to be absent after deserialization.'
        )) {
            $changed = true;
        }

        if (! $changed) {
            return null;
        }


        return $node;
    }

    private function isBootMethod(ClassMethod $method): bool
    {
        $methodName = $this->getName($method);

        if ($methodName === 'boot') {
            return true;
        }

        return is_string($methodName) && str_starts_with($methodName, 'boot') && strlen($methodName) > 4;
    }

    private function containsNestedInstantiation(ClassMethod $method, ?string $className): bool
    {
        if ($method->stmts === null) {
            return false;
        }

        $hasNestedInstantiation = false;

        $this->traverseNodesWithCallable($method->stmts, function (Node $node) use ($className, &$hasNestedInstantiation): ?int {
            if (! $node instanceof New_ || ! $node->class instanceof Name) {
                return null;
            }

            $newClassName = $node->class->toString();

            if (in_array($newClassName, ['self', 'static'], true) || ($className !== null && $newClassName === $className)) {
                $hasNestedInstantiation = true;
                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }

            return null;
        });

        return $hasNestedInstantiation;
    }

    private function hasTableProperty(Class_ $class): bool
    {
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Property) {
                continue;
            }

            foreach ($stmt->props as $property) {
                if ($property->name->name === 'table') {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasEloquentCollectionProperty(Class_ $class): bool
    {
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Property || ! $stmt->type instanceof Name) {
                continue;
            }

            $typeName = $stmt->type->toString();

            if ($typeName === 'Illuminate\\Database\\Eloquent\\Collection') {
                return true;
            }

            if ($typeName === 'Collection' && $this->fileHasImport('Illuminate\\Database\\Eloquent\\Collection')) {
                return true;
            }
        }

        return false;
    }

    private function extendsClass(Class_ $class, string $fullyQualifiedName, string $shortName): bool
    {
        if (! $class->extends instanceof Name) {
            return false;
        }

        $extendsName = $class->extends->toString();

        if ($extendsName === $fullyQualifiedName) {
            return true;
        }

        return $extendsName === $shortName && $this->fileHasImport($fullyQualifiedName);
    }

    private function implementsInterface(Class_ $class, string $fullyQualifiedName, string $shortName): bool
    {
        foreach ($class->implements as $implement) {
            $implementName = $implement->toString();

            if ($implementName === $fullyQualifiedName) {
                return true;
            }

            if ($implementName === $shortName && $this->fileHasImport($fullyQualifiedName)) {
                return true;
            }
        }

        return false;
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
            'Add advisory comments for Laravel 13 Eloquent behavior changes',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class User extends Model
{
    protected static function boot()
    {
        parent::boot();

        (new static())->getTable();
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class User extends Model
{
    // Laravel 13: instantiating models during boot() is now disallowed. Move this logic outside the model boot cycle.
    protected static function boot()
    {
        parent::boot();

        (new static())->getTable();
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
