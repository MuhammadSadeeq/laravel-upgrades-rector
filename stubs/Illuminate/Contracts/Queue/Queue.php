<?php

namespace Illuminate\Contracts\Queue;

interface Queue
{
    public function size(?string $queue = null): int;

    public function push(mixed $job, mixed $data = '', ?string $queue = null): mixed;

    public function pushOn(string $queue, mixed $job, mixed $data = ''): mixed;

    public function pushRaw(string $payload, ?string $queue = null, array $options = []): mixed;

    public function later(mixed $delay, mixed $job, mixed $data = '', ?string $queue = null): mixed;

    public function laterOn(string $queue, mixed $delay, mixed $job, mixed $data = ''): mixed;

    public function bulk(array $jobs, mixed $data = '', ?string $queue = null): mixed;

    public function pop(?string $queue = null): mixed;

    public function getConnectionName(): string;

    public function setConnectionName(string $name): static;

    public function pendingSize(?string $queue = null): int;

    public function delayedSize(?string $queue = null): int;

    public function reservedSize(?string $queue = null): int;

    public function creationTimeOfOldestPendingJob(?string $queue = null): ?int;
}
