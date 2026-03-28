<?php

namespace Illuminate\Database;

class Connection implements ConnectionInterface
{
    public function table(string $table, ?string $as = null) { return null; }

    /** @return array<int, mixed> */
    public function select(string $query, array $bindings = [], bool $useReadPdo = true): array { return []; }

    public function insert(string $query, array $bindings = []): bool { return true; }

    public function update(string $query, array $bindings = []): int { return 0; }

    public function delete(string $query, array $bindings = []): int { return 0; }

    public function statement(string $query, array $bindings = []): bool { return true; }

    public function scalar(string $query, array $bindings = [], bool $useReadPdo = true): mixed { return null; }

    public function raw(mixed $value) { return $value; }

    public function getDatabaseName(): string { return ''; }

    public function getTablePrefix(): string { return ''; }

    public function selectOne(string $query, array $bindings = [], bool $useReadPdo = true): mixed { return null; }

    public function getDoctrineConnection(): mixed { return null; }

    public function getDoctrineSchemaManager(): mixed { return null; }

    public function getDoctrineColumn(string $table, string $column): mixed { return null; }

    public function registerDoctrineType(string $class, string $name, string $type): void {}

    public function isDoctrineAvailable(): bool { return false; }
}
