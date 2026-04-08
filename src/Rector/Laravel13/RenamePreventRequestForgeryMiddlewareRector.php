<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RenamePreventRequestForgeryMiddlewareRector extends AbstractRector
{
    private const OLD_FQCNS = [
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
    ];

    private const NEW_FQCN = 'Illuminate\Foundation\Http\Middleware\PreventRequestForgery';

    private const NEW_SHORT_NAME = 'PreventRequestForgery';

    private const OLD_METHOD_NAME = 'validateCsrfTokens';

    private const NEW_METHOD_NAME = 'preventRequestForgery';

    public function getNodeTypes(): array
    {
        return [Use_::class, ClassConstFetch::class, MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Use_) {
            return $this->refactorUse($node);
        }

        if ($node instanceof ClassConstFetch) {
            return $this->refactorClassConstFetch($node);
        }

        if ($node instanceof MethodCall) {
            return $this->refactorMethodCall($node);
        }

        return null;
    }

    private function refactorUse(Use_ $node): ?Node
    {
        foreach ($node->uses as $use) {
            $name = $use->name->toString();

            if ($this->isAppMiddleware($name)) {
                return null;
            }

            if ($name === self::NEW_FQCN) {
                return null;
            }

            if (! in_array($name, self::OLD_FQCNS, true)) {
                continue;
            }

            $use->name = new Name(self::NEW_FQCN);
            $use->alias = null;

            return $node;
        }

        return null;
    }

    private function refactorClassConstFetch(ClassConstFetch $node): ?Node
    {
        if (! $node->class instanceof Name) {
            return null;
        }

        if (! $node->name instanceof Identifier || $node->name->name !== 'class') {
            return null;
        }

        $className = $node->class->toString();

        if ($this->isAppMiddleware($className)) {
            return null;
        }

        if ($className === self::NEW_SHORT_NAME || $className === self::NEW_FQCN) {
            return null;
        }

        $oldShortNames = ['VerifyCsrfToken', 'ValidateCsrfToken'];

        if (in_array($className, self::OLD_FQCNS, true)) {
            $node->class = new Name(self::NEW_FQCN);

            return $node;
        }

        if (in_array($className, $oldShortNames, true)) {
            $node->class = new Name(self::NEW_SHORT_NAME);

            return $node;
        }

        return null;
    }

    private function refactorMethodCall(MethodCall $node): ?Node
    {
        if (! $node->name instanceof Identifier) {
            return null;
        }

        if ($node->name->name !== self::OLD_METHOD_NAME) {
            return null;
        }

        if (! $this->isObjectType($node->var, new ObjectType('Illuminate\Foundation\Configuration\Middleware'))) {
            return null;
        }

        $node->name = new Identifier(self::NEW_METHOD_NAME);

        return $node;
    }

    private function isAppMiddleware(string $name): bool
    {
        return str_contains($name, 'App\Http\Middleware');
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rename VerifyCsrfToken/ValidateCsrfToken middleware to PreventRequestForgery and rename validateCsrfTokens() to preventRequestForgery()',
            [
                new CodeSample(
                    'use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;',
                    'use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;',
                ),
            ]
        );
    }
}
