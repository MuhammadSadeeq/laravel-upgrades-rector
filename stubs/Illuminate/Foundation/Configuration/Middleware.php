<?php

namespace Illuminate\Foundation\Configuration;

class Middleware
{
    public function validateCsrfTokens(): static
    {
        return $this;
    }

    public function preventRequestForgery(): static
    {
        return $this;
    }
}
