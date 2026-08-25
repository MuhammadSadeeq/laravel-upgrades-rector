<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * MySQL: DELETE ... JOIN with ORDER BY / LIMIT is no longer supported by
 * the query builder. Flags chained ->delete() calls preceded by
 * ->join() + ->orderBy() or ->limit().
 *
 * @implements Rule<MethodCall>
 */
final class DeleteJoinOrderLimitRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'delete') {
            return [];
        }

        $hasJoin = false;
        $hasOrderOrLimit = false;

        $cursor = $node->var;

        while ($cursor instanceof MethodCall) {
            if (! $cursor->name instanceof Identifier) {
                break;
            }

            $method = $cursor->name->toLowerString();

            if (str_starts_with($method, 'join')) {
                $hasJoin = true;
            }

            if ($method === 'orderby' || $method === 'limit') {
                $hasOrderOrLimit = true;
            }

            $cursor = $cursor->var;
        }

        if ($hasJoin && $hasOrderOrLimit) {
            return [
                RuleErrorBuilder::message(
                    'DELETE with JOIN and ORDER BY/LIMIT is not supported by MySQL/MariaDB.'
                )->identifier('laravelUpgrade.deleteJoinOrderLimit')
                    ->tip('Use a subquery to select IDs first, then delete those IDs.')
                    ->build(),
            ];
        }

        return [];
    }
}
