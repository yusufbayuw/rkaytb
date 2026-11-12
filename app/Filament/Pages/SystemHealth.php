<?php

namespace App\Filament\Pages;

use Filament\Panel;
use Spatie\Health\ResultStores\ResultStore;

class SystemHealth extends \ShuvroRoy\FilamentSpatieLaravelHealth\Pages\HealthCheckResults
{
    use \App\Support\RequiresSystemAccess;
    public static function getSlug(?Panel $panel = null): string { return 'system/health'; }
    public function getHeading(): string { return 'Kesehatan Aplikasi'; }
    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\Action::make('refresh')->label('Periksa sekarang')->action('refresh')];
    }
    public function getSubheading(): string
    {
        $finished = app(ResultStore::class)->latestResults()?->finishedAt;
        if (!$finished) return 'Belum diperiksa. Jalankan pemeriksaan atau aktifkan scheduler.';
        return $finished->lt(now()->subMinutes(15))
            ? 'Hasil kedaluwarsa: pemeriksaan terakhir lebih dari 15 menit. Periksa scheduler.'
            : 'Pemeriksaan terakhir: '.$finished->timezone(config('rka_platform.timezone'))->format('d/m/Y H:i:s');
    }
    public function refresh(): void
    {
        \App\Support\SystemAccess::authorize();
        parent::refresh();
    }
}
