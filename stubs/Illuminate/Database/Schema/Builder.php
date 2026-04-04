<?php

namespace Illuminate\Database\Schema;

class Builder
{
    /** @return array<int, mixed> */
    public function getAllTables(): array { return []; }

    /** @return array<int, mixed> */
    public function getAllViews(): array { return []; }

    /** @return array<int, mixed> */
    public function getAllTypes(): array { return []; }

    /** @return array<int, mixed> */
    public function getTables(): array { return []; }

    /** @return array<int, mixed> */
    public function getViews(): array { return []; }

    /** @return array<int, mixed> */
    public function getTypes(): array { return []; }

    public function getColumnType(string $table, string $column): string { return 'string'; }
}
