<?php

namespace App\Fixtures\Laravel12\UpdateRequestMergingRector;

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

namespace App\Fixtures\Laravel12\UpdateRequestMergingRector;

use Illuminate\Http\Request;

class UserController
{
    public function store(Request $request)
    {
        // @laravel-upgrade request-merge-dots mergeIfMissing() now supports nested array merging with dot notation. This may change behavior if you were relying on shallow merging.
        $request->mergeIfMissing([
            'status' => 'active',
            'role' => 'user'
        ]);

        return $request->all();
    }
}

?>
