<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\StatsOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Pages\Dashboard;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->default()->id('admin')
            ->path(config('filament.path', 'admin'))
            ->domain(config('filament.domain'))
            ->authGuard(config('filament.auth.guard', 'web'))
            ->login()
            ->brandName(config('app.name'))
            ->homeUrl('/')
            ->colors(['primary' => Color::Blue])
            ->viteTheme('resources/css/filament.css')
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->renderHook('panels::body.end', fn () => view('filament.print-support'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
                \ShuvroRoy\FilamentSpatieLaravelHealth\FilamentSpatieLaravelHealthPlugin::make()
                    ->usingPage(\App\Filament\Pages\SystemHealth::class)->navigationGroup('Sistem')
                    ->navigationLabel('Kesehatan Aplikasi')->authorize(fn () => \App\Support\SystemAccess::allowed()),
                \AchyutN\FilamentLogViewer\FilamentLogViewer::make()->pageClass(\App\Filament\Pages\SystemLogs::class)
                    ->parserClass(\App\Services\CompatibleLogParser::class)
                    ->providerClass(\App\Services\ReadOnlyLogProvider::class)->navigationGroup('Sistem')
                    ->navigationLabel('Log Teknis')->navigationUrl('system/logs')
                    ->authorize(fn () => \App\Support\SystemAccess::allowed()),
            ])
            ->pages([Dashboard::class])
            ->widgets([AccountWidget::class, StatsOverview::class])
            ->middleware([
                \App\Http\Middleware\EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                \App\Http\Middleware\VerifyCsrfToken::class,
                SubstituteBindings::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
