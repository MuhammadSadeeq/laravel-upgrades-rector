<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * Laravel 13: boot() and bootWithTraits() no longer instantiate models via
 * `new static` / `new self`. Flags nested model instantiation inside boot().
 */
/**
 * @implements Rule<New_>
 */
final class ModelBootInstantiationRule implements Rule
{
    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof New_ || ! $node->class instanceof Name) {
            return [];
        }

        $scope = $node->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection) {
            return [];
        }

        // Only flag when inside a boot/bootWithTraits method.
        $methodName = $scope->getFunction()?->getName();

        if ($methodName !== 'boot' && $methodName !== 'bootWithTraits') {
            return [];
        }

        $instantiatedName = ltrim($node->class->toString(), '\\');
        $modelBase = 'Illuminate\Database\Eloquent\Model';

        if (! $classReflection->is($modelBase)
            && ! $classReflection->isSubclassOf($modelBase)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf('Model instantiation (%s) inside boot() changed behaviour in Laravel 13.', $instantiatedName)
            )->identifier('laravelUpgrade.modelBootInstantiation')
                ->tip('Move model creation out of boot() or use a static factory method.')
                ->build(),
        ];
    }
}
