<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Additive module routes. Existing web endpoints remain unchanged.
require __DIR__.'/rka_platform.php';
