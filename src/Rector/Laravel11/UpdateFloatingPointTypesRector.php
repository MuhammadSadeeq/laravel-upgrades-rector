<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateFloatingPointTypesRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof MethodCall) {
            return null;
        }

        if (!$this->isObjectType($node->var, new ObjectType('Illuminate\Database\Schema\Blueprint'))) {
            return null;
        }

        $methodName = $this->getName($node->name);

        if ($methodName === null) {
            return null;
        }

        // Handle double() method - remove total and places arguments
        if ($methodName === 'double') {
            if (count($node->args) > 1) {
                $node->args = [$node->args[0]];

                return $node;
            }
        }

        // Handle float() method - remove total and places, add precision parameter if needed
        if ($methodName === 'float') {
            if (count($node->args) > 1) {
                $node->args = [$node->args[0]];

                $existingComments = $node->getComments();
                $newComment = new \PhpParser\Comment(
                    "// Laravel 11: float() method signature changed. Use precision parameter if needed: ->float('amount', precision: 53)"
                );
                $node->setAttribute('comments', array_merge([$newComment], $existingComments));
                $node->setAttribute(\Rector\NodeTypeResolver\Node\AttributeKey::ORIGINAL_NODE, null);

                return $node;
            }
        }

        // Handle unsigned methods that have been removed
        $removedUnsignedMethods = [
            'unsignedDecimal',
            'unsignedDouble',
            'unsignedFloat',
        ];

        if (in_array($methodName, $removedUnsignedMethods, true)) {
            $baseMethod = match ($methodName) {
                'unsignedDecimal' => 'decimal',
                'unsignedDouble' => 'double',
                'unsignedFloat' => 'float',
            };

            $node->name = new Identifier($baseMethod);

            // For double and float, remove precision arguments (keep only column name)
            if (($baseMethod === 'double' || $baseMethod === 'float') && count($node->args) > 1) {
                $node->args = [$node->args[0]];
            }

            return new MethodCall($node, new Identifier('unsigned'));
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update floating-point column types for Laravel 11 compatibility',
            [
                new CodeSample(
                    '$table->double(\'amount\', 8, 2)',
                    '$table->double(\'amount\')',
                ),
                new CodeSample(
                    '$table->float(\'amount\', 8, 2)',
                    '$table->float(\'amount\')',
                ),
                new CodeSample(
                    '$table->unsignedDouble(\'amount\')',
                    '$table->double(\'amount\')->unsigned()',
                ),
            ],
        );
    }
}
