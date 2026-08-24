<?php

namespace App\Fixtures\Laravel12\UpdateRoutePrecedenceRector;

Route::get('/dashboard', DashboardController::class)->name('dashboard');
Route::get('/home', HomeController::class)->name('home');
