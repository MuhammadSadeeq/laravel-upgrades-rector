<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeVisitor;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Methods removed in Carbon 3, plus the zero-argument isSameX() → isCurrentX()
 * rename (isSameDay() still exists but now requires an argument).
 *
 * - Carbon::minValue() / maxValue()  → startOfTime() / endOfTime() on the SAME
 *   class: both exist as static methods on every Carbon 3 class, and keeping
 *   the receiver avoids silently changing mutability semantics;
 * - $date->setUtf8(...)              → call stripped (removed in Carbon 3);
 * - $date->setWeekStartsAt(...) / ->setWeekEndsAt(...) → calling them would
 *   fatal in Carbon 3; when they form a whole statement the statement is
 *   removed, otherwise left untouched because dropping part of an expression
 *   would change behaviour;
 * - $date->isSameDay() etc.          → isCurrentDay() for the verified unit
 *   allow-list only (isSameDay($other) with arguments is untouched).
 */
final class CarbonRemovedMethodsRector extends AbstractRector
{
    /**
     * Zero-arg comparison renames, each verified against the Carbon 3 API.
     *
     * @var list<string>
     */
    private const CURRENT_UNITS = [
        'Second',
        'Minute',
        'Hour',
        'Day',
        'Week',
        'Month',
        'Quarter',
        'Year',
        'Decade',
        'Century',
        'Millennium',
        'Millisecond',
        'Microsecond',
    ];

    /**
     * @var list<string>
     */
    private const REMOVED_STATEMENT_METHODS = ['setWeekStartsAt', 'setWeekEndsAt', 'setUtf8'];

    public function getNodeTypes(): array
    {
        return [Expression::class, StaticCall::class, MethodCall::class];
    }

    /**
     * @return Node|int|null int is NodeVisitor::REMOVE_NODE
     */
    public function refactor(Node $node): Node|int|null
    {
        if ($node instanceof Expression) {
            return $this->refactorExpression($node);
        }

        if ($node instanceof StaticCall) {
            return $this->refactorStaticCall($node);
        }

        if ($node instanceof MethodCall) {
            return $this->refactorMethodCall($node);
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace methods removed in Carbon 3 and rename zero-arg isSame* comparisons to isCurrent*',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$min = Carbon::minValue();
$date->setUtf8(false);
$date->setWeekStartsAt(Carbon::MONDAY);
$same = $date->isSameDay();
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$min = Carbon::startOfTime();
$same = $date->isCurrentDay();
CODE_SAMPLE,
                ),
            ],
        );
    }

    private function refactorExpression(Expression $expression): ?int
    {
        if (! $expression->expr instanceof MethodCall) {
            return null;
        }

        $methodCall = $expression->expr;
        $methodName = $this->getName($methodCall->name);

        if ($methodName === null || ! in_array($methodName, self::REMOVED_STATEMENT_METHODS, true)) {
            return null;
        }

        if (! $this->isObjectType($methodCall->var, new ObjectType('Carbon\CarbonInterface'))) {
            return null;
        }

        // The call itself would fatal under Carbon 3; drop the statement.
        return NodeVisitor::REMOVE_NODE;
    }

    private function refactorStaticCall(StaticCall $staticCall): ?Node
    {
        if (! $staticCall->class instanceof Name) {
            return null;
        }

        $methodName = $this->getName($staticCall->name);

        if ($methodName === 'minValue' && $this->isCarbonClassName($staticCall->class)) {
            $staticCall->name = new Identifier('startOfTime');

            return $staticCall;
        }

        if ($methodName === 'maxValue' && $this->isCarbonClassName($staticCall->class)) {
            $staticCall->name = new Identifier('endOfTime');

            return $staticCall;
        }

        return null;
    }

    private function refactorMethodCall(MethodCall $methodCall): ?Node
    {
        $methodName = $this->getName($methodCall->name);

        if ($methodName === null) {
            return null;
        }

        if (
            str_starts_with($methodName, 'isSame')
            && $methodCall->args === []
            && $this->isObjectType($methodCall->var, new ObjectType('Carbon\CarbonInterface'))
        ) {
            $unit = substr($methodName, 6);

            if (in_array($unit, self::CURRENT_UNITS, true)) {
                $methodCall->name = new Identifier('isCurrent'.$unit);

                return $methodCall;
            }

            return null;
        }

        if ($methodName === 'setUtf8'
            && $this->isObjectType($methodCall->var, new ObjectType('Carbon\CarbonInterface'))
        ) {
            // Strip from a chain; the surrounding expression stays intact.
            return $methodCall->var;
        }

        return null;
    }

    private function isCarbonClassName(Name $class): bool
    {
        foreach (['Carbon', 'Carbon\Carbon', 'Illuminate\Support\Carbon', 'Carbon\CarbonImmutable'] as $candidate) {
            if ($this->isName($class, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
