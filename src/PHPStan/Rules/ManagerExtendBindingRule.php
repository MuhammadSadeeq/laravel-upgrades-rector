<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Laravel 13: Manager::extend() callbacks now receive the container instance.
 * Flags extend() calls on framework manager classes so developers can update
 * their closure signatures.
 *
 * @implements Rule<MethodCall>
 */
final class ManagerExtendBindingRule implements Rule
{
    private const MANAGER_CLASSES = [
        'Illuminate\Auth\AuthManager',
        'Illuminate\Cache\CacheManager',
        'Illuminate\Queue\QueueManager',
        'Illuminate\Filesystem\FilesystemManager',
        'Illuminate\Broadcasting\BroadcastManager',
        'Illuminate\Session\SessionManager',
        'Illuminate\Mail\MailManager',
        'Illuminate\Log\LogManager',
        'Illuminate\Database\DatabaseManager',
    ];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'extend') {
            return [];
        }

        if ($node->var instanceof StaticCall
            || $node->var instanceof ClassConstFetch) {
            return [];
        }

        $type = $scope->getType($node->var);

        foreach (self::MANAGER_CLASSES as $fqcn) {
            if ((new ObjectType($fqcn))->isSuperTypeOf($type)->yes()) {
                return [
                    RuleErrorBuilder::message(
                        'Manager extend() callbacks now receive the container instance in Laravel 13.'
                    )->identifier('laravelUpgrade.managerExtendBinding')
                        ->tip('Update the closure signature to accept the container or use the passed manager instance.')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
