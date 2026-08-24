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
 * array item or chained call (plan P2-01, rule standard #4).
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
     * First matching call of the given kind inside the statement.
     */
    public function findFirst(Node $statement, string $methodOrClassName, bool $isStaticName = false): MethodCall|StaticCall|New_|null
    {
        foreach ($this->find($statement) as $call) {
            if ($call instanceof New_) {
                if (! $isStaticName && $call->class instanceof Node\Name && $this->namesMatch($call->class, $methodOrClassName)) {
                    return $call;
                }

                continue;
            }

            if ($call instanceof StaticCall && $isStaticName) {
                if ($call->class instanceof Node\Name && $this->namesMatch($call->class, $methodOrClassName)) {
                    return $call;
                }

                continue;
            }
        }

        return null;
    }

    /**
     * @param list<MethodCall|StaticCall|New_> $out
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

    private function namesMatch(Node\Name $name, string $expected): bool
    {
        $resolved = $name->getAttribute(\Rector\NodeTypeResolver\Node\AttributeKey::RESOLVED_NAME);

        if ($resolved instanceof Node\Name) {
            return strcasecmp(ltrim($resolved->toString(), '\\'), ltrim($expected, '\\')) === 0;
        }

        // Unresolved short names only match when identical; guessing across
        // namespaces is a rule-standard violation (#1).
        return strcasecmp($name->toString(), ltrim($expected, '\\')) === 0
            || strcasecmp($name->toString(), substr($expected, (int) strrpos($expected, '\\') + 1)) === 0
            && $name->isFullyQualified();
    }
}
