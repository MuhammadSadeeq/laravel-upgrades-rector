<?php

namespace Illuminate\Http;

class Request
{
    /**
     * @param array<string, mixed> $input
     */
    public function mergeIfMissing(array $input): self
    {
        return $this;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function merge(array $input): self
    {
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return [];
    }

    public function input(string $key): mixed
    {
        return null;
    }
}
