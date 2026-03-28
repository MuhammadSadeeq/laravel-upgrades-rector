<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\Mul;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateRateLimitingRector extends AbstractRector
{
    /** @var array<int, string> */
    private array $rateLimitClasses = [
        "GlobalLimit",
        "Limit",
        "ThrottlesExceptions",
        "ThrottlesExceptionsWithRedis",
        "Illuminate\Cache\RateLimiting\GlobalLimit",
        "Illuminate\Cache\RateLimiting\Limit",
        "Illuminate\Queue\Middleware\ThrottlesExceptions",
        "Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis",
    ];

    public function getNodeTypes(): array
    {
        return [New_::class, StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof New_) {
            return $this->refactorNew($node);
        }

        if ($node instanceof StaticCall) {
            return $this->refactorStaticCall($node);
        }

        return null;
    }

    private function refactorNew(New_ $node): ?Node
    {
        if (!$node->class instanceof Name) {
            return null;
        }

        $className = $this->getName($node->class);

        if (!$this->isRateLimitClass($className)) {
            return null;
        }

        return $this->convertTimeArgument($node, $className, $node->args);
    }

    private function refactorStaticCall(StaticCall $node): ?Node
    {
        if (!$node->class instanceof Name) {
            return null;
        }

        $className = $this->getName($node->class);

        if (!$this->isRateLimitClass($className)) {
            return null;
        }

        // Only handle specific static methods that use time parameters
        $methodName = $this->getName($node->name);
        if (!in_array($methodName, ['perMinute', 'perMinutes'], true)) {
            return null;
        }

        return $this->convertTimeArgument($node, $className, $node->args);
    }

    private function isRateLimitClass(string $className): bool
    {
        return in_array($className, $this->rateLimitClasses, true) ||
               str_contains($className, 'GlobalLimit') ||
               str_contains($className, 'Limit') ||
               str_contains($className, 'ThrottlesExceptions');
    }

    /**
     * @param array<\PhpParser\Node\Arg|\PhpParser\Node\VariadicPlaceholder> $args
     */
    private function convertTimeArgument(Node $node, string $className, array $args): ?Node
    {
        $updated = false;

        // Check constructor arguments and convert minutes to seconds
        foreach ($args as $index => $arg) {
            if (!$arg instanceof Arg) {
                continue;
            }

            // For most rate limiting classes, the time parameter is usually the 2nd or 3rd argument
            // GlobalLimit: new GlobalLimit($attempts, $decayTime)
            // Limit: new Limit($key, $attempts, $decayTime) or Limit::perMinute($attempts, $decayTime)
            // ThrottlesExceptions: new ThrottlesExceptions($attempts, $decayTime)

            $isTimeArgument = false;

            // For static calls like Limit::perMinute(), the time is the 2nd argument (index 1)
            if ($node instanceof StaticCall && $index === 1) {
                $isTimeArgument = true;
            }
            // For GlobalLimit constructor
            elseif (str_contains($className, 'GlobalLimit') && $index === 1) {
                $isTimeArgument = true;
            }
            // For Limit constructor
            elseif (str_contains($className, 'Limit') && !str_contains($className, 'GlobalLimit') && $index === 2) {
                $isTimeArgument = true;
            }
            // For ThrottlesExceptions
            elseif (str_contains($className, 'ThrottlesExceptions') && $index === 1) {
                $isTimeArgument = true;
            }

            if ($isTimeArgument && $arg->value instanceof LNumber) {
                $minutes = $arg->value->value;

                // Convert minutes to seconds if it looks like a minute value
                if ($minutes <= 1440) {
                    // Up to 24 hours in minutes
                    $arg->value = new Mul(
                        new LNumber($minutes),
                        new LNumber(60),
                    );
                    $updated = true;
                }
            }
        }

        return $updated ? $node : null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Update rate limiting classes to use seconds instead of minutes for Laravel 11",
            [
                new CodeSample(
                    'new GlobalLimit($attempts, 2)',
                    '/** Laravel 11: GlobalLimit constructor now expects seconds instead of minutes */
new GlobalLimit($attempts, 2 * 60)',
                ),
                new CodeSample(
                    'new Limit($key, $attempts, 5)',
                    '/** Laravel 11: Limit constructor now expects seconds instead of minutes */
new Limit($key, $attempts, 5 * 60)',
                ),
            ],
        );
    }
}
