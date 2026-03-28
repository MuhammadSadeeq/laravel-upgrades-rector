<?php

use Illuminate\Http\Request;

class AlreadyDone
{
    public function process(Request $request)
    {
        // Laravel 12: mergeIfMissing() now supports nested array merging with dot notation. This may change behavior if you were relying on shallow merging.
        $request->mergeIfMissing(['key' => 'value']);
    }
}

?>
