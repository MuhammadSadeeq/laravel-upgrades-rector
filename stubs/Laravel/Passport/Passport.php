<?php

namespace Laravel\Passport;

class Passport
{
    public static function routes(callable $callback = null, array $options = []): void
    {
    }

    public static function tokensExpireIn(\DateTimeInterface $date = null): void
    {
    }

    public static function refreshTokensExpireIn(\DateTimeInterface $date = null): void
    {
    }

    public static function enablePasswordGrant(): void
    {
    }
}
