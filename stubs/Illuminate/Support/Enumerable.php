<?php

namespace Illuminate\Support;

interface Enumerable
{
    public function dump(...$args): static;

    /**
     * @return array<mixed>
     */
    public function toArray(): array;
}
