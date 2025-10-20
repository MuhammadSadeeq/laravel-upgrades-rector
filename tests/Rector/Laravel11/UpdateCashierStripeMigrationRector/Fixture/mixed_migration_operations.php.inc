<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MixedMigrationOperations extends Migration
{
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('new_column');
            $table->renameColumn('name', 'type');
            $table->dropColumn('old_column');
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropUnique(['subscription_id', 'stripe_price']);
            $table->index(['subscription_id', 'stripe_price']);
            $table->string('metadata')->nullable();
        });
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MixedMigrationOperations extends Migration
{
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('new_column');
            $table->renameColumn('name', 'type');
            $table->dropColumn('old_column');
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropUnique(['subscription_id', 'stripe_price']);
            $table->index(['subscription_id', 'stripe_price']);
            $table->string('metadata')->nullable();
        });
    }
}

?>