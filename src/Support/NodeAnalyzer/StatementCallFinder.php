<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;

/**
 * Yields every method/static/new call nested anywhere inside a statement,
 * regardless of shape — bare expression, assignment, return, argument,
 * array item or chained call.
 *
 * Replaces per-rule "expr instanceof X" ladders and StaticCallExtractor.
 */
final class StatementCallFinder
{
    /**
     * @return list<MethodCall|StaticCall|New_>
     */
    public function find(Node $statement): array
    {
        $found = [];

        $this->collect($statement, $found);

        return $found;
    }

    /**
     * @param  list<MethodCall|StaticCall|New_>  $out
     */
    private function collect(Node $node, array &$out): void
    {
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $value = $node->{$subNodeName};

            if (! is_array($value) && ! $value instanceof Node) {
                continue;
            }

            $children = is_array($value) ? $value : [$value];

            foreach ($children as $child) {
                if (! $child instanceof Node) {
                    continue;
                }

                if ($child instanceof MethodCall || $child instanceof StaticCall || $child instanceof New_) {
                    $out[] = $child;
                }

                $this->collect($child, $out);
            }
        }
    }
}
