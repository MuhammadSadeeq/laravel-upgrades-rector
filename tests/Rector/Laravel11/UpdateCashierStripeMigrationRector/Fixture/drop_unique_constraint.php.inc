<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSubscriptionItemsTable extends Migration
{
    public function up()
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropUnique(['subscription_id', 'stripe_price']);
            $table->index(['subscription_id', 'stripe_price']);
        });
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSubscriptionItemsTable extends Migration
{
    public function up()
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            // Cashier Stripe 15: Unique constraint replaced with regular index on subscription_items
            $table->dropUnique(['subscription_id', 'stripe_price']);
            $table->index(['subscription_id', 'stripe_price']);
        });
    }
}

?>
