<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\BlueprintReceiverResolver;
use PhpParser\Comment;
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
 * Named arguments are skipped rather than silently dropped, and receivers
 * must be confirmed Blueprint instances — a variable merely called `$table`
 * no longer counts.
 */
final class UpdateFloatingPointTypesRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade float-precision';

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

        if ($methodName === 'double' && count($node->args) > 1 && ! $this->hasNamedArgs($node)) {
            // double($column) keeps only the column name
            $node->args = [$node->args[0]];

            return $node;
        }

        if ($methodName === 'float' && count($node->args) > 1 && ! $this->hasNamedArgs($node)) {
            $node->args = [$node->args[0]];
            $this->addPrecisionNote($node);

            return $node;
        }

        $unsignedReplacements = [
            'unsignedDecimal' => 'decimal',
            'unsignedDouble' => 'double',
            'unsignedFloat' => 'float',
        ];

        if (isset($unsignedReplacements[$methodName])) {
            $baseMethod = $unsignedReplacements[$methodName];
            $node->name = new Identifier($baseMethod);

            if (($baseMethod !== 'decimal') && count($node->args) > 1 && ! $this->hasNamedArgs($node)) {
                $node->args = [$node->args[0]];
            }

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
$table->double('amount', 8, 2);
$table->float('rate', 8, 2);
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
// @laravel-upgrade float-precision: precision/scale (8, 2) dropped by Laravel 11;
// use decimal('rate', 8, 2) for fixed precision or float('rate', precision: 24)
$table->float('rate');
$table->double('amount');
CODE_SAMPLE,
                ),
            ],
        );
    }

    private function hasNamedArgs(MethodCall $node): bool
    {
        foreach ($node->getArgs() as $arg) {
            if ($arg->name !== null || $arg->unpack) {
                return true;
            }
        }

        return false;
    }

    private function addPrecisionNote(MethodCall $node): void
    {
        $note = sprintf(
            '// %s: precision/scale dropped by Laravel 11; use decimal(\'column\', 8, 2) '
            .'for fixed precision or float(\'column\', precision: 24) for a 4-byte FLOAT.',
            self::COMMENT_MARKER
        );

        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return;
            }
        }

        $comments = $node->getComments();
        $comments[] = new Comment($note);
        $node->setAttribute('comments', $comments);
    }
}
