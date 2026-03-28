<?php

use Doctrine\DBAL\Types\Type;
use Illuminate\Support\Facades\Schema;

class Example
{
    public function test()
    {
        return Schema::hasTable('users');
    }
}

?>
-----
<?php

use Illuminate\Support\Facades\Schema;

class Example
{
    public function test()
    {
        return Schema::hasTable('users');
    }
}

?>
