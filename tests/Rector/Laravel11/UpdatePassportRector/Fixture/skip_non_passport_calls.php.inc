<?php

namespace App\Fixtures\Laravel11\UpdatePassportRector;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class SomeConfiguration
{
    public function configure()
    {
        Auth::routes();
        Config::set('app.name', 'My App');
        SomeClass::staticMethod();
    }
}

?>
