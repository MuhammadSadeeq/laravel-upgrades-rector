<?php

namespace Illuminate\Support\Facades;

class DB
{
    /** @return mixed */
    public static function connection(): mixed { return null; }

    /** @return mixed */
    public static function select(string $query): mixed { return null; }

    /** @return mixed */
    public static function getDoctrineConnection(): mixed { return null; }

    /** @return array<int, mixed> */
    public static function getAllTables(): array { return []; }

    /** @return array<int, mixed> */
    public static function getAllViews(): array { return []; }

    /** @return array<int, mixed> */
    public static function getTables(): array { return []; }

    /** @return array<int, mixed> */
    public static function getViews(): array { return []; }
}
