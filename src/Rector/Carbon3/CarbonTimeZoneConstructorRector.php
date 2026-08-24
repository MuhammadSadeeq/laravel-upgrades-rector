<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Carbon3;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Carbon 3's CarbonTimeZone no longer tolerates construction without an
 * explicit timezone (the parent DateTimeZone constructor gained a required
 * argument on PHP 8.3+, so the old internal default vanished).
 *
 * `new CarbonTimeZone()` → `new CarbonTimeZone('UTC')` keeps Carbon 2's
 * effective default explicit. Only zero-argument constructors are touched.
 */
final class CarbonTimeZoneConstructorRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [New_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof New_ || ! $node->class instanceof Name) {
            return null;
        }

        if (! $this->isName($node->class, 'Carbon\CarbonTimeZone')) {
            return null;
        }

        if ($node->args !== []) {
            return null;
        }

        $node->args[] = new Arg(new String_('UTC'));

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Give CarbonTimeZone constructors an explicit UTC default (required in Carbon 3)',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$timezone = new CarbonTimeZone();
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$timezone = new CarbonTimeZone('UTC');
CODE_SAMPLE,
                ),
            ],
        );
    }
}
