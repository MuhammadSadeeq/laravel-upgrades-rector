<?php

use Illuminate\Support\Facades\Schema;

class NoChangesExample
{
    public function example()
    {
        // These methods should not be changed
        Schema::hasTable('users');
        Schema::getColumns('users');
        Schema::getIndexes('users');

        $connection = DB::connection();
        $connection->select('SELECT * FROM users');

        // Regular method calls that don't match
        $this->someOtherMethod();
        $this->getColumnData();
    }
}

?>