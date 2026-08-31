<?php

use Illuminate\Support\Facades\Route;

Route::get('/api/home', fn () => 'home')->name('dashboard');
