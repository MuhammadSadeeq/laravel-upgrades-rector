<?php

use Illuminate\Support\Facades\Schema;

class NoChangesExample
{
    public function example()
    {
        Schema::hasTable('users');
        Schema::getColumns('users');
        Schema::getIndexes('users');

        $this->someOtherMethod();
        $this->getColumnData();
    }
}

?>
