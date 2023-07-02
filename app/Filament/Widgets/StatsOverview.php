<?php

namespace App\Filament\Widgets;

use App\Models\Rka;
use App\Models\RkaConsumable;
use App\Models\RkaGaji;
use App\Models\RkaMaintenance;
use App\Models\RkaOperasional;
use App\Models\RkaPendapatan;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class StatsOverview extends BaseWidget
{
    protected function getCards(): array
    {
        $unit_name = Unit::find(auth()->user()->unit_id)->nama;
        $unit_id = auth()->user()->unit_id;
        $formatter = function($total) {
            return number_format($total);
        };

        return [
            Card::make("Total Pragnosa Pendapatan ({$unit_name})", $formatter(RkaPendapatan::where('unit_nama', $unit_id)->isRoot()->sum('total'))),
            Card::make("Total Anggaran Gaji ({$unit_name})", $formatter(RkaGaji::where('unit_nama', $unit_id)->sum('total'))),
            Card::make("Total Anggaran Maintenance ({$unit_name})", $formatter(RkaMaintenance::where('unit_nama', $unit_id)->isRoot()->sum('total'))),
            Card::make("Total Anggaran Consumable ({$unit_name})", $formatter(RkaConsumable::where('unit_nama', $unit_id)->isRoot()->sum('total'))),
            Card::make("Total Anggaran Operasional ({$unit_name})", $formatter(RkaOperasional::where('unit_nama', $unit_id)->isRoot()->sum('total'))),
            Card::make("Total Anggaran Investasi ({$unit_name})", $formatter(Rka::where('unit_nama', $unit_id)->isRoot()->sum('total'))),
        ];
    }
}

