<?php

namespace App\Filament\Resources\AkunResource\Pages;

use App\Models\Setting;
use Filament\Pages\Actions;
use App\Filament\Resources\AkunResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAkuns extends ManageRecords
{
    protected static string $resource = AkunResource::class;

    protected function getActions(): array
    {
        $dataSettingVal = Setting::find(2)->valbool;
        return [
            Actions\Action::make(($dataSettingVal) ? 'Mode: Otomatis' : 'Mode: Manual')
                ->action(
                    function () {
                        $dataSetting = Setting::find(2);
                        $dataSetting->valbool = !($dataSetting->valbool);
                        $dataSetting->save();
                    }
                )->color(fn () => ($dataSettingVal) ? 'secondary' : 'primary'),
            Actions\CreateAction::make()->hidden($dataSettingVal),
        ];
    }

    protected function isTablePaginationEnabled(): bool 
    {
        return !(Setting::find(2)->valbool);
    }
}
