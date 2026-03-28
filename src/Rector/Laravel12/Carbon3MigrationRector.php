<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Cast\Int_ as CastInt;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class Carbon3MigrationRector extends AbstractRector
{
    /** @var array<int, string> */
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

    /** @var array<int, string> */
    private array $carbonClasses = [
        'Carbon',
        'Carbon\\Carbon',
        'Illuminate\\Support\\Carbon',
        'Carbon\\CarbonImmutable',
    ];

    /** @var array<int, string> */
    private array $removedChainMethods = ['setUtf8'];

    /** @var array<int, string> */
    private array $removedStatementMethods = ['setWeekStartsAt', 'setWeekEndsAt'];

    /**
     * Sorted by key length descending so longer tokens are replaced first,
     * preventing partial matches (e.g. %W before %w).
     *
     * @var array<string, string>
     */
    private array $formatMappings = [
        '%W' => 'WW',   // Week number (ISO)
        '%U' => 'ww',   // Week number
        '%B' => 'MMMM', // Full month name
        '%b' => 'MMM',  // Abbreviated month name
        '%A' => 'dddd', // Full weekday name
        '%a' => 'ddd',  // Abbreviated weekday name
        '%Y' => 'YYYY', // Full year
        '%y' => 'YY',   // 2-digit year
        '%m' => 'MM',   // Month (01-12)
        '%d' => 'DD',   // Day of month (01-31)
        '%j' => 'DDDD', // Day of year
        '%e' => 'D',    // Day of month (1-31)
        '%H' => 'HH',   // Hour (00-23)
        '%I' => 'hh',   // Hour (01-12)
        '%M' => 'mm',   // Minutes
        '%S' => 'ss',   // Seconds
        '%p' => 'A',    // AM/PM
        '%w' => 'e',    // Day of week (0-6)
    ];

    public function getNodeTypes(): array
    {
        return [MethodCall::class, StaticCall::class, New_::class, Expression::class];
    }

    public function refactor(Node $node): ?Node
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

        if ($node instanceof New_) {
            return $this->refactorNew($node);
        }

        return null;
    }

    private function refactorExpression(Expression $node): ?Expression
    {
        if (! $node->expr instanceof MethodCall) {
            return null;
        }

        $methodCall = $node->expr;
        $methodName = $this->getName($methodCall->name);

        if ($methodName === null) {
            return null;
        }

        if (!$this->isObjectType($methodCall->var, new ObjectType('Carbon\\Carbon'))
            && !$this->isObjectType($methodCall->var, new ObjectType('Carbon\\CarbonImmutable'))
            && !$this->isObjectType($methodCall->var, new ObjectType('Illuminate\\Support\\Carbon'))
            && !$this->isObjectType($methodCall->var, new ObjectType('Carbon\\CarbonInterface'))
        ) {
            return null;
        }

        if (! in_array($methodName, array_merge($this->removedStatementMethods, $this->removedChainMethods), true)) {
            return null;
        }

        if (in_array($methodName, $this->removedStatementMethods, true)) {
            $node->expr = $methodCall->var;

            $comments = $node->getComments();
            $commentText = "// TODO: Carbon 3 removed {$methodName}(). Configure week start/end via CarbonImmutable::setWeekStartsAt() or locale settings.";
            $comments[] = new Comment($commentText);
            $node->setAttribute('comments', $comments);

            return $node;
        }

        if (in_array($methodName, $this->removedChainMethods, true)) {
            $node->expr = $methodCall->var;

            return $node;
        }

        return null;
    }

    private function refactorStaticCall(StaticCall $node): ?Node
    {
        if (! $node->class instanceof Name) {
            return null;
        }

        if (! $this->isCarbonClass($node->class)) {
            return null;
        }

        $methodName = $this->getName($node->name);

        if ($methodName === null) {
            return null;
        }

        $changed = false;

        foreach ($node->args as $arg) {
            if ($arg instanceof Arg && $arg->name !== null && $this->isName($arg->name, 'tz')) {
                $arg->name = new Identifier('timezone');
                $changed = true;
            }
        }

        switch ($methodName) {
            case 'createFromTimestamp':
                if (count($node->args) < 2) {
                    $node->name = new Identifier('createFromTimestampUTC');
                    $changed = true;
                }

                break;

            case 'minValue':
                $node->class = new Name('\\Carbon\\CarbonImmutable');
                $node->name = new Identifier('startOfTime');
                $changed = true;

                break;

            case 'maxValue':
                $node->class = new Name('\\Carbon\\CarbonImmutable');
                $node->name = new Identifier('endOfTime');
                $changed = true;

                break;
        }

        return $changed ? $node : null;
    }

    private function refactorMethodCall(MethodCall $node): ?Node
    {
        $methodName = $this->getName($node->name);

        if ($methodName === null) {
            return null;
        }

        if (!$this->isObjectType($node->var, new ObjectType('Carbon\\Carbon'))
            && !$this->isObjectType($node->var, new ObjectType('Carbon\\CarbonImmutable'))
            && !$this->isObjectType($node->var, new ObjectType('Illuminate\\Support\\Carbon'))
            && !$this->isObjectType($node->var, new ObjectType('Carbon\\CarbonInterface'))
        ) {
            return null;
        }

        if (in_array($methodName, $this->diffMethods, true)) {
            return $this->refactorDiffMethod($node);
        }

        if (str_starts_with($methodName, 'isSame') && empty($node->args)) {
            $newMethod = 'isCurrent' . substr($methodName, 6);
            $node->name = new Identifier($newMethod);

            return $node;
        }

        if ($methodName === 'formatLocalized') {
            $node->name = new Identifier('isoFormat');

            if (isset($node->args[0]) && $node->args[0] instanceof Arg && $node->args[0]->value instanceof String_) {
                $formatString = $node->args[0]->value->value;
                $convertedFormat = $this->convertFormatString($formatString);
                $node->args[0]->value = new String_($convertedFormat);
            }

            return $node;
        }

        if (in_array($methodName, $this->removedChainMethods, true)) {
            return $node->var;
        }

        $changed = false;

        foreach ($node->args as $arg) {
            if ($arg instanceof Arg && $arg->name !== null && $this->isName($arg->name, 'tz')) {
                $arg->name = new Identifier('timezone');
                $changed = true;
            }
        }

        return $changed ? $node : null;
    }

    private function refactorDiffMethod(MethodCall $node): ?Node
    {
        if ($this->isAlreadyWrappedInAbsCast($node)) {
            return null;
        }

        $absFunc = new FuncCall(new Name('abs'), [new Arg($node)]);

        return new CastInt($absFunc);
    }

    private function isAlreadyWrappedInAbsCast(MethodCall $node): bool
    {
        $startPos = $node->getStartFilePos();

        if ($startPos < 0) {
            return false;
        }

        $originalContent = $this->file->getOriginalFileContent();
        $prefix = substr($originalContent, max(0, $startPos - 15), min(15, $startPos));

        return str_contains($prefix, 'abs(');
    }

    private function refactorNew(New_ $node): ?Node
    {
        if (! $node->class instanceof Name) {
            return null;
        }

        if (($this->isName($node->class, 'CarbonTimeZone') || $this->isName($node->class, 'Carbon\\CarbonTimeZone')) && empty($node->args)) {
            $node->args[] = new Arg(new String_('UTC'));

            return $node;
        }

        return null;
    }

    private function isCarbonClass(Name $class): bool
    {
        foreach ($this->carbonClasses as $className) {
            if ($this->isName($class, $className)) {
                return true;
            }
        }

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
                    <<<'CODE_SAMPLE'
<?php

use Carbon\Carbon;

$date = Carbon::createFromTimestamp($ts);
$diff = $date->diffInSeconds($other);
$same = $date->isSameDay();
$min = Carbon::minValue();
$fmt = $date->formatLocalized('%A');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
<?php

use Carbon\Carbon;

$date = Carbon::createFromTimestampUTC($ts);
$diff = (int) abs($date->diffInSeconds($other));
$same = $date->isCurrentDay();
$min = \Carbon\CarbonImmutable::startOfTime();
$fmt = $date->isoFormat('dddd');
CODE_SAMPLE,
                ),
            ],
        );
    }
}
