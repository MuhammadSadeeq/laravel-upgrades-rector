<?php

Route::get('/health', HealthController::class);
Route::domain('{account}.example.com')->get('/dashboard', DashboardController::class);

?>
-----
<?php

Route::get('/health', HealthController::class);
// Laravel 13: explicit domain routes now take precedence over non-domain routes during matching. Review route behavior if this file mixes both route types.
Route::domain('{account}.example.com')->get('/dashboard', DashboardController::class);

?>
