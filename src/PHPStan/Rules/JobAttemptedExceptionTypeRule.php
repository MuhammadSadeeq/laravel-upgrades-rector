<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

/**
 * Laravel 13 changed JobAttempted's exception state from bool to
 * ?Throwable. Boolean comparisons and scalar assignments need review.
 *
 * @implements Rule<Node>
 */
final class JobAttemptedExceptionTypeRule implements Rule
{
    private const PROPERTY_NAMES = ['exception', 'exceptionoccurred'];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof Identical || $node instanceof NotIdentical
            || $node instanceof Equal || $node instanceof NotEqual) {
            if (($this->isExceptionProperty($node->left, $scope) && $this->isBooleanLiteral($node->right))
                || ($this->isExceptionProperty($node->right, $scope) && $this->isBooleanLiteral($node->left))) {
                return [$this->error($node->getStartLine())];
            }
        }

        if ($node instanceof Assign && $this->isExceptionProperty($node->expr, $scope)
            && $this->isScalarTarget($scope->getType($node->var))) {
            return [$this->error($node->getStartLine())];
        }

        return [];
    }

    private function isExceptionProperty(Node $node, Scope $scope): bool
    {
        return $node instanceof PropertyFetch
            && $node->name instanceof Identifier
            && in_array($node->name->toLowerString(), self::PROPERTY_NAMES, true)
            && (new ObjectType('Illuminate\\Queue\\Events\\JobAttempted'))
                ->isSuperTypeOf($scope->getType($node->var))->yes();
    }

    private function isBooleanLiteral(Node $node): bool
    {
        return $node instanceof Node\Expr\ConstFetch
            && in_array($node->name->toLowerString(), ['true', 'false'], true);
    }

    private function isScalarTarget(Type $type): bool
    {
        if ($type->isScalar()->yes()) {
            return true;
        }

        if (! $type instanceof UnionType) {
            return false;
        }

        foreach ($type->getTypes() as $unionType) {
            if ($unionType->isNull()->yes() || $unionType->isScalar()->yes()) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function error(int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            'JobAttempted exception is now ?Throwable in Laravel 13, not a boolean.'
        )->identifier('laravelUpgrade.jobAttemptedExceptionType')
            ->tip('Use $event->successful() for a boolean result or handle the Throwable|null exception explicitly.')
            ->line($line)
            ->build();
    }
}
