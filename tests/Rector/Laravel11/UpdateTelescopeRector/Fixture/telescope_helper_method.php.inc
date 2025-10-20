<?php

class TestController
{
    public function show()
    {
        $app->telescope();

        return response()->json(['status' => 'ok']);
    }
}

?>