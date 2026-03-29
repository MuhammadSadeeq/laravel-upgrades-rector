<?php

namespace Illuminate\Contracts\Cache;

interface Store
{
    public function get(string $key): mixed;

    public function many(array $keys): array;

    public function put(string $key, mixed $value, int $seconds): bool;

    public function putMany(array $values, int $seconds): bool;

    public function increment(string $key, int $value = 1): int|bool;

    public function decrement(string $key, int $value = 1): int|bool;

    public function forever(string $key, mixed $value): bool;

    public function forget(string $key): bool;

    public function flush(): bool;

    public function getPrefix(): string;

    public function touch(string $key, int $seconds): bool;
}
