<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Laravel 12: the 'image' validation rule no longer accepts SVG files by
 * default. Flags image rule values and File::image() calls that leave the
 * SVG choice implicit.
 *
 * @implements Rule<Node>
 */
final class ImageRuleExcludesSvgRule implements Rule
{
    private const ERROR_MESSAGE = "The 'image' validation rule no longer accepts SVG files by default.";

    private const ERROR_TIP = "Add 'image:allow_svg' to keep SVG support or use File::image(allowSvg: true).";

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $methodName = $node->name->toLowerString();

            if ($methodName === 'validate'
                && (new ObjectType('Illuminate\\Http\\Request'))
                    ->isSuperTypeOf($scope->getType($node->var))->yes()) {
                return $this->errorsForRulesArgument($node->args[0] ?? null);
            }

            if ($methodName === 'make'
                && (new ObjectType('Illuminate\Validation\Factory'))
                    ->isSuperTypeOf($scope->getType($node->var))->yes()) {
                return $this->errorsForRulesArgument($node->args[1] ?? null);
            }
        }

        if ($node instanceof StaticCall && $this->isImplicitFileImage($node, $scope)) {
            return $this->allowsSvg($node) ? [] : [$this->error()];
        }

        if ($node instanceof StaticCall && $this->isValidatorMake($node, $scope)) {
            return $this->errorsForRulesArgument($node->args[1] ?? null);
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function errorsForRulesArgument(Arg|Node\VariadicPlaceholder|null $argument): array
    {
        if (! $argument instanceof Arg) {
            return [];
        }

        /** @var list<IdentifierRuleError> $errors */
        $errors = [];
        $this->scanRulesValue($argument->value, $errors);

        return $errors;
    }

    /**
     * Scan only array values, never keys or unrelated arguments. This keeps
     * a field named "image" and request payload strings from false positives.
     *
     * @param  list<IdentifierRuleError>  $errors
     */
    private function scanRulesValue(Node $node, array &$errors): void
    {
        if ($node instanceof String_) {
            if ($this->hasBareImageRule($node->value)) {
                $errors[] = $this->error();
            }

            return;
        }

        if (! $node instanceof Array_) {
            return;
        }

        foreach ($node->items as $item) {
            if ($item !== null) {
                $this->scanRulesValue($item->value, $errors);
            }
        }
    }

    private function isImplicitFileImage(StaticCall $call, Scope $scope): bool
    {
        return $call->name instanceof Identifier
            && $call->name->toLowerString() === 'image'
            && $call->class instanceof Name
            && in_array(ltrim($scope->resolveName($call->class), '\\'), [
                'Illuminate\Validation\Rules\File',
                'File',
            ], true);
    }

    private function isValidatorMake(StaticCall $call, Scope $scope): bool
    {
        return $call->name instanceof Identifier
            && $call->name->toLowerString() === 'make'
            && $call->class instanceof Name
            && in_array(ltrim($scope->resolveName($call->class), '\\'), [
                'Illuminate\Support\Facades\Validator',
                'Validator',
            ], true);
    }

    private function allowsSvg(StaticCall $call): bool
    {
        $argument = $call->args[0] ?? null;

        if (! $argument instanceof Arg || ! $argument->value instanceof ConstFetch) {
            return false;
        }

        return $argument->value->name->toLowerString() === 'true';
    }

    private function hasBareImageRule(string $ruleString): bool
    {
        foreach (explode('|', $ruleString) as $part) {
            if (trim($part) === 'image') {
                return true;
            }
        }

        return false;
    }

    private function error(): IdentifierRuleError
    {
        return RuleErrorBuilder::message(self::ERROR_MESSAGE)
            ->identifier('laravelUpgrade.imageRuleExcludesSvg')
            ->tip(self::ERROR_TIP)
            ->build();
    }
}
