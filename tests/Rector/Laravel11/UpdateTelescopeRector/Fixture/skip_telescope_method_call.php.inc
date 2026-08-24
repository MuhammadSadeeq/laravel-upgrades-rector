<?php

namespace App\Fixtures\Laravel11\UpdateTelescopeRector;

class SomeController
{
    public function index()
    {
        $telescope = app('telescope');
        $telescope->filter();

        return view('dashboard');
    }
}

?>
