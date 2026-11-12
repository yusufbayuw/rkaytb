<?php

namespace App\Filament\Resources\RkaResource\Widgets;

use App\Models\Rka;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class TotalOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $formatter = fn ($total) => number_format((float)$total, 2, ',', '.');

        if ($user->hasAnyRole(['superadmin', 'admin-keuangan'])) {
            return [Stat::make('Grand Total (Semua Unit)',
                $formatter(Rka::isLeaf()->sum('total')))];
        }

        $unit = $user->unit_id ? Unit::find($user->unit_id) : null;
        if (!$unit) {
            // An RKA-only member has no legacy unit. Never dereference null or expose
            // a global financial aggregate as a fallback.
            return [Stat::make('RKA Investasi: Unit Belum Ditugaskan', '—')];
        }

        // Sum leaf records rather than possibly stale parent-string totals.
        return [Stat::make("Grand Total ({$unit->nama})",
            $formatter(Rka::where('unit_nama', $unit->id)->isLeaf()->sum('total')))];
    }
}
