<?php

namespace App\Filament\Resources\RkaCategoryResource\Pages;

class ManageRkaCategories extends \Filament\Resources\Pages\ManageRecords
{
    use \App\Support\RequiresSystemAccess;
    protected static string $resource = \App\Filament\Resources\RkaCategoryResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()->label('Tambah kategori')]; }
}
