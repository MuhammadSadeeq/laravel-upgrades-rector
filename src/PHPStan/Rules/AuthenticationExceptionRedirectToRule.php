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
 * Laravel 11: AuthenticationException::redirectTo() receives the current
 * request so applications can choose a redirect based on request context.
 * The Rector transform handles calls where a typed Request is in scope; this
 * rule reports the remaining no-argument calls for manual review.
 *
 * @implements Rule<MethodCall>
 */
final class AuthenticationExceptionRedirectToRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall
            || ! $node->name instanceof Identifier
            || $node->name->toLowerString() !== 'redirectto'
            || $node->getArgs() !== []) {
            return [];
        }

        if (! (new ObjectType('Illuminate\\Auth\\AuthenticationException'))
            ->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'AuthenticationException::redirectTo() now requires a Request argument in Laravel 11.'
            )->identifier('laravelUpgrade.authenticationExceptionRedirectTo')
                ->tip('Pass a Request instance to redirectTo(); no safe typed request variable was available to the Rector transform.')
                ->build(),
        ];
    }
}
