<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RenameQueueBusyEventPropertyRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [PropertyFetch::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof PropertyFetch) {
            return null;
        }

        if (! $this->isName($node->name, 'connection')) {
            return null;
        }

        if (! $this->isObjectType($node->var, new ObjectType('Illuminate\Queue\Events\QueueBusy'))) {
            return null;
        }

        $node->name = new Identifier('connectionName');

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rename connection property to connectionName on QueueBusy event for Laravel 13',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Queue\Events\QueueBusy;

function handle(QueueBusy $event) {
    $value = $event->connection;
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Queue\Events\QueueBusy;

function handle(QueueBusy $event) {
    $value = $event->connectionName;
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
