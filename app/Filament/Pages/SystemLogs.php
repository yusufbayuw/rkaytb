<?php

namespace App\Filament\Pages;

use AchyutN\FilamentLogViewer\Contracts\LogProvider;

class SystemLogs extends \AchyutN\FilamentLogViewer\LogTable
{
    use \App\Support\RequiresSystemAccess;
    public function getHeading(): string { return 'Log Teknis'; }
    protected function getReadMailAction(\AchyutN\FilamentLogViewer\Contracts\Schema\LogEntrySchemaInterface $entrySchema): \Filament\Actions\Action
    {
        // Log contents are untrusted; render mail as escaped text, never active HTML.
        return \Filament\Actions\Action::make('read')->label('Isi email')->icon('heroicon-o-envelope')
            ->visible(fn (array $record) => $record['log_level'] === \AchyutN\FilamentLogViewer\Enums\LogLevel::MAIL)
            ->schema([\Filament\Infolists\Components\TextEntry::make('mail_text')->label('Pesan')
                ->state(fn (array $record) => $record['mail']['plain'] ?? '')])
            ->modalSubmitAction(false)->slideOver();
    }
    public function getSubheading(): string
    {
        $files = app(LogProvider::class)->skippedFiles();
        return $files ? count($files).' berkas dilewati (lebih dari 2 MB atau lokasi tidak diizinkan): '.implode(', ', $files)
            : 'Hanya-baca. Berkas maksimal 2 MB dari penyimpanan log aplikasi.';
    }
    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\Action::make('refresh')->label('Muat ulang')->action(function () {
            \App\Support\SystemAccess::authorize();
            app(LogProvider::class)->getRows(true);
            $this->resetTable();
        })];
    }
}
