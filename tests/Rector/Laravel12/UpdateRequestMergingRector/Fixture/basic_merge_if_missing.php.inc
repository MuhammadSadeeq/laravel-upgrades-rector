<?php

use Illuminate\Http\Request;

class UserController
{
    public function store(Request $request)
    {
        $request->mergeIfMissing([
            'status' => 'active',
            'role' => 'user'
        ]);

        return $request->all();
    }
}

?>
-----
<?php

use Illuminate\Http\Request;

class UserController
{
    public function store(Request $request)
    {
        // Laravel 12: mergeIfMissing() now supports nested array merging with dot notation. This may change behavior if you were relying on shallow merging.
        $request->mergeIfMissing([
            'status' => 'active',
            'role' => 'user'
        ]);

        return $request->all();
    }
}

?>
