<?php

namespace App\Filament\Resources\JabatanKpiResource\Pages;

use App\Filament\Resources\JabatanKpiResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageJabatanKpis extends ManageRecords
{
    protected static string $resource = JabatanKpiResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
