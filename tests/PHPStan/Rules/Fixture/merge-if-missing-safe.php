<?php

namespace App;

use Illuminate\Http\Request;

function mergeTopLevelInput(Request $request): void
{
    $request->mergeIfMissing([
        'last_name' => 'Otwell',
    ]);
}
