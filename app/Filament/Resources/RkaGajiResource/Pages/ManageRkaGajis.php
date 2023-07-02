<?php

namespace App\Filament\Resources\RkaGajiResource\Pages;

use App\Models\User;
use App\Models\Setting;
use Filament\Pages\Actions;
use App\Filament\Resources\RkaGajiResource;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Resources\RkaGajiResource\Widgets\TotalGajiOverview;

class ManageRkaGajis extends ManageRecords
{
    protected static string $resource = RkaGajiResource::class;

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
            TotalGajiOverview::class,
        ];
    }
}
