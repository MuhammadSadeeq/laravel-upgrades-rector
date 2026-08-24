<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Flags every ->change() call on a Blueprint receiver without explicit index
 * modifiers (plan P3-03, ColumnChangeRequiresModifiersRule).
 *
 * Port of the former UpdateColumnModificationRector advisory comment, now
 * as a structured PHPStan rule with file/line/severity metadata.
 */
/**
 * @implements Rule<MethodCall>
 */
final class ColumnChangeRequiresModifiersRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall || ! $node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->toLowerString() !== 'change') {
            return [];
        }

        $type = $scope->getType($node->var);

        if (! (new ObjectType('Illuminate\Database\Schema\Blueprint'))->isSuperTypeOf($type)->yes()) {
            return [];
        }

        // Check for chained ->primary()/->unique()/->index() on the var chain.
        $hasIndexModifier = false;
        $cursor = $node->var;

        while ($cursor instanceof MethodCall) {
            $methodName = $cursor->name instanceof Identifier ? $cursor->name->toLowerString() : '';

            if (in_array($methodName, ['primary', 'unique', 'index'], true)) {
                $hasIndexModifier = true;

                break;
            }

            $cursor = $cursor->var;
        }

        $message = 'Column modification via ->change() requires all column modifiers to be re-specified in Laravel 11+.';
        $action = 'Re-specify modifiers explicitly or use schema:dump to capture the current state.';

        if ($hasIndexModifier) {
            $message .= ' Index modifiers (->primary()/->unique()/->index()) are NOT preserved by ->change().';
            $action = 'Drop the column and re-add it with all modifiers, or use DB::statement for precise DDL.';
        }

        return [
            RuleErrorBuilder::message($message)
                ->identifier('laravelUpgrade.columnChangeRequiresModifiers')
                ->tip($action)
                ->build(),
        ];
    }
}
