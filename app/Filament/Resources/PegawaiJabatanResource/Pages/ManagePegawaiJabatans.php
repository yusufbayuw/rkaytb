<?php

namespace App\Filament\Resources\PegawaiJabatanResource\Pages;

use App\Filament\Resources\PegawaiJabatanResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePegawaiJabatans extends ManageRecords
{
    protected static string $resource = PegawaiJabatanResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
