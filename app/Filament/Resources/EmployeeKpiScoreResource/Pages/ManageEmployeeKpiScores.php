<?php

namespace App\Filament\Resources\EmployeeKpiScoreResource\Pages;

use App\Filament\Resources\EmployeeKpiScoreResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeKpiScores extends ManageRecords
{
    protected static string $resource = EmployeeKpiScoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
