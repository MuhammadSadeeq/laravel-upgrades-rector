<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Laravel 13 renamed bootstrap/app.php's validateCsrfTokens() to
 * preventRequestForgery() on Illuminate\Foundation\Configuration\Middleware
 * (verified: preventRequestForgery(array $except = [], bool $originOnly =
 * false, bool $allowSameSite = false)).
 *
 * Class renames are handled separately by core RenameClassRector configured
 * in the Laravel 13 set.
 */
final class RenameValidateCsrfTokensMethodRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof MethodCall || ! $this->isName($node->name, 'validateCsrfTokens')) {
            return null;
        }

        if (! $this->isObjectType($node->var, new ObjectType('Illuminate\Foundation\Configuration\Middleware'))) {
            return null;
        }

        $node->name = new Identifier('preventRequestForgery');

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rename validateCsrfTokens() to preventRequestForgery() in bootstrap/app.php',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['webhook/*']);
})
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
->withMiddleware(function (Middleware $middleware) {
    $middleware->preventRequestForgery(except: ['webhook/*']);
})
CODE_SAMPLE,
                ),
            ],
        );
    }
}
