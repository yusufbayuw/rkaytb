<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Filament\Resources\PositionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePositions extends ManageRecords
{
    protected static string $resource = PositionResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
