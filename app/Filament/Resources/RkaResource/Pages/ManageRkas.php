<?php

namespace App\Filament\Resources\RkaResource\Pages;

use App\Filament\Resources\RkaResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRkas extends ManageRecords
{
    protected static string $resource = RkaResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
