<?php

namespace App\Filament\Resources\RkaOperasionalResource\Widgets;

use App\Models\RkaOperasional;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget\Card;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class TotalOperasionalOverview extends BaseWidget
{
    protected function getCards(): array
    {
        $unit_name = Unit::find(auth()->user()->unit_id)->nama;
        $unit_id = auth()->user()->unit_id;
        $formatter = function($total) {
            return number_format($total);
        };

        return [
            auth()->user()->hasRole(['superadmin', 'admin-keuangan']) ? Card::make("Grand Total (Semua Unit)", $formatter(RkaOperasional::isRoot()->sum('total'))) : Card::make("Grand Total ({$unit_name})", $formatter(RkaOperasional::where('unit_nama', $unit_id)->isRoot()->sum('total'))),
        ];
    }
}
