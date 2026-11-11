<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RkaPlatformServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/rka_platform.php'));
    }
}
