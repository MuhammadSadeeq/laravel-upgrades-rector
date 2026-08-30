<?php

namespace App;

use Illuminate\Http\Request;

function mergeNestedInput(Request $request): void
{
    $request->mergeIfMissing([
        'user.last_name' => 'Otwell',
    ]);
}
