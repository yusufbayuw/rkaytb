<?php

namespace App\Filament\Resources\RkaResource\Pages;

use App\Models\User;
use Filament\Pages\Actions;
use App\Filament\Resources\RkaResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRkas extends ManageRecords
{
    protected static string $resource = RkaResource::class;

    protected function getActions(): array
    {
        return [
            Actions\Action::make((auth()->user()->is_editmode) ? 'Mode: Edit' : 'Mode: Normal')
                ->action(
                    function () {
                        $userData = User::find(auth()->user()->id);
                        $userData->is_editmode = !(auth()->user()->is_editmode);
                        $userData->save();
                    }
                )->color(fn () => (auth()->user()->is_editmode) ? 'secondary' : 'primary'),
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [50, 100, 200, 300, 500];
    }
}
