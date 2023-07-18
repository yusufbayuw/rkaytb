<?php

namespace App\Filament\Resources\JabatanResource\Pages;

use App\Filament\Resources\JabatanResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageJabatans extends ManageRecords
{
    protected static string $resource = JabatanResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
