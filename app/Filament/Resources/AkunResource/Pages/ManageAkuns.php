<?php

namespace App\Filament\Resources\AkunResource\Pages;

use App\Models\Setting;
use Filament\Actions;
use App\Filament\Resources\AkunResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAkuns extends ManageRecords
{
    protected static string $resource = AkunResource::class;

    protected function getHeaderActions(): array
    {
        $dataSettingVal = Setting::flag(2);
        return [
            Actions\Action::make('toggleAccountMode')->label(fn () => Setting::flag(2) ? 'Mode: Otomatis' : 'Mode: Manual')
                ->visible(fn () => auth()->user()->hasRole('superadmin'))
                ->action(
                    function () {
                        $dataSetting = Setting::firstOrCreate(['id' => 2], ['nama' => 'Nomor Akun Otomatis', 'valbool' => false]);
                        \Illuminate\Support\Facades\Gate::authorize('update', $dataSetting);
                        $dataSetting->valbool = !($dataSetting->valbool);
                        $dataSetting->save();
                        $this->resetTable();
                    }
                )->color(fn () => ($dataSettingVal) ? 'gray' : 'primary'),
            Actions\CreateAction::make()->hidden($dataSettingVal),
        ];
    }

    protected function isTablePaginationEnabled(): bool 
    {
        return !(Setting::flag(2));
    }
}
