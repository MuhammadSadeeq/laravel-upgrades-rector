<?php

use Illuminate\Database\DBAL\TimestampType;
use Illuminate\Database\Schema\Grammars\ChangeColumn;
use Illuminate\Database\Schema\Grammars\RenameColumn;
use Illuminate\Support\Facades\Schema;

class Example
{
    public function test()
    {
        // Some regular code
        return Schema::hasTable('users');
    }
}

?>
-----
<?php

/** Laravel 11: Illuminate\Database\DBAL\TimestampType has been removed. Doctrine DBAL is no longer required. */
use Illuminate\Database\DBAL\TimestampType;
/** Laravel 11: Illuminate\Database\Schema\Grammars\ChangeColumn has been removed. Doctrine DBAL is no longer required. */
use Illuminate\Database\Schema\Grammars\ChangeColumn;
/** Laravel 11: Illuminate\Database\Schema\Grammars\RenameColumn has been removed. Doctrine DBAL is no longer required. */
use Illuminate\Database\Schema\Grammars\RenameColumn;
use Illuminate\Support\Facades\Schema;

class Example
{
    public function test()
    {
        // Some regular code
        return Schema::hasTable('users');
    }
}

?>