<?php

namespace App\Fixtures\Laravel12\UpdateRequestMergingRector;

use Illuminate\Http\Request;

class AlreadyDone
{
    public function process(Request $request)
    {
        // @laravel-upgrade request-merge-dots mergeIfMissing() now supports nested array merging with dot notation. This may change behavior if you were relying on shallow merging.
        $request->mergeIfMissing(['key' => 'value']);
    }
}

?>
