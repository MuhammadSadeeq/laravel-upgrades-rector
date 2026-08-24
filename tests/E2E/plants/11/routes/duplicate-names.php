<?php

use Illuminate\Support\Facades\Route;

Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');
Route::get('/home', fn () => 'home')->name('dashboard');
