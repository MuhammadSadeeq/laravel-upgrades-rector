<?php

namespace Illuminate\Auth;

use Illuminate\Http\Request;

class AuthenticationException extends \Exception
{
    public function redirectTo(Request $request): ?string
    {
        return null;
    }
}
