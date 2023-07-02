<?php

namespace App\Filament\Resources\RkaOperasionalResource\Pages;

use App\Models\User;
use App\Models\Setting;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Resources\RkaOperasionalResource;
use App\Filament\Resources\RkaOperasionalResource\Widgets\TotalOperasionalOverview;

class ManageRkaOperasionals extends ManageRecords
{
    protected static string $resource = RkaOperasionalResource::class;

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
                )->color(fn () => (auth()->user()->is_editmode) ? 'secondary' : 'primary')
                ->hidden(Setting::find(1)->valbool),
            Actions\CreateAction::make()->hidden(Setting::find(1)->valbool),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [50, 100, 200, 300, 500];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TotalOperasionalOverview::class,
        ];
    }
}
