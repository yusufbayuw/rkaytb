<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RkaPlatformExportController;

Route::middleware(['web','auth'])->get(
    '/rka-platform/books/{book}/download/{format}',
    RkaPlatformExportController::class
)->name('rka-platform.books.download');
