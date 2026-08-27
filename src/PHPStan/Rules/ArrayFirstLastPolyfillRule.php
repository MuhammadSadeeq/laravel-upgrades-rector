<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHP 8.5's array_first/array_last polyfill conflicts with application
 * helpers. Callback calls retain Laravel helper semantics and need Arr::first
 * or Arr::last instead.
 *
 * @implements Rule<Node>
 */
final class ArrayFirstLastPolyfillRule implements Rule
{
    private const FUNCTIONS = ['array_first', 'array_last'];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof Function_ && $scope->getNamespace() === null
            && in_array($node->name->toLowerString(), self::FUNCTIONS, true)) {
            return [$this->error($node->getStartLine(), $node->name->toString(), 'function declaration')];
        }

        if (! $node instanceof FuncCall || ! $this->isGlobalCall($node, $scope)
            || ! $node->name instanceof Name) {
            return [];
        }

        $name = strtolower(ltrim($scope->resolveName($node->name), '\\'));

        if (! in_array($name, self::FUNCTIONS, true)
            || ! isset($node->args[1])
            || ! $node->args[1] instanceof Node\Arg
            || ! ($node->args[1]->value instanceof Closure || $node->args[1]->value instanceof ArrowFunction)) {
            return [];
        }

        return [$this->error($node->getStartLine(), $name, 'callback call')];
    }

    private function isGlobalCall(FuncCall $call, Scope $scope): bool
    {
        if (! $call->name instanceof Name) {
            return false;
        }

        if ($call->name->isFullyQualified()) {
            return true;
        }

        // An unqualified one-part call may fall back to the global helper
        // from inside a namespace. Explicit namespace-qualified calls remain
        // application functions and are intentionally ignored.
        return count($call->name->getParts()) === 1;
    }

    private function error(int $line, string $name, string $context): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            sprintf('The %s %s conflicts with the PHP 8.5 array polyfill in Laravel 13.', $name, $context)
        )->identifier('laravelUpgrade.arrayFirstLastPolyfill')
            ->tip('Rename the global helper, or use Illuminate\\Support\\Arr::'.($name === 'array_first' ? 'first' : 'last').' for callback semantics.')
            ->line($line)
            ->build();
    }
}
