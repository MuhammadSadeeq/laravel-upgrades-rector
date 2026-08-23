<?php

namespace App\Http;

use Illuminate\Http\Client\Response;

class AlreadyUpdatedResponse extends Response
{
    public function throw($callback = null)
    {
        parent::throw($callback);
    }

    public function throwIf($condition, $callback = null)
    {
        parent::throwIf($condition, $callback);
    }
}
