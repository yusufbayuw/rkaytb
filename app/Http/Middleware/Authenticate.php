<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) return null;

        // The legacy application has a Filament login route, not necessarily
        // the Laravel Breeze/Auth scaffolding route named "login".
        if (Route::has('login')) return route('login');
        if (Route::has('filament.admin.auth.login')) return route('filament.admin.auth.login');

        // Last-resort configured panel URL prevents a 500 for guest downloads.
        return url(trim((string)config('filament.path', 'admin'), '/').'/login');
    }
}
