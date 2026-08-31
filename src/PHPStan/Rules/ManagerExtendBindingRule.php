<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Laravel 13: Manager::extend() callbacks now receive the container instance.
 * Flags extend() calls on framework manager classes so developers can update
 * their closure signatures.
 *
 * @implements Rule<Node>
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
        'Illuminate\Redis\RedisManager',
        'Illuminate\Notifications\ChannelManager',
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall && ! $node instanceof StaticCall) {
            return [];
        }

        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'extend') {
            return [];
        }

        if ($node instanceof StaticCall) {
            return $this->isManagerFacade($node, $scope) ? [$this->error()] : [];
        }

        $type = $scope->getType($node->var);

        if ((new ObjectType('Illuminate\\Support\\Manager'))->isSuperTypeOf($type)->yes()) {
            return [$this->error()];
        }

        foreach (self::MANAGER_CLASSES as $fqcn) {
            if ((new ObjectType($fqcn))->isSuperTypeOf($type)->yes()) {
                return [$this->error()];
            }
        }

        return [];
    }

    private function isManagerFacade(StaticCall $call, Scope $scope): bool
    {
        if (! $call->class instanceof Name) {
            return false;
        }

        $name = ltrim($scope->resolveName($call->class), '\\');

        return in_array($name, [
            'Illuminate\\Support\\Facades\\Auth',
            'Illuminate\\Support\\Facades\\Broadcast',
            'Illuminate\\Support\\Facades\\Cache',
            'Illuminate\\Support\\Facades\\DB',
            'Illuminate\\Support\\Facades\\Log',
            'Illuminate\\Support\\Facades\\Mail',
            'Illuminate\\Support\\Facades\\Notification',
            'Illuminate\\Support\\Facades\\Queue',
            'Illuminate\\Support\\Facades\\Redis',
            'Illuminate\\Support\\Facades\\Session',
            'Illuminate\\Support\\Facades\\Storage',
            'Auth',
            'Broadcast',
            'Cache',
            'DB',
            'Log',
            'Mail',
            'Notification',
            'Queue',
            'Redis',
            'Session',
            'Storage',
        ], true);
    }

    private function error(): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            'Manager extend() callbacks now receive the container instance in Laravel 13.'
        )->identifier('laravelUpgrade.managerExtendBinding')
            ->tip('Update the closure signature to accept the container or use the passed manager instance.')
            ->build();
    }
}
