<?php

use Illuminate\Support\Facades\DB;

class StaticExample
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
-----
<?php

use Illuminate\Support\Facades\DB;

class StaticExample
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
