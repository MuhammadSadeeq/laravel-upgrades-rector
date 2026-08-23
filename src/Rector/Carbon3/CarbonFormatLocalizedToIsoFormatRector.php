<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * formatLocalized() relies on the deprecated strftime() C library function
 * (removed from PHP 8.1+ builds) and was dropped in Carbon 3. isoFormat() is
 * the replacement, but it uses a different token language and treats unknown
 * characters as literal text — a naive token swap corrupts output
 * ("Le %A" → "Le dddd" renders as "Le dddd", not "Le Tuesday").
 *
 * This rule converts only string literals, using a strftime→isoFormat table
 * verified against Carbon 3's actual output:
 * - every run of non-token characters is wrapped in [...] so it stays literal;
 * - literal "[" and "]" inside those runs are escaped with a backslash
 *   (Carbon 3 renders \[ as [);
 * - if ANY % token cannot be mapped confidently the whole call is left alone.
 */
final class CarbonFormatLocalizedToIsoFormatRector extends AbstractRector
{
    /**
     * Exact one-to-one tokens (verified: strftime output == isoFormat output).
     *
     * Ordered longest-first so %D/%F/%T/%R/%r are matched before single-letter
     * tokens.
     *
     * @var array<string, string>
     */
    private const EXACT_TOKENS = [
        '%A' => 'dddd',
        '%a' => 'ddd',
        '%B' => 'MMMM',
        '%b' => 'MMM',
        '%h' => 'MMM',
        '%D' => 'MM/DD/YY',
        '%d' => 'DD',
        '%e' => 'D',
        '%F' => 'YYYY-MM-DD',
        '%G' => 'GGGG',
        '%g' => 'GG',
        '%H' => 'HH',
        '%I' => 'hh',
        '%j' => 'DDDD',
        '%k' => 'H',
        '%l' => 'h',
        '%M' => 'mm',
        '%m' => 'MM',
        '%n' => "\n",
        '%p' => 'A',
        '%P' => 'a',
        '%R' => 'HH:mm',
        '%r' => 'hh:mm:ss A',
        '%S' => 'ss',
        '%s' => 'X',
        '%T' => 'HH:mm:ss',
        '%t' => "\t",
        '%u' => 'E',
        '%V' => 'WW',
        '%w' => 'd',
        '%Y' => 'YYYY',
        '%y' => 'YY',
        '%z' => 'ZZ',
        '%Z' => 'z',
    ];

    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof MethodCall || ! $this->isName($node->name, 'formatLocalized')) {
            return null;
        }

        if (! $this->isObjectType($node->var, new ObjectType('Carbon\CarbonInterface'))) {
            return null;
        }

        $firstArg = $node->args[0] ?? null;

        // Non-literal or missing argument: renaming alone would change output,
        // because strftime tokens in variables would hit isoFormat unescaped.
        if (! $firstArg instanceof Arg || ! $firstArg->value instanceof String_) {
            return null;
        }

        $converted = $this->convertFormat($firstArg->value->value);

        if ($converted === null) {
            return null;
        }

        $node->name = new Identifier('isoFormat');
        $firstArg->value = new String_($converted);

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert formatLocalized() with literal strftime formats to isoFormat() with escaped literals',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$label = $date->formatLocalized('%A %d %B');
$french = $date->formatLocalized('Le %A %d');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$label = $date->isoFormat('dddd DD MMMM');
$french = $date->isoFormat('[Le] dddd DD');
CODE_SAMPLE,
                ),
            ],
        );
    }

    /**
     * Returns the converted isoFormat string, or null when the format
     * contains tokens that cannot be mapped confidently.
     */
    private function convertFormat(string $format): ?string
    {
        $result = '';
        $literalRun = '';
        $length = strlen($format);

        for ($index = 0; $index < $length; ++$index) {
            $char = $format[$index];

            if ($char !== '%') {
                $literalRun .= $char;

                continue;
            }

            $token = $this->matchToken(substr($format, $index));

            if ($token === null) {
                // Unknown % sequence: leave the whole call untouched rather
                // than emit a format that silently renders wrong text.
                return null;
            }

            [$tokenLength, $replacement] = $token;
            $index += $tokenLength - 1;

            $result .= $this->flushLiteralRun($literalRun) . $replacement;
            $literalRun = '';
        }

        return $result . $this->flushLiteralRun($literalRun);
    }

    /**
     * @return array{int, string}|null length of matched token + replacement
     */
    private function matchToken(string $rest): ?array
    {
        foreach (self::EXACT_TOKENS as $strftimeToken => $isoToken) {
            if (str_starts_with($rest, $strftimeToken)) {
                return [strlen($strftimeToken), $isoToken];
            }
        }

        return null;
    }

    private function flushLiteralRun(string $run): string
    {
        if ($run === '') {
            return '';
        }

        // Spaces, digits and plain punctuation pass through isoFormat
        // verbatim; only letters, brackets, backslashes and percent signs
        // could be (or interfere with) tokens and need bracketing.
        if (preg_match('/^[^A-Za-z\[\]\\\\%]+$/', $run) === 1) {
            return $run;
        }

        $escaped = str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $run);

        return '[' . $escaped . ']';
    }
}
