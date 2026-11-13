<?php

namespace Tests\Feature;

use App\Filament\Resources\RkaResource;
use App\Models\{RkaEntry, Unit, User};
use App\Services\RkaConsolidation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RkaPlatformLegacyReadSafetyTest extends TestCase
{
    use RefreshDatabase;
    public function test_reading_migrated_financial_values_never_rewrites_originals_or_active_records(): void
    {
        $this->withoutVite(); Filament::setCurrentPanel(Filament::getPanel('admin'));
        $unit = Unit::create(['nama' => 'Read safety']);
        $user = User::factory()->create(); $user->assignRole(Role::findOrCreate('superadmin', 'web')); $this->actingAs($user);
        DB::table('rkas')->insert(['uraian' => 'Historical mismatched amount', 'unit_nama' => (string) $unit->id,
            'is_parent' => false, 'volume' => '2', 'harga_satuan' => '3', 'total' => '99.00', 'januari' => '1.25',
            'created_at' => '2020-01-02 00:00:00', 'updated_at' => '2020-01-02 00:00:00']);
        app(RkaConsolidation::class)->import();
        $archive = DB::table('rkas')->get()->toJson(); $active = RkaEntry::all()->toJson();
        $writes = [];
        DB::listen(function ($q) use (&$writes) { if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $q->sql)) $writes[] = $q->sql; });
        $this->get(RkaResource::getUrl())->assertOk()->assertSee('99,00');
        $this->assertSame($archive, DB::table('rkas')->get()->toJson());
        $this->assertSame($active, RkaEntry::all()->toJson());
        $this->assertSame([], $writes);
    }
}
