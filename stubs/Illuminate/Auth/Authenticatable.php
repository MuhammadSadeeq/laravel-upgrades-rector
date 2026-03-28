<?php

namespace Illuminate\Auth;

trait Authenticatable
{
    protected string $authPasswordName = 'password';

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    public function getAuthPassword(): string
    {
        return $this->{$this->authPasswordName};
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken(string $value): void
    {
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
