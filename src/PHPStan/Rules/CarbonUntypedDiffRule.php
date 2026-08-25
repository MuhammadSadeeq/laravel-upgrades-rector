<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\MixedType;

/**
 * Carbon 3: diffIn* methods return signed floats instead of unsigned ints.
 * Flags diff method calls on unresolved receivers so users without Larastan
 * still get a pointer.
 *
 * @implements Rule<MethodCall>
 */
final class CarbonUntypedDiffRule implements Rule
{
    private const DIFF_METHODS = [
        'diffindays', 'diffinhours', 'diffinminutes', 'diffinseconds',
        'diffinweeks', 'diffinmonths', 'diffinyears',
        'diffinmilliseconds', 'diffinmicroseconds',
    ];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        if (! in_array($node->name->toLowerString(), self::DIFF_METHODS, true)) {
            return [];
        }

        $type = $scope->getType($node->var);

        if (! $type instanceof MixedType) {
            return [];
        }

        // Unresolved receiver + diff method name → low-confidence pointer.
        return [
            RuleErrorBuilder::message(
                'diffIn*() returns a signed float in Carbon 3; verify this call handles negative values.'
            )->identifier('laravelUpgrade.carbonUntypedDiff')
                ->tip('Wrap with (int) abs(...) to preserve the old absolute-int behaviour.')
                ->build(),
        ];
    }
}
