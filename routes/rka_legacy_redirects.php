<?php

use App\Filament\Resources\RkaResource;
use App\Models\RkaCategory;
use App\Support\RkaEntryAccess;
use Illuminate\Support\Facades\Route;

$rkaLegacyRoutes = Route::prefix(trim(config('filament.path', 'admin'), '/'))
    ->middleware('auth:'.config('filament.auth.guard', 'web'));
if (config('filament.domain')) $rkaLegacyRoutes->domain(config('filament.domain'));
$rkaLegacyRoutes->group(function (): void {
    foreach (['rka-investasi' => 'investment', 'rka-maintenance' => 'maintenance', 'rka-consumable' => 'consumables',
        'rka-operasional' => 'operational', 'rka-gaji' => 'salary', 'rka-pendapatan' => 'revenue'] as $slug => $code) {
        Route::get($slug, function () use ($code) {
            abort_unless(RkaEntryAccess::canViewAny(), 403);
            $category = RkaCategory::where('code', $code)->firstOrFail();
            return redirect(RkaResource::getUrl('index', ['activeTab' => (string) $category->id], panel: 'admin'));
        });
    }
});
