<?php

namespace App\Filament\Resources\PegawaiJabatanKpiResource\Pages;

use App\Filament\Resources\PegawaiJabatanKpiResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePegawaiJabatanKpis extends ManageRecords
{
    protected static string $resource = PegawaiJabatanKpiResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
