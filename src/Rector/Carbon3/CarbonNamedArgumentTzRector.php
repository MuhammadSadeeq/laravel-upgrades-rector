<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Carbon 3 renamed the `$tz` parameter to `$timezone` across its creator and
 * modifier methods (verified against CarbonInterface in Carbon 3). Code
 * calling e.g. `Carbon::parse($value, tz: 'UTC')` fatals under Carbon 3.
 *
 * Only named `tz:` arguments on Carbon receivers are renamed; positional
 * arguments are untouched, and non-Carbon receivers are never modified.
 */
final class CarbonNamedArgumentTzRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [StaticCall::class, MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof StaticCall) {
            return $this->refactorStaticCall($node);
        }

        if ($node instanceof MethodCall) {
            if (! $this->isObjectType($node->var, new ObjectType('Carbon\CarbonInterface'))) {
                return null;
            }

            return $this->renameTzArguments($node) ? $node : null;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rename the Carbon named argument tz: to timezone: (Carbon 3 signature change)',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$date = Carbon::parse('2024-01-01 12:00', tz: 'Europe/Paris');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$date = Carbon::parse('2024-01-01 12:00', timezone: 'Europe/Paris');
CODE_SAMPLE,
                ),
            ],
        );
    }

    private function refactorStaticCall(StaticCall $staticCall): ?Node
    {
        if (! $staticCall->class instanceof Name || ! $this->isCarbonClassName($staticCall->class)) {
            return null;
        }

        return $this->renameTzArguments($staticCall) ? $staticCall : null;
    }

    private function renameTzArguments(StaticCall|MethodCall $call): bool
    {
        $changed = false;

        foreach ($call->args as $arg) {
            if ($arg instanceof Arg && $arg->name !== null && $this->isName($arg->name, 'tz')) {
                $arg->name = new Identifier('timezone');
                $changed = true;
            }
        }

        return $changed;
    }

    private function isCarbonClassName(Name $class): bool
    {
        foreach (['Carbon', 'Carbon\Carbon', 'Illuminate\Support\Carbon'] as $candidate) {
            if ($this->isName($class, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
