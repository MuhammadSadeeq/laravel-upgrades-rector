<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\Cast\Int_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class Carbon3MigrationRector extends AbstractRector
{
    private array $diffMethods = [
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

    private array $formatMappings = [
        '%A' => 'dddd', // Full weekday name
        '%a' => 'ddd',  // Abbreviated weekday name
        '%B' => 'MMMM', // Full month name
        '%b' => 'MMM',  // Abbreviated month name
        '%d' => 'DD',   // Day of month (01-31)
        '%e' => 'D',    // Day of month (1-31)
        '%Y' => 'YYYY', // Full year
        '%y' => 'YY',   // 2-digit year
        '%m' => 'MM',   // Month (01-12)
        '%H' => 'HH',   // Hour (00-23)
        '%I' => 'hh',   // Hour (01-12)
        '%M' => 'mm',   // Minutes
        '%S' => 'ss',   // Seconds
        '%p' => 'A',    // AM/PM
        '%w' => 'e',    // Day of week (0-6)
        '%j' => 'DDDD', // Day of year
        '%U' => 'ww',   // Week number
        '%W' => 'WW',   // Week number
    ];

    public function getNodeTypes(): array
    {
        return [MethodCall::class, StaticCall::class, New_::class];
    }

    public function refactor(Node $node): ?Node
    {
        // 1. Rename named arg 'tz' to 'timezone' for consistency with Carbon 3
        if ($node instanceof MethodCall || $node instanceof StaticCall) {
            foreach ($node->args as $arg) {
                if ($arg->name && $this->isName($arg->name, 'tz')) {
                    $arg->name = new Identifier('timezone');
                }
            }
        }

        // 2. StaticCall transformations (Carbon static methods)
        if ($node instanceof StaticCall) {
            return $this->refactorStaticCall($node);
        }

        // 3. MethodCall transformations (instance methods on Carbon objects)
        if ($node instanceof MethodCall) {
            return $this->refactorMethodCall($node);
        }

        // 4. New expressions (constructors)
        if ($node instanceof New_) {
            return $this->refactorNew($node);
        }

        return $node;
    }

    private function refactorStaticCall(StaticCall $node): ?Node
    {
        if (!$this->isCarbonClass($node->class)) {
            return $node;
        }

        $methodName = $this->getName($node->name);

        switch ($methodName) {
            case 'createFromTimestamp':
                // createFromTimestamp -> createFromTimestampUTC if no timezone provided
                if (count($node->args) < 2) {
                    $node->name = new Identifier('createFromTimestampUTC');
                }
                break;

            case 'minValue':
                // Replace Carbon::minValue() -> CarbonImmutable::startOfTime()
                $node->class = new Name('\\Carbon\\CarbonImmutable');
                $node->name = new Identifier('startOfTime');
                break;

            case 'maxValue':
                // Replace Carbon::maxValue() -> CarbonImmutable::endOfTime()
                $node->class = new Name('\\Carbon\\CarbonImmutable');
                $node->name = new Identifier('endOfTime');
                break;
        }

        return $node;
    }

    private function refactorMethodCall(MethodCall $node): ?Node
    {
        $methodName = $this->getName($node->name);

        // Handle diffIn* methods - wrap with (int) abs() for Carbon 2 behavior
        if (in_array($methodName, $this->diffMethods, true)) {
            // Apply the transformation to all diffIn* method calls, assuming they're Carbon instances
            $absFunc = new FuncCall(new Name('abs'), [new Arg($node)]);
            return new Cast\Int_($absFunc);
        }

        // isSameX() without args -> isCurrentX() to satisfy Carbon 3 signature
        if (str_starts_with($methodName, 'isSame') && empty($node->args)) {
            $newMethod = 'isCurrent' . substr($methodName, 6);
            $node->name = new Identifier($newMethod);
            return $node;
        }

        // Replace formatLocalized with isoFormat and convert format strings
        if ($methodName === 'formatLocalized') {
            $node->name = new Identifier('isoFormat');

            // Convert format string if it's a direct string literal
            if (isset($node->args[0]) && $node->args[0]->value instanceof String_) {
                $formatString = $node->args[0]->value->value;
                $convertedFormat = $this->convertFormatString($formatString);
                $node->args[0]->value = new String_($convertedFormat);
            }

            return $node;
        }

        // Drop methods that are no longer needed/available
        if (in_array($methodName, ['setUtf8', 'setWeekStartsAt', 'setWeekEndsAt'], true)) {
            return $node->var; // Return the object the method was called on
        }

        return $node;
    }

    private function refactorNew(New_ $node): ?Node
    {
        if (!$node->class instanceof Name) {
            return $node;
        }

        // new CarbonTimeZone() with no args -> add default 'UTC'
        if (($this->isName($node->class, 'CarbonTimeZone') || $this->isName($node->class, 'Carbon\CarbonTimeZone')) && empty($node->args)) {
            $node->args[] = new Arg(new String_('UTC'));
        }

        return $node;
    }

    private function isCarbonClass($class): bool
    {
        if (!$class instanceof Name) {
            return false;
        }

        return $this->isName($class, 'Carbon') ||
               $this->isName($class, 'Carbon\\Carbon') ||
               $this->isName($class, 'Illuminate\\Support\\Carbon');
    }

    private function isCarbonMethodCall(MethodCall $methodCall): bool
    {
        // Check if it's a static call to Carbon
        if ($methodCall->var instanceof StaticCall) {
            return $this->isCarbonClass($methodCall->var->class);
        }

        // Check if it's a method call on another method call (chaining)
        if ($methodCall->var instanceof MethodCall) {
            return $this->isCarbonMethodCall($methodCall->var);
        }

        // For variables and other expressions, be conservative
        return false;
    }

    private function convertFormatString(string $format): string
    {
        $converted = $format;

        foreach ($this->formatMappings as $old => $new) {
            $converted = str_replace($old, $new, $converted);
        }

        return $converted;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Migrate Carbon 2.x code to Carbon 3.x by adjusting methods, arguments and handling breaking changes',
            [
                new CodeSample(
                    'Carbon::createFromTimestamp($timestamp)',
                    'Carbon::createFromTimestampUTC($timestamp)',
                ),
                new CodeSample(
                    '$date->diffInSeconds($other)',
                    '(int) abs($date->diffInSeconds($other))',
                ),
                new CodeSample(
                    '$date->isSameDay()',
                    '$date->isCurrentDay()',
                ),
                new CodeSample(
                    'Carbon::minValue()',
                    'CarbonImmutable::startOfTime()',
                ),
                new CodeSample(
                    '$date->formatLocalized("%A")',
                    '$date->isoFormat("dddd")',
                ),
            ],
        );
    }
}