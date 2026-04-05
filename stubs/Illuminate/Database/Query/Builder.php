<?php

namespace Illuminate\Database\Query;

class Builder
{
    public function upsert(array $values, array|string $uniqueBy, ?array $update = null): int
    {
        return 0;
    }

    public function join(string $table, mixed $first = null, mixed $operator = null, mixed $second = null): static
    {
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        return $this;
    }

    public function limit(int $value): static
    {
        return $this;
    }

    public function delete(): int
    {
        return 0;
    }
}
