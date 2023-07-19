<?php

namespace App\Filament\Resources\KpiTargetResource\Pages;

use App\Filament\Resources\KpiTargetResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageKpiTargets extends ManageRecords
{
    protected static string $resource = KpiTargetResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
