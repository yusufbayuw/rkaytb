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
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $unit = $user?->unit_id ? Unit::find($user->unit_id) : null;
        $global = $user?->hasAnyRole(['superadmin', 'admin-keuangan']);
        if (!$global && !$unit) {
            return [Stat::make('Unit Belum Ditugaskan', '—')];
        }
        $unit_name = $global ? 'Semua Unit' : $unit->nama;
        $unit_id = $global ? null : $unit->id;
        $formatter = function($total) {
            return number_format((float) $total, 2, ',', '.');
        };

        return [
            Stat::make("Total Pragnosa Pendapatan ({$unit_name})", $formatter(RkaPendapatan::query()->when($unit_id !== null, fn ($query) => $query->where('unit_nama', $unit_id))->isRoot()->sum('total'))),
            Stat::make("Total Anggaran Gaji ({$unit_name})", $formatter(RkaGaji::query()->when($unit_id !== null, fn ($query) => $query->where('unit_nama', $unit_id))->sum('total'))),
            Stat::make("Total Anggaran Maintenance ({$unit_name})", $formatter(RkaMaintenance::query()->when($unit_id !== null, fn ($query) => $query->where('unit_nama', $unit_id))->isRoot()->sum('total'))),
            Stat::make("Total Anggaran Consumable ({$unit_name})", $formatter(RkaConsumable::query()->when($unit_id !== null, fn ($query) => $query->where('unit_nama', $unit_id))->isRoot()->sum('total'))),
            Stat::make("Total Anggaran Operasional ({$unit_name})", $formatter(RkaOperasional::query()->when($unit_id !== null, fn ($query) => $query->where('unit_nama', $unit_id))->isRoot()->sum('total'))),
            Stat::make("Total Anggaran Investasi ({$unit_name})", $formatter(Rka::query()->when($unit_id !== null, fn ($query) => $query->where('unit_nama', $unit_id))->isRoot()->sum('total'))),
        ];
    }
}

