<?php

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

use Doctrine\DBAL\Types\Type, Illuminate\Support\Facades\Schema;

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

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

use Illuminate\Support\Facades\Schema;

class Example
{
    public function test()
    {
        return Schema::hasTable('users');
    }
}

?>
