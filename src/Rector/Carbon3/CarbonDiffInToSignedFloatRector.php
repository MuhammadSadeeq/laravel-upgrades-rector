<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Cast\Int_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Name;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Carbon 3 changed every diffIn*() method to return a signed float instead of
 * an unsigned int, and diffInWeekdays()/diffInWeekendDays() flipped their
 * default from absolute to signed.
 *
 * Behaviour-preserving rewrites (Carbon 2 semantics):
 * - omitted or `absolute: true`  → `(int) abs(...)` (float diffs) / `abs(...)` (int diffs)
 * - explicit `absolute: false`   → `(int) ...`      / untouched
 *
 * Skipped deliberately:
 * - calls already wrapped in `(int)` cast or abs()/floor()/ceil()/round() —
 *   detected through the parent node, never by sniffing source text;
 * - non-literal `absolute` arguments (a finding replaces the transform in the
 *   advisory phase);
 * - receivers that do not resolve to Carbon\CarbonInterface (no guessing).
 */
final class CarbonDiffInToSignedFloatRector extends AbstractRector
{
    /**
     * Methods whose Carbon 3 return type became a signed float.
     *
     * @var list<string>
     */
    private const FLOAT_DIFF_METHODS = [
        'diffInYears',
        'diffInMonths',
        'diffInWeeks',
        'diffInDays',
        'diffInHours',
        'diffInMinutes',
        'diffInSeconds',
        'diffInMilliseconds',
        'diffInMicroseconds',
    ];

    /**
     * Weekday helpers stay int in Carbon 3 but flipped to signed-by-default.
     *
     * @var list<string>
     */
    private const INT_DIFF_METHODS = ['diffInWeekdays', 'diffInWeekendDays'];

    private const WRAPPED_MARKER = 'laravel-upgrades-rector:diff-wrapped';

    public function getNodeTypes(): array
    {
        // Wrappers are visited top-down BEFORE the inner diff call, so each
        // wrapper marks its inner call; the marked call is then skipped.
        return [Int_::class, FuncCall::class, MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Int_) {
            $this->markWrappedDiffCall($node->expr);

            return null;
        }

        if ($node instanceof FuncCall) {
            $this->markWrappedDiffCallFromFuncCall($node);

            return null;
        }

        if (! $node instanceof MethodCall) {
            return null;
        }

        $methodName = $this->getName($node->name);

        if ($methodName === null) {
            return null;
        }

        if (! in_array($methodName, [...self::FLOAT_DIFF_METHODS, ...self::INT_DIFF_METHODS], true)) {
            return null;
        }

        if ($node->getAttribute(self::WRAPPED_MARKER) === true) {
            return null;
        }

        if (! $this->isObjectType($node->var, new ObjectType('Carbon\CarbonInterface'))) {
            return null;
        }

        $absoluteBehavior = $this->resolveAbsoluteBehavior($node);

        // null = unsafe to decide (non-literal argument, argument spreading)
        if ($absoluteBehavior === null) {
            return null;
        }

        $isFloatDiff = in_array($methodName, self::FLOAT_DIFF_METHODS, true);
        $transformedCall = clone $node;

        // When preserving the old absolute behaviour the argument becomes
        // redundant and is dropped; an explicit `false` is kept as-is since
        // it already selects the new signed behaviour.
        $absoluteArgIndex = $this->getAbsoluteArgIndex($node);

        if ($absoluteBehavior && $absoluteArgIndex !== null) {
            unset($transformedCall->args[$absoluteArgIndex]);
            $transformedCall->args = array_values($transformedCall->args);
        }

        if (! $absoluteBehavior) {
            return $isFloatDiff ? new Int_($transformedCall) : $transformedCall;
        }

        $absFunc = new FuncCall(new Name('abs'), [new Arg($transformedCall)]);

        return $isFloatDiff ? new Int_($absFunc) : $absFunc;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Preserve Carbon 2 integer/absolute diff behaviour around Carbon 3 signed float diffs',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$days = $start->diffInDays($end);
$hours = $end->diffInHours($start, false);
$weekdays = $start->diffInWeekdays($end);
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$days = (int) abs($start->diffInDays($end));
$hours = (int) $end->diffInHours($start, false);
$weekdays = abs($start->diffInWeekdays($end));
CODE_SAMPLE,
                ),
            ],
        );
    }

    /**
     * Marks a diff call that is already wrapped in an (int) cast, so a
     * second traversal never double-wraps it.
     */
    private function markWrappedDiffCall(?Node $expr): void
    {
        if ($expr instanceof MethodCall) {
            $methodName = $this->getName($expr->name);

            if (
                $methodName !== null
                && in_array($methodName, [...self::FLOAT_DIFF_METHODS, ...self::INT_DIFF_METHODS], true)
            ) {
                $expr->setAttribute(self::WRAPPED_MARKER, true);
            }
        }
    }

    private function markWrappedDiffCallFromFuncCall(FuncCall $funcCall): void
    {
        $functionName = $funcCall->name instanceof Name ? strtolower($funcCall->name->toString()) : null;

        if ($functionName === null || ! in_array($functionName, ['abs', 'floor', 'ceil', 'round'], true)) {
            return;
        }

        $firstArg = $funcCall->args[0] ?? null;

        if ($firstArg instanceof Arg) {
            $this->markWrappedDiffCall($firstArg->value);
        }
    }

    private const UNSAFE = 'unsafe';

    private function resolveAbsoluteBehavior(MethodCall $node): ?bool
    {
        $behavior = $this->scanAbsoluteBehavior($node);

        return $behavior === self::UNSAFE ? null : $behavior;
    }

    /**
     * @return bool|'unsafe'
     */
    private function scanAbsoluteBehavior(MethodCall $node): bool|string
    {
        foreach ($node->args as $arg) {
            if ($arg instanceof Arg && $arg->unpack) {
                return self::UNSAFE;
            }
        }

        // Named `absolute:` argument.
        foreach ($node->args as $arg) {
            if ($arg instanceof Arg && $arg->name !== null && $this->isName($arg->name, 'absolute')) {
                return $this->literalBoolean($arg);
            }
        }

        // Positional second argument.
        $second = $node->args[1] ?? null;

        if ($second instanceof Arg) {
            return $this->literalBoolean($second);
        }

        // No absolute argument at all → Carbon 2 default was absolute.
        return true;
    }

    /**
     * @return bool|'unsafe'
     */
    private function literalBoolean(Arg $arg): bool|string
    {
        if (! $arg->value instanceof ConstFetch) {
            return self::UNSAFE;
        }

        return match (strtolower($arg->value->name->toString())) {
            'true' => true,
            'false' => false,
            default => self::UNSAFE,
        };
    }

    private function getAbsoluteArgIndex(MethodCall $node): ?int
    {
        foreach ($node->args as $index => $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            if ($arg->unpack) {
                // Argument spreading makes every positional assumption unsafe.
                return null;
            }

            if ($arg->name !== null && $this->isName($arg->name, 'absolute')) {
                return $index;
            }
        }

        foreach ($node->args as $index => $arg) {
            if ($arg instanceof Arg && $arg->name === null && ! $arg->unpack && $index === 1) {
                return $index;
            }
        }

        return null;
    }
}
