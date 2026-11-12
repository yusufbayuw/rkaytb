<?php

namespace App\Filament\Resources\RkaMaintenanceResource\Pages;

use App\Models\User;
use App\Models\Setting;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Resources\RkaMaintenanceResource;
use App\Filament\Resources\RkaMaintenanceResource\Widgets\TotalMaintenanceOverview;

class ManageRkaMaintenances extends ManageRecords
{
    protected static string $resource = RkaMaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggleEditMode')->label(fn () => auth()->user()->is_editmode ? 'Mode: Edit' : 'Mode: Normal')
                ->action(
                    function () {
                        abort_unless(\App\Support\LegacyRkaAccess::canWrite(auth()->user()), 403);
                        $userData = auth()->user();
                        $userData->is_editmode = !(auth()->user()->is_editmode);
                        $userData->save();
                        $this->resetTable();
                    }
                )->color(fn () => (auth()->user()->is_editmode) ? 'gray' : 'primary')
                ->hidden(!\App\Support\LegacyRkaAccess::canWrite(auth()->user())),
            Actions\CreateAction::make()->hidden(Setting::flag(1)),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [50, 100, 200, 300, 500];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TotalMaintenanceOverview::class,
        ];
    }
}
