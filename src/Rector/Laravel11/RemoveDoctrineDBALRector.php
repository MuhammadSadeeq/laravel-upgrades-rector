<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveDoctrineDBALRector extends AbstractRector
{
    /** @var array<int, string> */
    private array $removedMethods = [
        "useNativeSchemaOperationsIfPossible",
        "usingNativeSchemaOperations",
        "isDoctrineAvailable",
        "getDoctrineConnection",
        "getDoctrineSchemaManager",
        "getDoctrineColumn",
        "registerDoctrineType",
        "getDoctrineTableDiff",
    ];

    /** @var array<int, string> */
    private array $removedClasses = [
        "Illuminate\Database\DBAL\TimestampType",
        "Illuminate\Database\Schema\Grammars\ChangeColumn",
        "Illuminate\Database\Schema\Grammars\RenameColumn",
    ];

    /** @var array<string, string> */
    private array $replacementMethods = [
        "getAllTables" => "getTables",
        "getAllViews" => "getViews",
        "getAllTypes" => "getTypes",
    ];

    public function getNodeTypes(): array
    {
        return [MethodCall::class, StaticCall::class, Use_::class];
    }

    public function refactor(Node $node): ?Node
    {
        // Handle use statements for removed classes
        if ($node instanceof Use_) {
            foreach ($node->uses as $use) {
                $className = $this->getName($use->name);
                if (in_array($className, $this->removedClasses, true)) {
                    // Add comment about removal
                    $node->setAttribute("comments", [
                        new \PhpParser\Comment\Doc(
                            "/** Laravel 11: {$className} has been removed. Doctrine DBAL is no longer required. */",
                        ),
                    ]);
                    return null; // Remove the use statement
                }
            }
            return null;
        }

        // Handle method calls
        if ($node instanceof MethodCall || $node instanceof StaticCall) {
            $methodName = $this->getName($node->name);

            // Handle removed methods
            if (in_array($methodName, $this->removedMethods, true)) {
                $node->setAttribute("comments", [
                    new \PhpParser\Comment\Doc(
                        "/** Laravel 11: {$methodName}() method has been removed. Doctrine DBAL is no longer required. */",
                    ),
                ]);
                return $node;
            }

            // Handle replaced deprecated methods
            if (isset($this->replacementMethods[$methodName])) {
                $newMethodName = $this->replacementMethods[$methodName];
                $node->name = new \PhpParser\Node\Identifier($newMethodName);
                return $node;
            }

            // Handle getColumnType method
            if ($methodName === "getColumnType") {
                $node->setAttribute("comments", [
                    new \PhpParser\Comment\Doc(
                        "/** Laravel 11: getColumnType() now returns actual type, not Doctrine DBAL equivalent. */",
                    ),
                ]);
                return $node;
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Remove Doctrine DBAL related code and update deprecated schema methods for Laravel 11",
            [
                new CodeSample("Schema::getAllTables()", "Schema::getTables()"),
                new CodeSample(
                    '$connection->getDoctrineConnection()',
                    '/** Laravel 11: getDoctrineConnection() method has been removed. Doctrine DBAL is no longer required. */
$connection->getDoctrineConnection()',
                ),
            ],
        );
    }
}
