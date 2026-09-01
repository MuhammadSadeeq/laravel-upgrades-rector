<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;

/**
 * Laravel 11 removed the schema-layer Doctrine DBAL bridge from typed
 * Connection receivers.
 *
 * @implements Rule<MethodCall>
 */
final class DoctrineRemovedMethodsRule implements Rule
{
    /** @var array<string, string> */
    private const REPLACEMENTS = [
        'getdoctrineconnection' => 'use the native connection API',
        'getdoctrineschemamanager' => 'use Schema inspection methods',
        'getdoctrinecolumn' => 'use Schema::getColumnType() or native schema inspection',
        'registerdoctrinetype' => 'use a native database type or migration',
        'isdoctrineavailable' => 'remove the Doctrine availability check',
    ];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall || ! $node->name instanceof Identifier) {
            return [];
        }

        $method = $node->name->toLowerString();
        $replacement = self::REPLACEMENTS[$method] ?? null;
        $isLowConfidenceCandidate = str_starts_with($method, 'getdoctrine')
            || $method === 'registerdoctrinetype';

        if ($replacement === null && ! $isLowConfidenceCandidate) {
            return [];
        }

        $type = $scope->getType($node->var);
        $isConnection = (new ObjectType('Illuminate\\Database\\Connection'))->isSuperTypeOf($type)->yes();

        if ($isConnection && $replacement !== null) {
            return [$this->error($node->name->toString(), $replacement, 'high')];
        }

        if (! $isLowConfidenceCandidate || ! $type instanceof MixedType) {
            return [];
        }

        return [$this->error(
            $node->name->toString(),
            $replacement ?? 'review the native database API',
            'low',
            true,
        )];
    }

    private function error(
        string $method,
        string $replacement,
        string $confidence,
        bool $includeConfidenceMetadata = false,
    ): IdentifierRuleError {
        $builder = RuleErrorBuilder::message(
            sprintf('%s() was removed from Laravel 11 (%s-confidence).', $method, $confidence)
        )->identifier('laravelUpgrade.doctrineRemovedMethods')
            ->tip('Doctrine DBAL schema methods are gone; '.$replacement.'.');

        if ($includeConfidenceMetadata) {
            $builder->metadata(['confidence' => 'low']);
        }

        return $builder->build();
    }
}
