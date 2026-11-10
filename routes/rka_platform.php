<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RkaPlatformExportController;
use App\Http\Controllers\RkaPlatformEvidenceController;

Route::middleware(['web','auth'])->get(
    '/rka-platform/books/{book}/download/{format}',
    RkaPlatformExportController::class
)->name('rka-platform.books.download');

Route::middleware(['web','auth'])->get('/rka-platform/evidence/{evidence}/download',
    RkaPlatformEvidenceController::class)->name('rka-platform.evidence.download');
