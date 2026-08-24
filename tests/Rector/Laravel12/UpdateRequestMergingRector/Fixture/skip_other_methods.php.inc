<?php

namespace App\Fixtures\Laravel12\UpdateRequestMergingRector;

use Illuminate\Http\Request;

class RequestController
{
    public function process(Request $request)
    {
        $request->merge(['data' => 'value']);
        $request->replace(['new' => 'data']);
        $request->offsetSet('key', 'value');
    }
}

?>
