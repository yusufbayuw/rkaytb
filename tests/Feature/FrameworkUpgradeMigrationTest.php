<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FrameworkUpgradeMigrationTest extends TestCase
{
    public function test_legacy_schema_upgrade_full_platform_rollback_and_reapply_preserve_financial_rows(): void
    {
        $driver = DB::getDefaultConnection();
        $database = DB::connection()->getDatabaseName();
        if (!app()->environment('testing') || !(($driver === 'sqlite' && $database === ':memory:') ||
            ($driver === 'mysql' && $database === 'rka_test'))) {
            throw new \RuntimeException('Migration rehearsal only permits isolated SQLite memory or MySQL rka_test.');
        }
        $this->artisan('db:wipe', ['--force' => true])->assertSuccessful();
        $legacy = array_filter(glob(database_path('migrations/*.php')), fn ($file) => preg_match('/^(2014|2019|2023)_/', basename($file)));
        $this->artisan('migrate', ['--path' => array_values($legacy), '--realpath' => true, '--force' => true])->assertSuccessful();
        foreach (['rkas', 'rka_gajis'] as $table) {
            $row = ['uraian' => 'UPGRADE_SENTINEL', 'unit_nama' => 'TEST_ONLY', 'is_detail' => true, 'total' => '50.50'];
            if ($table === 'rkas') $row += ['is_parent' => false, 'volume' => '2', 'harga_satuan' => '25.25'];
            DB::table($table)->insert($row);
        }
        $before = $this->sentinels();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertSame($before, $this->sentinels());
        $count = count(glob(database_path('migrations/2026_*.php')));
        $this->assertSame($count, DB::table('migrations')->where('migration', 'like', '2026_%')->count());
        $this->artisan('migrate:rollback', ['--step' => $count, '--force' => true])->assertSuccessful();
        foreach (['rka_organizations', 'rka_cycles', 'rka_reconciliation_runs', 'rka_cycle_closings'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertSame($before, $this->sentinels());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('rka_organizations'));
        $this->assertSame($before, $this->sentinels());
    }

    private function sentinels(): array
    {
        return array_map(fn ($table) => (array) DB::table($table)->where('uraian', 'UPGRADE_SENTINEL')->first(), ['rkas', 'rka_gajis']);
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
        parent::tearDown();
    }
}
