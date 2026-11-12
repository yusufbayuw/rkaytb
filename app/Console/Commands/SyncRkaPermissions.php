<?php

namespace App\Console\Commands;

use App\Support\StandardPermissions;
use Illuminate\Console\Command;

class SyncRkaPermissions extends Command
{
    protected $signature = 'rka:sync-permissions {--dry-run : Tampilkan permission yang belum tersedia}';
    protected $description = 'Lengkapi permission standar tanpa mengubah assignment atau permission existing';

    public function handle(): int
    {
        $missing = StandardPermissions::sync((bool) $this->option('dry-run'));
        foreach ($missing as $name) $this->line($name);
        $this->info(count($missing).' permission '.($this->option('dry-run') ? 'belum tersedia.' : 'ditambahkan.'));
        return self::SUCCESS;
    }
}
