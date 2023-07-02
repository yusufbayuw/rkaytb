<?php

namespace App\Filament\Resources\RkaConsumableResource\Widgets;

use App\Models\RkaConsumable;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget\Card;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class TotalConsumableOverview extends BaseWidget
{
    protected function getCards(): array
    {
        $unit_name = Unit::find(auth()->user()->unit_id)->nama;
        $unit_id = auth()->user()->unit_id;
        $formatter = function($total) {
            return number_format($total);
        };

        return [
            auth()->user()->hasRole(['superadmin', 'admin-keuangan']) ? Card::make("Grand Total (Semua Unit)", $formatter(RkaConsumable::isRoot()->sum('total'))) : Card::make("Grand Total ({$unit_name})", $formatter(RkaConsumable::where('unit_nama', $unit_id)->isRoot()->sum('total'))),
        ];
    }
}
