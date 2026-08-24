<?php

namespace App\Fixtures\Laravel11\UpdateSparkStripeRector;

use Laravel\Spark\Spark;

Spark::ignoreMigrations();

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateSparkStripeRector;

use Laravel\Spark\Spark;

// Laravel 11 Spark Stripe 5: Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=spark-migrations
Spark::ignoreMigrations();

?>
