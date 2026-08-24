<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeMigrationRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSubscriptionsTable extends Migration
{
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('name', 'type');
        });
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeMigrationRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSubscriptionsTable extends Migration
{
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Cashier Stripe 15: Renamed "name" column to "type" in subscriptions table
            $table->renameColumn('name', 'type');
        });
    }
}

?>
