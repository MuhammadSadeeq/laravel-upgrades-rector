<?php

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