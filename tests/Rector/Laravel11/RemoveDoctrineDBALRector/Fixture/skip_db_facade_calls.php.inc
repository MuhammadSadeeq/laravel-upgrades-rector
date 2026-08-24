<?php

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

class StaticCaller
{
    public function example()
    {
        DB::getAllTables();
        DB::getAllViews();

        // This should not be changed
        DB::select('SELECT * FROM users');
    }
}

?>
