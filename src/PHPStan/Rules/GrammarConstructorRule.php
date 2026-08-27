<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Laravel 12: database Grammar constructors require a Connection.
 *
 * @implements Rule<New_>
 */
final class GrammarConstructorRule implements Rule
{
    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof New_ || $node->args !== []) {
            return [];
        }

        // Resolve the constructed expression rather than trusting a class
        // name ending in Grammar: application classes may use that suffix
        // without extending Laravel's base grammar.
        if (! (new ObjectType('Illuminate\\Database\\Grammar'))
            ->isSuperTypeOf($scope->getType($node))->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Laravel 12 grammar constructors require a Connection argument.'
            )->identifier('laravelUpgrade.grammarConstructor')
                ->tip('Pass the Connection instance when constructing a query grammar.')
                ->build(),
        ];
    }
}
