<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class SomeConfiguration
{
    public function configure()
    {
        // These should not be changed as they're not Passport calls
        Auth::routes();
        Config::set('app.name', 'My App');
        SomeClass::staticMethod();
    }
}

?>