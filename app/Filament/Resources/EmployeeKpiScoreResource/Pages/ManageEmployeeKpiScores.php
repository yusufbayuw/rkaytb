<?php

namespace App\Filament\Resources\EmployeeKpiScoreResource\Pages;

use App\Filament\Resources\EmployeeKpiScoreResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeKpiScores extends ManageRecords
{
    protected static string $resource = EmployeeKpiScoreResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
