<?php

namespace App\Filament\Widgets;

use App\Models\RkaEntry;
use App\Services\RkaSummary;
use App\Support\{RkaDecimal, RkaEntryAccess};
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        if (!RkaEntryAccess::canViewAny()) return [Stat::make('Unit Belum Ditugaskan', '—')];
        $query = RkaEntry::query();
        if (!RkaEntryAccess::globalSummary()) RkaEntryAccess::scope($query);
        $totals = app(RkaSummary::class)->totals($query);
        return [
            Stat::make('Total Belanja', RkaDecimal::display($totals['expense'])),
            Stat::make('Total Pendapatan', RkaDecimal::display($totals['income'])),
            Stat::make('Surplus / Defisit', RkaDecimal::display($totals['balance']))
                ->description($totals['invalid'] ? $totals['invalid'].' nilai historis perlu diperiksa' : null),
        ];
    }
}
