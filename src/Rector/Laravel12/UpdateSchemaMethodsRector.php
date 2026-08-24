<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\CommentInserter;
use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\StaticCallExtractor;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateSchemaMethodsRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade schema-inspection-methods';

    public function __construct(
        private readonly StaticCallExtractor $staticCallExtractor,
        private readonly CommentInserter $commentInserter,
    ) {}

    /** @var array<int, string> */
    private array $schemaMethodsWithSchemaParam = [
        'getTables',
        'getViews',
        'getTypes',
        'getTableListing',
    ];

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        $staticCall = $this->staticCallExtractor->extract($node);

        if ($staticCall === null) {
            return null;
        }

        if (! $staticCall->class instanceof Name) {
            return null;
        }

        if (
            ! $this->isName($staticCall->class, 'Schema') &&
            ! $this->isName($staticCall->class, 'Illuminate\Support\Facades\Schema')
        ) {
            return null;
        }

        $methodName = $this->getName($staticCall->name);
        if ($methodName === null || ! in_array($methodName, $this->schemaMethodsWithSchemaParam, true)) {
            return null;
        }

        if (count($staticCall->args) !== 0) {
            return null;
        }

        if (! $this->commentInserter->addComment(
            $node,
            self::COMMENT_MARKER,
            $this->resolveCommentMessage($methodName)
        )) {
            return null;
        }

        return $node;
    }

    private function resolveCommentMessage(string $methodName): string
    {
        if ($methodName === 'getTableListing') {
            return 'getTableListing() now returns schema-qualified table names from all schemas by default. Pass schema and schemaQualified arguments to preserve previous behavior.';
        }

        return 'This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.';
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comment for Schema methods that now return multi-schema results by default in Laravel 12',
            [
                new CodeSample(
                    '$tables = Schema::getTables();',
                    '// Laravel 12: This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
$tables = Schema::getTables();',
                ),
            ],
        );
    }
}
