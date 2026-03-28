<?php

namespace Illuminate\Database\Schema;

class Blueprint
{
    /** @return $this */
    public function id(): self { return $this; }

    public function string(string $column, ?int $length = null): ColumnDefinition { return new ColumnDefinition(); }

    public function integer(string $column): ColumnDefinition { return new ColumnDefinition(); }

    public function text(string $column): ColumnDefinition { return new ColumnDefinition(); }

    public function decimal(string $column, int $total = 8, int $places = 2): ColumnDefinition { return new ColumnDefinition(); }

    public function double(string $column, ?int $total = null, ?int $places = null): ColumnDefinition { return new ColumnDefinition(); }

    public function float(string $column, ?int $total = null, ?int $places = null): ColumnDefinition { return new ColumnDefinition(); }

    public function unsignedDecimal(string $column, int $total = 8, int $places = 2): ColumnDefinition { return new ColumnDefinition(); }

    public function unsignedDouble(string $column): ColumnDefinition { return new ColumnDefinition(); }

    public function unsignedFloat(string $column, int $total = 8, int $places = 2): ColumnDefinition { return new ColumnDefinition(); }

    /** @return $this */
    public function unsigned(): self { return $this; }

    /** @return $this */
    public function nullable(): self { return $this; }

    /** @return $this */
    public function default(mixed $value): self { return $this; }

    /** @return $this */
    public function change(): self { return $this; }

    /** @return $this */
    public function unique(): self { return $this; }

    public function point(string $column): ColumnDefinition { return new ColumnDefinition(); }

    public function lineString(string $column): ColumnDefinition { return new ColumnDefinition(); }

    public function polygon(string $column): ColumnDefinition { return new ColumnDefinition(); }

    public function geometryCollection(string $column): ColumnDefinition { return new ColumnDefinition(); }

    public function multiPoint(string $column): ColumnDefinition { return new ColumnDefinition(); }

    public function multiLineString(string $column): ColumnDefinition { return new ColumnDefinition(); }

    public function multiPolygon(string $column): ColumnDefinition { return new ColumnDefinition(); }

    public function multiPolygonZ(string $column): ColumnDefinition { return new ColumnDefinition(); }

    public function geometry(string $column, ?string $subtype = null): ColumnDefinition { return new ColumnDefinition(); }

    public function timestamps(): void {}

    public function rememberToken(): void {}

    public function timestamp(string $column): ColumnDefinition { return new ColumnDefinition(); }

    /** @param array<int, string> $columns */
    public function dropColumn(array $columns): void {}

    /** @return $this */
    public function renameColumn(string $from, string $to): self { return $this; }

    public function dropUnique(string|array $index): void {}

    /** @return $this */
    public function index(string|array $columns, ?string $name = null): self { return $this; }
}
