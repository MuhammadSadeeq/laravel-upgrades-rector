<?php

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Schema::getAllTables();
DB::getAllViews();

?>
-----
<?php

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Schema::getTables();
DB::getAllViews();

?>
