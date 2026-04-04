<?php

Route::get('/dashboard', DashboardController::class)->name('dashboard');
Route::get('/home', HomeController::class)->middleware('auth')->name('dashboard');

?>
-----
<?php

Route::get('/dashboard', DashboardController::class)->name('dashboard');
// Laravel 12: duplicate route names now resolve to the first registered route. This later route no longer wins when names collide.
Route::get('/home', HomeController::class)->middleware('auth')->name('dashboard');

?>
