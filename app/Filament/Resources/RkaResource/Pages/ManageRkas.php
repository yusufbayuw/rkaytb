<?php

namespace App\Filament\Resources\RkaResource\Pages;

use Closure;
use App\Models\Rka;
use App\Models\User;
use App\Models\Setting;
use Filament\Actions;
use App\Filament\Resources\RkaResource;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Resources\RkaResource\Widgets\TotalOverview;

class ManageRkas extends ManageRecords
{
    protected static string $resource = RkaResource::class;

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
