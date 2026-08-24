<?php

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Contracts\Routing\ResponseFactory;

return [
    [
        'interface' => Store::class,
        'method' => 'touch',
        // touch($key, $seconds) is declared untyped by the interface.
        'definition' => <<<'PHP'
public function touch($key, $seconds)
{
}
PHP,
    ],
    [
        'interface' => Repository::class,
        'method' => 'touch',
        'definition' => <<<'PHP'
public function touch($key, $ttl)
{
}
PHP,
    ],
    [
        'interface' => Queue::class,
        'method' => 'pendingSize',
        'definition' => <<<'PHP'
public function pendingSize($queue = null): int
{
    return 0;
}
PHP,
    ],
    [
        'interface' => Queue::class,
        'method' => 'delayedSize',
        'definition' => <<<'PHP'
public function delayedSize($queue = null): int
{
    return 0;
}
PHP,
    ],
    [
        'interface' => Queue::class,
        'method' => 'reservedSize',
        'definition' => <<<'PHP'
public function reservedSize($queue = null): int
{
    return 0;
}
PHP,
    ],
    [
        'interface' => Queue::class,
        'method' => 'creationTimeOfOldestPendingJob',
        'definition' => <<<'PHP'
public function creationTimeOfOldestPendingJob($queue = null): ?int
{
    return null;
}
PHP,
    ],
    [
        'interface' => Dispatcher::class,
        'method' => 'dispatchAfterResponse',
        'definition' => <<<'PHP'
public function dispatchAfterResponse($command, $handler = null)
{
}
PHP,
    ],
    [
        'interface' => ResponseFactory::class,
        'method' => 'eventStream',
        'definition' => <<<'PHP'
public function eventStream(\Closure $callback, array $headers = [], \Illuminate\Http\StreamedEvent|string|null $endStreamWith = '</stream>'): \Symfony\Component\HttpFoundation\StreamedResponse
{
    throw new \LogicException('eventStream() must be implemented to satisfy the updated contract.');
}
PHP,
    ],
    [
        'interface' => MustVerifyEmail::class,
        'method' => 'markEmailAsUnverified',
        // Eloquent models get a working body via forceFill(); anything else
        // gets an explicit TODO so a silent `return false` lie is gone.
        'definition_eloquent' => <<<'PHP'
public function markEmailAsUnverified(): bool
{
    $this->forceFill(['email_verified_at' => null])->save();

    return true;
}
PHP,
        'todo_eloquent' => 'verify the cleared attribute matches your verification flow.',
        'definition' => <<<'PHP'
public function markEmailAsUnverified(): bool
{
    return false;
}
PHP,
    ],
];
