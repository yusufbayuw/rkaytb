<?php

namespace App\Filament\Widgets;

use App\Services\RkaSummary;
use App\Support\{RkaDecimal, RkaEntryAccess};
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RkaOverview extends StatsOverviewWidget
{
    use \Filament\Widgets\Concerns\InteractsWithPageTable;
    protected function getTablePage(): string { return \App\Filament\Resources\RkaResource\Pages\ManageRkas::class; }
    protected function getStats(): array
    {
        if (!RkaEntryAccess::canViewAny()) return [Stat::make('Unit Belum Ditugaskan', '—')];
        $totals = app(RkaSummary::class)->totals($this->getPageTableQuery());
        return [
            Stat::make('Total Belanja', RkaDecimal::display($totals['expense'])),
            Stat::make('Total Pendapatan', RkaDecimal::display($totals['income'])),
            Stat::make('Surplus / Defisit', RkaDecimal::display($totals['balance']))
                ->description($totals['invalid'] ? $totals['invalid'].' nilai historis perlu diperiksa' : 'Sesuai kategori, pencarian, dan filter tabel'),
        ];
    }
}
