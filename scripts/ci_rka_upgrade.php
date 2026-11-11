<?php
/**
 * Isolated CI-only migration rehearsal. Never run on a real organization DB.
 * CLI stages: baseline | expanded | rolledback
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = DB::getDefaultConnection();
$database = (string) DB::connection()->getDatabaseName();
$allowed = getenv('APP_ENV') === 'testing'
    && (($connection === 'mysql' && $database === 'rka_test')
        || ($connection === 'sqlite' && basename($database) === 'ci_upgrade_test.sqlite'));
if (!$allowed) {
    fwrite(STDERR, "REFUSED: this program is for isolated test databases only.\n");
    exit(2);
}

$stage = $argv[1] ?? '';
$legacyTables = ['rkas','rka_gajis','rka_maintenances','rka_consumables',
    'rka_operasionals','rka_pendapatans','users','units'];
$newTables = ['rka_organizations','rka_cycles','rka_reconciliation_runs',
    'rka_cycle_closings','rka_shared_pool_impact_assessments','rka_close_exceptions'];
$marker = 'CI_LEGACY_SENTINEL_DO_NOT_USE';

foreach ($legacyTables as $table) {
    if (!Schema::hasTable($table)) {
        throw new RuntimeException("Missing expected legacy table: {$table}");
    }
}

if ($stage === 'baseline') {
    DB::table('rkas')->insert([
        'uraian' => $marker, 'is_parent' => false,
        'is_detail' => true, 'unit_nama' => 'CI_ONLY',
        'volume' => '2', 'harga_satuan' => '25.25', 'total' => '50.50',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // The existing payroll table is deliberately flat (no is_parent/volume/price).
    DB::table('rka_gajis')->insert([
        'uraian' => $marker, 'is_detail' => true,
        'unit_nama' => 'CI_ONLY', 'total' => '50.50',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    echo "Legacy marker inserted directly in isolated test DB.\n";
    exit(0);
}

if (!in_array($stage, ['expanded','rolledback'], true)) {
    fwrite(STDERR, "Expected stage: baseline | expanded | rolledback.\n");
    exit(2);
}

foreach (['rkas','rka_gajis'] as $table) {
    $rows = DB::table($table)->where('uraian', $marker)->get();
    if ($rows->count() !== 1 || $rows[0]->total !== '50.50' ||
        $rows[0]->unit_nama !== 'CI_ONLY') {
        throw new RuntimeException("Legacy financial marker was altered: {$table}");
    }
}

if ($stage === 'expanded') {
    foreach ($newTables as $table) {
        if (!Schema::hasTable($table)) {
            throw new RuntimeException("Expanded table missing: {$table}");
        }
    }
    $count = DB::table('migrations')->where('migration', 'like', '2026_%')->count();
    if ($count !== 9) {
        throw new RuntimeException("Expected 9 additive migrations, got {$count}.");
    }
    echo "Expanded schema: 9 additive migrations; legacy amounts unchanged.\n";
} else {
    foreach ($newTables as $table) {
        if (Schema::hasTable($table)) {
            throw new RuntimeException("Rolled-back table still exists: {$table}");
        }
    }
    if (DB::table('migrations')->where('migration', 'like', '2026_%')->exists()) {
        throw new RuntimeException('New migrations still registered after rollback.');
    }
    echo "Additive rollback complete; legacy sentinel preserved.\n";
}
