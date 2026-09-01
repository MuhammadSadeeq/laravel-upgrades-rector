<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\BlueprintReceiverResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Laravel 11 changed floating point column signatures:
 * - double($column) lost its total/places arguments;
 * - float($column, $precision = 53) lost total/places too;
 * - unsignedDecimal()/unsignedDouble()/unsignedFloat() were removed.
 *
 * Ambiguous multi-argument calls are left unchanged so the post-Rector
 * advisory can preserve their precision/scale evidence. Receivers must be
 * confirmed Blueprint instances — a variable merely called `$table` no longer
 * counts.
 */
final class UpdateFloatingPointTypesRector extends AbstractRector
{
    private BlueprintReceiverResolver $blueprintReceiverResolver;

    public function __construct()
    {
        $this->blueprintReceiverResolver = new BlueprintReceiverResolver;
    }

    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof MethodCall) {
            return null;
        }

        if (! $this->blueprintReceiverResolver->isBlueprint($node->var)) {
            return null;
        }

        $methodName = $this->getName($node->name);

        if ($methodName === null) {
            return null;
        }

        if (($methodName === 'double' || $methodName === 'float') && count($node->args) > 1) {
            // Keep precision/scale evidence for FloatPrecisionDroppedRule,
            // which runs after the code transform and produces the advisory.
            return null;
        }

        $unsignedReplacements = [
            'unsignedDecimal' => 'decimal',
            'unsignedDouble' => 'double',
            'unsignedFloat' => 'float',
        ];

        if (isset($unsignedReplacements[$methodName])) {
            $baseMethod = $unsignedReplacements[$methodName];

            $node->name = new Identifier($baseMethod);

            return new MethodCall($node, new Identifier('unsigned'));
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update floating-point column definitions for Laravel 11 and flag dropped precision',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$table->unsignedDouble('amount');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$table->double('amount')->unsigned();
CODE_SAMPLE,
                ),
            ],
        );
    }
}
