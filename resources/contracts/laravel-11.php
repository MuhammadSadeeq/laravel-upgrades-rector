<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\ConnectionInterface;

return [
    [
        'interface' => ConnectionInterface::class,
        'method' => 'scalar',
        // The interface declares untyped parameters — adding native types here
        // would be a fatal declaration-compatibility error.
        'definition' => <<<'PHP'
public function scalar($query, $bindings = [], $useReadPdo = true): mixed
{
    return null;
}
PHP,
        'todo' => 'implement scalar() to satisfy the updated contract.',
    ],
    [
        'interface' => Mailer::class,
        'method' => 'sendNow',
        'definition' => <<<'PHP'
public function sendNow($mailable, array $data = [], $callback = null): ?\Illuminate\Mail\SentMessage
{
    return null;
}
PHP,
        'todo' => 'implement sendNow() to satisfy the updated contract.',
    ],
    [
        'interface' => UserProvider::class,
        'method' => 'rehashPasswordIfRequired',
        'definition' => <<<'PHP'
public function rehashPasswordIfRequired(\Illuminate\Contracts\Auth\Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
{
}
PHP,
        'todo' => 'implement rehashPasswordIfRequired() to satisfy the updated contract.',
    ],
    [
        'interface' => \Illuminate\Contracts\Auth\Authenticatable::class,
        'method' => 'getAuthPasswordName',
        'definition' => <<<'PHP'
public function getAuthPasswordName(): string
{
    return 'password';
}
PHP,
        'todo' => 'adjust this default when the password credential column is not "password".',
    ],
    [
        'interface' => BatchRepository::class,
        'method' => 'rollBack',
        'definition' => <<<'PHP'
public function rollBack(): void
{
}
PHP,
        'todo' => 'implement rollBack() to satisfy the updated contract.',
    ],
];
