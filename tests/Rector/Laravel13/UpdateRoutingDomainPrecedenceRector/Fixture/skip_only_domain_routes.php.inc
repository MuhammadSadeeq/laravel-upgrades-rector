<?php

namespace App\Fixtures\Laravel13\UpdateRoutingDomainPrecedenceRector;

Route::domain('{account}.example.com')->get('/dashboard', DashboardController::class);
Route::domain('{team}.example.com')->get('/billing', BillingController::class);

?>
