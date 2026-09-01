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
 * Laravel 11: float() dropped its total/places arguments; the column now
 * stores full precision. Flags float()/double() calls with extra arguments
 * so developers switch to decimal() for fixed-precision storage.
 *
 * @implements Rule<MethodCall>
 */
final class FloatPrecisionDroppedRule implements Rule
{
    /** @var list<string> */
    private const REMOVED_NAMED_ARGUMENTS = ['total', 'places'];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->toLowerString();

        if ($methodName !== 'float' && $methodName !== 'double') {
            return [];
        }

        $type = $scope->getType($node->var);

        if (! (new ObjectType('Illuminate\Database\Schema\Blueprint'))->isSuperTypeOf($type)->yes()) {
            return [];
        }

        $argumentCount = count($node->getArgs());
        $hasRemovedNamedArgument = false;

        foreach ($node->getArgs() as $argument) {
            if ($argument->name !== null
                && in_array($argument->name->toLowerString(), self::REMOVED_NAMED_ARGUMENTS, true)) {
                $hasRemovedNamedArgument = true;
                break;
            }
        }

        // Laravel 11 retains float($column, $precision), including the
        // named precision: form. The legacy total:/places: names are still
        // findings even when their argument count looks valid; double() still
        // accepts only its column argument.
        if (! $hasRemovedNamedArgument
            && (($methodName === 'float' && $argumentCount <= 2)
                || ($methodName === 'double' && $argumentCount <= 1))) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf(
                    '%s() no longer accepts precision/scale arguments in Laravel 11.',
                    ucfirst($methodName)
                )
            )->identifier('laravelUpgrade.floatPrecisionDropped')
                ->tip('Use decimal(\'column\', 8, 2) for fixed precision or float(\'column\', precision: N).')
                ->build(),
        ];
    }
}
