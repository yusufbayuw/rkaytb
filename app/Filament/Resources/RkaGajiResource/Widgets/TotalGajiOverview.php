<?php

namespace App\Filament\Resources\RkaGajiResource\Widgets;

use App\Models\RkaGaji;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget\Card;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class TotalGajiOverview extends BaseWidget
{
    protected function getCards(): array
    {
        $unit_name = Unit::find(auth()->user()->unit_id)->nama;
        $unit_id = auth()->user()->unit_id;
        $formatter = function($total) {
            return number_format($total);
        };

        return [
            auth()->user()->hasRole(['superadmin', 'admin-keuangan']) ? Card::make("Grand Total (Semua Unit)", $formatter(RkaGaji::sum('total'))) : Card::make("Grand Total ({$unit_name})", $formatter(RkaGaji::where('unit_nama', $unit_id)->sum('total'))),
        ];
    }
}
