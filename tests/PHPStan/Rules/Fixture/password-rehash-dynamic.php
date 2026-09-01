<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class DynamicPasswordUser extends Authenticatable
{
    public function getAuthPassword()
    {
        return $this->getAttribute($this->getPasswordColumn());
    }

    private function getPasswordColumn(): string
    {
        return 'secret_hash';
    }
}
