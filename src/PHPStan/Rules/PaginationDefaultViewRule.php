<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 13 renames the default pagination views. This catches literal
 * references outside the paginator method calls handled by the Rector rule.
 *
 * @implements Rule<String_>
 */
final class PaginationDefaultViewRule implements Rule
{
    /** @var array<string, string> */
    private const VIEW_MAP = [
        'pagination::default' => 'pagination::bootstrap-3',
        'pagination::simple-default' => 'pagination::simple-bootstrap-3',
    ];

    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof String_ || ! isset(self::VIEW_MAP[$node->value])) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf('Pagination view "%s" was renamed in Laravel 13.', $node->value)
            )->identifier('laravelUpgrade.paginationDefaultView')
                ->tip('Use "'.self::VIEW_MAP[$node->value].'" or configure the published view explicitly.')
                ->build(),
        ];
    }
}
