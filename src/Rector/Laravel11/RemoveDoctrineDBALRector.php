<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\ImportUsageChecker;
use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Use_;
use PHPStan\Analyser\Scope;
use Rector\NodeTypeResolver\Node\AttributeKey;
use PhpParser\NodeTraverser;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Laravel 11 removed the Doctrine DBAL dependency from the schema layer.
 *
 * - getAllTables()/getAllViews()/getAllTypes() are renamed to
 *   getTables()/getViews()/getTypes() — but only on the Schema facade, never
 *   on DB (whose methods were NOT renamed);
 * - getDoctrine*()/registerDoctrineType() calls receive an advisory comment:
 *   on a confirmed Illuminate\Database\Connection receiver it is high
 *   confidence, on unresolved receivers low confidence wording is used;
 * - Doctrine\DBAL imports are removed only when nothing else in the file
 *   references them.
 */
final class RemoveDoctrineDBALRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade dbal';

    /**
     * @var array<string, string>
     */
    private const RENAMED_METHODS = [
        'getAllTables' => 'getTables',
        'getAllViews' => 'getViews',
        'getAllTypes' => 'getTypes',
    ];

    /**
     * @var list<string>
     */
    private const REMOVED_METHODS = [
        'getDoctrineConnection',
        'getDoctrineSchemaManager',
        'getDoctrineColumn',
        'registerDoctrineType',
        'isDoctrineAvailable',
        'usingNativeSchemaOperations',
        'useNativeSchemaOperationsIfPossible',
    ];

    private ImportUsageChecker $importUsageChecker;

    public function __construct()
    {
        $this->importUsageChecker = new ImportUsageChecker();
    }

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
            return $this->refactorAdvisory($node);
        }

        if ($node instanceof StaticCall) {
            return $this->refactorStaticCall($node);
        }

        if ($node instanceof Array_) {
            return $this->removeDbalTypesFromConfigArray($node);
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rename DBAL-era Schema inspection methods and flag removed Doctrine methods for Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$tables = Schema::getAllTables();
$type = $connection->getDoctrineColumn('users', 'email');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$tables = Schema::getTables();
// @laravel-upgrade dbal: getDoctrineColumn() was removed in Laravel 11. Use native schema introspection instead.
$type = $connection->getDoctrineColumn('users', 'email');
CODE_SAMPLE,
                ),
            ],
        );
    }

    private function refactorUseStatement(Use_ $use): ?int
    {
        $keptUseItems = [];
        $removedCount = 0;

        foreach ($use->uses as $useItem) {
            $fqcn = $useItem->name->toString();

            if (! str_starts_with($fqcn, 'Doctrine\DBAL\\')) {
                $keptUseItems[] = $useItem;

                continue;
            }

            $alias = $useItem->getAlias()->name;
            $used = $this->importUsageChecker->isUsed(
                $this->file->getNewStmts(),
                $this->file->getOriginalFileContent(),
                $fqcn,
                $alias
            );

            if ($used) {
                $keptUseItems[] = $useItem;

                continue;
            }

            ++$removedCount;
        }

        if ($removedCount === 0) {
            return null;
        }

        if ($keptUseItems === []) {
            return NodeTraverser::REMOVE_NODE;
        }

        $use->uses = $keptUseItems;

        return null;
    }

    private function refactorStaticCall(StaticCall $staticCall): ?Node
    {
        if (! $staticCall->class instanceof Name) {
            return null;
        }

        $methodName = $this->getName($staticCall->name);

        if ($methodName === null || ! isset(self::RENAMED_METHODS[$methodName])) {
            return null;
        }

        // Only the Schema facade had these renames. DB::getAll*() never existed.
        if (! $this->isName($staticCall->class, 'Illuminate\Support\Facades\Schema')
            && ! $this->isName($staticCall->class, 'Schema')) {
            return null;
        }

        $staticCall->name = new Identifier(self::RENAMED_METHODS[$methodName]);

        return $staticCall;
    }

    private function refactorAdvisory(Expression $expression): ?Node
    {
        foreach ($expression->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $removedMethod = $this->findRemovedMethodCall($expression->expr);

        if ($removedMethod === null) {
            return null;
        }

        [$methodName, $confidence] = $removedMethod;

        $note = sprintf(
            '// %s: %s() was removed in Laravel 11 (%s confidence). Doctrine DBAL is no longer required; '
            . 'migrate to native schema operations.',
            self::COMMENT_MARKER,
            $methodName,
            $confidence
        );

        $comments = $expression->getComments();
        $comments[] = new Comment($note);
        $expression->setAttribute('comments', $comments);

        return $expression;
    }

    /**
     * @return array{string, string}|null method name + confidence
     */
    private function findRemovedMethodCall(Node $node): ?array
    {
        $found = null;

        $this->traverseNodesWithCallable($node, function (Node $subNode) use (&$found): ?int {
            if ($found !== null) {
                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }

            if (! $subNode instanceof MethodCall && ! $subNode instanceof StaticCall) {
                return null;
            }

            $methodName = $this->getName($subNode->name);

            if ($methodName === null || ! in_array($methodName, self::REMOVED_METHODS, true)) {
                return null;
            }

            if ($subNode instanceof StaticCall) {
                // Never fabricate advice on facades that never had these methods.
                return null;
            }

            $confidence = $this->receiverConfidence($subNode, $methodName);

            if ($confidence === null) {
                return null;
            }

            $found = [$methodName, $confidence];

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        });

        return $found;
    }

    private function removeDbalTypesFromConfigArray(Array_ $array): ?Node
    {
        $dbalItem = $this->findArrayItemByKey($array, 'dbal');

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
            $array->items = array_values(array_filter(
                $array->items,
                static fn ($item): bool => $item !== $dbalItem
            ));

            return $array;
        }

        $dbalItem->value->items = $remainingDbalItems;

        return $array;
    }

    private function findArrayItemByKey(Array_ $array, string $key): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            if ($item->key->value === $key) {
                return $item;
            }
        }

        return null;
    }

    private function receiverConfidence(MethodCall $methodCall, string $methodName): ?string
    {
        $isConnection = $this->isObjectType($methodCall->var, new ObjectType('Illuminate\Database\Connection'));

        if ($isConnection) {
            return 'high';
        }

        $scopeBased = false;

        $scopeAttribute = $methodCall->getAttribute(AttributeKey::SCOPE);

        if ($scopeAttribute instanceof Scope) {
            try {
                $type = $scopeAttribute->getType($methodCall->var);
                $scopeBased = ! $type->equals(new \PHPStan\Type\MixedType());
            } catch (\Throwable) {
                $scopeBased = false;
            }
        }

        if ($scopeBased) {
            // A known non-connection type: these names simply don't belong here.
            return null;
        }

        // Unresolved receiver + unmistakably Doctrine method name → low confidence.
        $unmistakable = str_starts_with($methodName, 'getDoctrine') || $methodName === 'registerDoctrineType';

        return $unmistakable ? 'low' : null;
    }
}
