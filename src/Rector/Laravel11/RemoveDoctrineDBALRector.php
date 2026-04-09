<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveDoctrineDBALRector extends AbstractRector
{
    /** @var array<string, string> */
    private array $renamedMethods = [
        'getAllTables' => 'getTables',
        'getAllViews' => 'getViews',
        'getAllTypes' => 'getTypes',
    ];

    /** @var array<int, string> */
    private array $removedMethods = [
        'getDoctrineConnection',
        'getDoctrineSchemaManager',
        'getDoctrineColumn',
        'registerDoctrineType',
        'isDoctrineAvailable',
    ];

    /** @var array<int, string> */
    private array $schemaFacades = [
        'Illuminate\\Support\\Facades\\Schema',
        'Illuminate\\Support\\Facades\\DB',
    ];

    public function getNodeTypes(): array
    {
        return [Use_::class, Expression::class, StaticCall::class, Array_::class];
    }

    /**
     * @return int|Node|null
     */
    public function refactor(Node $node)
    {
        if ($node instanceof Use_) {
            return $this->refactorUseStatement($node);
        }

        if ($node instanceof Expression) {
            return $this->refactorExpression($node);
        }

        if ($node instanceof StaticCall) {
            return $this->refactorStaticCall($node);
        }

        if ($node instanceof Array_) {
            return $this->refactorConfigArray($node);
        }

        return null;
    }

    private function refactorUseStatement(Use_ $node): ?int
    {
        $nonDoctrineUses = [];

        foreach ($node->uses as $use) {
            $className = $this->getName($use->name);

            if ($className === null) {
                $nonDoctrineUses[] = $use;
                continue;
            }

            if (! str_starts_with($className, 'Doctrine\DBAL\\')) {
                $nonDoctrineUses[] = $use;
            }
        }

        if (count($nonDoctrineUses) === count($node->uses)) {
            return null;
        }

        if ($nonDoctrineUses === []) {
            return NodeTraverser::REMOVE_NODE;
        }

        $node->uses = $nonDoctrineUses;

        return null;
    }

    private function refactorExpression(Expression $node): ?Node
    {
        $expr = $node->expr;

        if (!$expr instanceof MethodCall) {
            return null;
        }

        $methodName = $this->getName($expr->name);

        if ($methodName === null) {
            return null;
        }

        if (!in_array($methodName, $this->removedMethods, true)) {
            return null;
        }

        $existingComments = $node->getComments();

        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), 'Laravel 11:')) {
                return null;
            }
        }

        $newComment = new Comment(
            "// Laravel 11: {$methodName}() has been removed. Doctrine DBAL is no longer required."
        );

        $node->setAttribute('comments', array_merge([$newComment], $existingComments));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function refactorStaticCall(StaticCall $node): ?Node
    {
        if (! $node->class instanceof Name) {
            return null;
        }

        $methodName = $this->getName($node->name);

        if ($methodName === null) {
            return null;
        }

        if (! isset($this->renamedMethods[$methodName])) {
            return null;
        }

        if (! $this->isSchemaFacade($node->class)) {
            return null;
        }

        $node->name = new Identifier($this->renamedMethods[$methodName]);

        return $node;
    }

    private function refactorConfigArray(Array_ $node): ?Node
    {
        $dbalItem = $this->findArrayItemByKey($node, 'dbal');

        if (! $dbalItem instanceof ArrayItem || ! $dbalItem->value instanceof Array_) {
            return null;
        }

        $dbalTypesItem = $this->findArrayItemByKey($dbalItem->value, 'types');

        if (! $dbalTypesItem instanceof ArrayItem) {
            return null;
        }

        $remainingDbalItems = [];

        foreach ($dbalItem->value->items as $item) {
            if ($item === null || $item === $dbalTypesItem) {
                continue;
            }

            $remainingDbalItems[] = $item;
        }

        if ($remainingDbalItems === []) {
            $node->items = array_values(array_filter(
                $node->items,
                static fn ($item): bool => $item !== $dbalItem
            ));

            return $node;
        }

        $dbalItem->value->items = $remainingDbalItems;

        return $node;
    }

    private function isSchemaFacade(Name $className): bool
    {
        foreach ($this->schemaFacades as $fqcn) {
            if ($this->isName($className, $fqcn)) {
                return true;
            }
        }

        return false;
    }

    private function findArrayItemByKey(Array_ $array, string $key): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof Node\Scalar\String_) {
                continue;
            }

            if ($item->key->value === $key) {
                return $item;
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove Doctrine DBAL related code and update deprecated schema methods for Laravel 11',
            [
                new CodeSample(
                    'Schema::getAllTables()',
                    'Schema::getTables()',
                ),
                new CodeSample(
                    '$connection->getDoctrineConnection()',
                    '// Laravel 11: getDoctrineConnection() has been removed. Doctrine DBAL is no longer required.
$connection->getDoctrineConnection()',
                ),
            ],
        );
    }
}
