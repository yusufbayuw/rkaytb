<?php

namespace App\Filament\Resources\RkaResource\Pages;

use Closure;
use App\Models\Rka;
use App\Models\User;
use App\Models\Setting;
use Filament\Pages\Actions;
use App\Filament\Resources\RkaResource;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Resources\RkaResource\Widgets\TotalOverview;

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
                )->color(fn () => (auth()->user()->is_editmode) ? 'secondary' : 'primary')
                ->hidden(Setting::find(1)->valbool),
            Actions\CreateAction::make()->hidden(Setting::find(1)->valbool),
        ];
    }

    public function isTableRecordSelectable(): ?Closure
    {
        return fn(Rka $record): bool => false;
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [50, 100, 200, 300, 500];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TotalOverview::class,
        ];
    }
}
