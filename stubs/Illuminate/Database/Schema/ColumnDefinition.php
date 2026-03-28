<?php

namespace Illuminate\Database\Schema;

class ColumnDefinition
{
    /** @return $this */
    public function nullable(): self { return $this; }

    /** @return $this */
    public function default(mixed $value): self { return $this; }

    /** @return $this */
    public function change(): self { return $this; }

    /** @return $this */
    public function unsigned(): self { return $this; }

    /** @return $this */
    public function unique(): self { return $this; }

    /** @return $this */
    public function index(): self { return $this; }

    /** @return $this */
    public function after(string $column): self { return $this; }

    /** @return $this */
    public function comment(string $comment): self { return $this; }
}
