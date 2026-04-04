<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\StaticCallExtractor;
use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateSchemaMethodsRector extends AbstractRector
{
    public function __construct(
        private readonly StaticCallExtractor $staticCallExtractor,
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
        if (!$node instanceof Expression) {
            return null;
        }

        $staticCall = $this->staticCallExtractor->extract($node);

        if ($staticCall === null) {
            return null;
        }

        if (!$staticCall->class instanceof Name) {
            return null;
        }

        if (
            !$this->isName($staticCall->class, 'Schema') &&
            !$this->isName($staticCall->class, 'Illuminate\Support\Facades\Schema')
        ) {
            return null;
        }

        $methodName = $this->getName($staticCall->name);
        if ($methodName === null || !in_array($methodName, $this->schemaMethodsWithSchemaParam, true)) {
            return null;
        }

        if (count($staticCall->args) !== 0) {
            return null;
        }

        $existingComments = $node->getComments();
        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), 'Laravel 12:')) {
                return null;
            }
        }

        $newComment = new Comment('// ' . $this->resolveCommentText($methodName));

        $node->setAttribute('comments', array_merge([$newComment], $existingComments));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function resolveCommentText(string $methodName): string
    {
        if ($methodName === 'getTableListing') {
            return 'Laravel 12: getTableListing() now returns schema-qualified table names from all schemas by default. Pass schema and schemaQualified arguments to preserve previous behavior.';
        }

        return 'Laravel 12: This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.';
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
