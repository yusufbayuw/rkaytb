<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Support\LegacySettings::class);
        $this->app->scoped(\App\Support\RkaEntryAccess::class);
        $this->app->bind(\MrAdder\FilamentLogger\Support\ActivityExporter::class, \App\Services\HistoricalActivityExporter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \BezhanSalleh\FilamentShield\Facades\FilamentShield::buildPermissionKeyUsing(\App\Support\StandardPermissions::key(...));
        \BezhanSalleh\FilamentShield\Facades\FilamentShield::prohibitDestructiveCommands($this->app->environment('production'));
        $checks = [\Spatie\Health\Checks\Checks\DatabaseCheck::new(), \Spatie\Health\Checks\Checks\CacheCheck::new(),
            \App\Health\StorageWritableCheck::new()];
        if ($this->app->environment('production')) {
            $checks[] = \Spatie\Health\Checks\Checks\DebugModeCheck::new();
            $checks[] = \Spatie\Health\Checks\Checks\OptimizedAppCheck::new();
        }
        \Spatie\Health\Facades\Health::checks($checks);
    }
}
