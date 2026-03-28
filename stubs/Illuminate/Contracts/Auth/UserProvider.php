<?php

namespace Illuminate\Contracts\Auth;

interface UserProvider
{
    public function retrieveById(mixed $identifier): ?Authenticatable;

    public function retrieveByToken(mixed $identifier, string $token): ?Authenticatable;

    public function updateRememberToken(Authenticatable $user, string $token): void;

    public function retrieveByCredentials(array $credentials): ?Authenticatable;

    public function validateCredentials(Authenticatable $user, array $credentials): bool;

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void;
}
