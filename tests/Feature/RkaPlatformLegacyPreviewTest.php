<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RkaPlatform\LegacyBudgetPreviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RkaPlatformLegacyPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_budget_preview_is_read_only_and_reserved_for_global_superadmin(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $ordinary = User::factory()->create();

        $unitId = DB::table('units')->insertGetId([
            'nama' => 'Generic Unit', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rkas')->insert([
            'uraian' => 'Unmapped old record', 'unit_nama' => (string)$unitId,
            'is_parent' => false, 'total' => '123.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $before = DB::table('rkas')->first();

        $preview = app(LegacyBudgetPreviewService::class)->preview(
            $admin->id, 'investment', $unitId
        );
        $this->assertSame('Generic Unit', $preview['unit']);
        $this->assertSame(1, $preview['count']);
        $this->assertSame('123.00', $preview['sample'][0]['total_as_stored']);
        $this->assertSame((array)$before, (array)DB::table('rkas')->first());

        $this->expectException(AuthorizationException::class);
        app(LegacyBudgetPreviewService::class)->preview(
            $ordinary->id, 'investment', $unitId
        );
    }
}
