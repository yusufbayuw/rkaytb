<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Resources\PermissionResource;
use App\Services\PermissionSpreadsheetImporter;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Http\UploadedFile;

class ManagePermissions extends ManageRecords
{
    protected static string $resource = PermissionResource::class;

    public array $importErrors = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')->label('Impor permission')->visible(fn () => PermissionResource::canCreate())
                ->schema([
                    FileUpload::make('file')->label('Berkas CSV/XLS/XLSX')->required()->maxSize(10240)
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->storeFiles(false)->live(),
                    Toggle::make('has_header')->label('Baris pertama adalah judul kolom')->default(true)->live(),
                    Select::make('column')->label('Kolom nama permission')->required()->options(function (Get $get) {
                        $file = $get('file');
                        if (is_array($file)) $file = reset($file);
                        return $file instanceof UploadedFile ? app(PermissionSpreadsheetImporter::class)->headers($file, (bool) $get('has_header')) : [];
                    }),
                ])
                ->action(function (array $data) {
                    $result = app(PermissionSpreadsheetImporter::class)->import($data['file'], (int) $data['column'], (bool) $data['has_header']);
                    $this->importErrors = $result['errors'];
                    Notification::make()->title($result['created'].' permission dibuat; '.$result['duplicates'].' duplikat dilewati.')
                        ->body(collect($result['errors'])->take(20)->map(fn ($error) => 'Baris '.$error['row'].': '.$error['message'])->implode("\n"))
                        ->status($result['errors'] ? 'warning' : 'success')->persistent()->send();
                }),
            Actions\Action::make('download_import_errors')->label('Unduh kesalahan impor')
                ->visible(fn () => $this->importErrors !== [])
                ->action(fn () => response()->streamDownload(function () {
                    $stream = fopen('php://output', 'wb');
                    fputcsv($stream, ['Baris', 'Kesalahan'], ',', '"', '');
                    foreach ($this->importErrors as $error) fputcsv($stream, [$error['row'], $error['message']], ',', '"', '');
                    fclose($stream);
                }, 'kesalahan-impor.csv')),
            Actions\CreateAction::make(),
        ];
    }
}
