<?php

namespace App\Fixtures\Laravel11\UpdateTelescopeRector;

class TestController
{
    public function show()
    {
        $app->telescope();

        return response()->json(['status' => 'ok']);
    }
}

?>
