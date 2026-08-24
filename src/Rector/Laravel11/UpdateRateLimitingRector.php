<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\Mul;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\VarLikeIdentifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Laravel 11 rate limiters express their window in seconds instead of minutes.
 *
 * - `new Limit(...)` / GlobalLimit / ThrottlesExceptions constructor time
 *   arguments are multiplied by 60 — only when the class name resolves to the
 *   exact framework FQCN (a userland `Limit` class is never touched);
 * - inside classes extending ThrottlesExceptions(+Redis), the
 *   `$decayMinutes` property (declaration, default value and `$this->`
 *   reads) becomes `$decaySeconds`, with numeric defaults converted.
 */
final class UpdateRateLimitingRector extends AbstractRector
{
    private const THROTTLE_CLASSES = [
        'Illuminate\Queue\Middleware\ThrottlesExceptions',
        'Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis',
    ];

    /**
     * FQCN => index of the time argument in the constructor.
     *
     * @var array<string, int>
     */
    private const CONSTRUCTOR_TIME_ARG_INDEX = [
        'Illuminate\Cache\RateLimiting\GlobalLimit' => 1,
        'Illuminate\Cache\RateLimiting\Limit' => 2,
        'Illuminate\Queue\Middleware\ThrottlesExceptions' => 1,
        'Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis' => 1,
    ];

    public function getNodeTypes(): array
    {
        return [New_::class, Property::class, PropertyFetch::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof New_) {
            return $this->refactorNew($node);
        }

        if ($node instanceof Property) {
            return $this->refactorPropertyDeclaration($node);
        }

        if ($node instanceof PropertyFetch) {
            return $this->refactorPropertyFetch($node);
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert rate limiter windows from minutes to seconds for Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Cache\RateLimiting\Limit;

$limit = new Limit(perMinute: 5);

final class RetryJob extends ThrottlesExceptions
{
    protected int $decayMinutes = 5;

    public function middleware(): array
    {
        return [$this->buildDecay($this->decayMinutes)];
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Cache\RateLimiting\Limit;

$limit = new Limit(perSecond: 5 * 60);

final class RetryJob extends ThrottlesExceptions
{
    protected int $decaySeconds = 5 * 60;

    public function middleware(): array
    {
        return [$this->buildDecay($this->decaySeconds)];
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }

    private function refactorNew(New_ $new): ?Node
    {
        if (! $new->class instanceof Name) {
            return null;
        }

        foreach (self::CONSTRUCTOR_TIME_ARG_INDEX as $fqcn => $argIndex) {
            if (! $this->matchesClass($new->class, $fqcn)) {
                continue;
            }

            if (! isset($new->args[$argIndex])) {
                return null;
            }

            $arg = $new->args[$argIndex];

            if (! $arg instanceof Arg || ! $arg->value instanceof LNumber) {
                return null;
            }

            if ($this->isAlreadyMultipliedBy60($arg)) {
                return null;
            }

            $arg->value = new Mul($arg->value, new LNumber(60));

            return $new;
        }

        return null;
    }

    private function refactorPropertyDeclaration(Property $property): ?Node
    {
        $onlyPropertyItem = $property->props[0] ?? null;

        if (! $onlyPropertyItem instanceof PropertyItem || ! $this->isName($onlyPropertyItem->name, 'decayMinutes')) {
            return null;
        }

        $scope = $property->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return null;
        }

        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection || ! $this->extendsThrottlesMiddleware($classReflection)) {
            return null;
        }

        $onlyPropertyItem->name = new VarLikeIdentifier('decaySeconds');

        $default = $onlyPropertyItem->default;

        if ($default instanceof LNumber) {
            $onlyPropertyItem->default = new Mul($default, new LNumber(60));
        }

        return $property;
    }

    private function refactorPropertyFetch(PropertyFetch $propertyFetch): ?Node
    {
        if (! $this->isName($propertyFetch->name, 'decayMinutes')) {
            return null;
        }

        // Only match $this->decayMinutes inside a throttles middleware class
        if (! $propertyFetch->var instanceof Variable || ! $this->isName($propertyFetch->var, 'this')) {
            return null;
        }

        $scope = $propertyFetch->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return null;
        }

        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection || ! $this->extendsThrottlesMiddleware($classReflection)) {
            return null;
        }

        $propertyFetch->name = new Identifier('decaySeconds');

        return $propertyFetch;
    }

    private function extendsThrottlesMiddleware(ClassReflection $classReflection): bool
    {
        foreach (self::THROTTLE_CLASSES as $throttleClass) {
            if ($classReflection->is($throttleClass)) {
                return true;
            }
        }

        return false;
    }

    private function matchesClass(Name $name, string $fqcn): bool
    {
        if ($this->isName($name, $fqcn)) {
            return true;
        }

        // Scope-resolved comparison only — no bare short-name guessing.
        $scope = $name->getAttribute(AttributeKey::SCOPE);

        if ($scope instanceof Scope) {
            try {
                return strcasecmp($scope->resolveName($name), $fqcn) === 0;
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    private function isAlreadyMultipliedBy60(Arg $arg): bool
    {
        if (! $arg->value instanceof Mul) {
            return false;
        }

        $mul = $arg->value;

        if ($mul->right instanceof LNumber && $mul->right->value === 60) {
            return true;
        }

        if ($mul->left instanceof LNumber && $mul->left->value === 60) {
            return true;
        }

        return false;
    }
}
