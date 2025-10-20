<?php

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