<?php

namespace App\Filament\Widgets;

use App\Models\RkaGaji;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

abstract class LegacyBudgetOverview extends StatsOverviewWidget
{
    protected static string $model;

    protected function getStats(): array
    {
        $user = auth()->user();
        $global = $user?->hasAnyRole(['superadmin', 'admin-keuangan']);
        $unit = $user?->unit_id ? Unit::find($user->unit_id) : null;
        if (!$global && !$unit) return [Stat::make('Unit Belum Ditugaskan', '—')];
        $query = static::$model::query();
        if (static::$model !== RkaGaji::class) $query->isLeaf();
        if (!$global) $query->where('unit_nama', (string) $unit->id);
        return [Stat::make('Grand Total ('.($global ? 'Semua Unit' : $unit->nama).')',
            number_format((float) $query->sum('total'), 2, ',', '.'))];
    }
}
