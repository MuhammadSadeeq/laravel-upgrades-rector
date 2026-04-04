<?php

namespace Illuminate\Support\Facades;

class Schema
{
    /** @param callable(\Illuminate\Database\Schema\Blueprint): void $callback */
    public static function create(string $table, callable $callback): void {}

    /** @param callable(\Illuminate\Database\Schema\Blueprint): void $callback */
    public static function table(string $table, callable $callback): void {}

    public static function dropIfExists(string $table): void {}

    public static function hasTable(string $table): bool { return true; }

    /** @return array<int, mixed> */
    public static function getTables(): array { return []; }

    /** @return array<int, mixed> */
    public static function getViews(): array { return []; }

    /** @return array<int, mixed> */
    public static function getTypes(): array { return []; }

    /** @return array<int, mixed> */
    public static function getColumns(string $table): array { return []; }

    /** @return array<int, mixed> */
    public static function getIndexes(string $table): array { return []; }

    public static function getColumnType(string $table, string $column): string { return 'string'; }

    /** @return array<int, mixed> */
    public static function getAllTables(): array { return []; }

    /** @return array<int, mixed> */
    public static function getAllViews(): array { return []; }

    /** @return array<int, mixed> */
    public static function getAllTypes(): array { return []; }
}
