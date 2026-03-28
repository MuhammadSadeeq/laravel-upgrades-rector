<?php

namespace Illuminate\Database;

interface ConnectionInterface
{
    public function table(string $table, ?string $as = null);

    public function select(string $query, array $bindings = [], bool $useReadPdo = true): array;

    public function insert(string $query, array $bindings = []): bool;

    public function update(string $query, array $bindings = []): int;

    public function delete(string $query, array $bindings = []): int;

    public function statement(string $query, array $bindings = []): bool;

    public function scalar(string $query, array $bindings = [], bool $useReadPdo = true): mixed;

    public function raw(mixed $value);

    public function getDatabaseName(): string;

    public function getTablePrefix(): string;

    public function selectOne(string $query, array $bindings = [], bool $useReadPdo = true): mixed;
}
