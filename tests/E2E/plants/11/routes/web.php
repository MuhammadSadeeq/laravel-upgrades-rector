<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));
Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');

// Load the API route file explicitly so route:list observes a cross-file
// duplicate name, as it would in a composed application route registry.
require __DIR__.'/api.php';
