<?php

namespace App\Filament\Resources\KpiResource\Pages;

use App\Filament\Resources\KpiResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageKpis extends ManageRecords
{
    protected static string $resource = KpiResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
