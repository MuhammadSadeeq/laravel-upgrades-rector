<?php

use Illuminate\Support\Facades\DB;

class StaticExample
{
    public function example()
    {
        DB::getDoctrineConnection();
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
        DB::getDoctrineConnection();
        DB::getTables();
        DB::getViews();

        // This should not be changed
        DB::select('SELECT * FROM users');
    }
}

?>