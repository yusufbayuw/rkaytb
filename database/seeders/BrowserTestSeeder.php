<?php

namespace Database\Seeders;

use App\Models\{Akun, Rka, RkaConsumable, RkaGaji, RkaMaintenance, RkaOperasional, RkaPendapatan, Setting, Unit, User};
use App\Models\RkaPlatform\{BudgetLine, Cycle, Membership, Organization, Unit as OrganizationUnit};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class BrowserTestSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('testing', 'browser-testing') || DB::getDefaultConnection() !== 'sqlite' ||
            basename(DB::connection()->getDatabaseName()) !== 'rka_browser.sqlite') {
            throw new \RuntimeException('Browser fixtures require the isolated rka_browser.sqlite test database.');
        }
        DB::transaction(function () {
        Setting::initializeDefaults();
        \App\Support\StandardPermissions::sync();
        \Spatie\Permission\Models\Permission::findOrCreate('Keep:Browser', 'web');
        file_put_contents(storage_path('logs/browser-plugin-fixture.log'), '[2026-11-12 10:00:00] browser-testing.ERROR: Browser plugin log'.PHP_EOL);
        $password = Hash::make('Browser-test-password-2026!');
        $a = Unit::create(['nama' => 'Unit Uji A']);
        $b = Unit::create(['nama' => 'Unit Uji B']);
        $account = Akun::create(['nama' => 'Akun Uji']);
        $admin = User::create(['name' => 'Administrator Uji', 'email' => 'admin@example.test', 'password' => $password]);
        $admin->assignRole(Role::findOrCreate('superadmin', 'web'));
        $finance = User::create(['name' => 'Keuangan Uji', 'email' => 'finance@example.test', 'password' => $password, 'unit_id' => $a->id]);
        $finance->assignRole(Role::findOrCreate('admin-keuangan', 'web'));
        User::create(['name' => 'Pengguna Unit A', 'email' => 'unit-a@example.test', 'password' => $password, 'unit_id' => $a->id]);
        User::create(['name' => 'Pengguna Unit B', 'email' => 'unit-b@example.test', 'password' => $password, 'unit_id' => $b->id]);
        $member = User::create(['name' => 'Anggota Organisasi A', 'email' => 'member@example.test', 'password' => $password]);
        $reviewer = User::create(['name' => 'Reviewer Uji', 'email' => 'reviewer@example.test', 'password' => $password]);
        foreach ([Rka::class, RkaMaintenance::class, RkaConsumable::class, RkaOperasional::class, RkaPendapatan::class] as $model) {
            foreach ([$a, $b] as $unit) {
                $root = $model::create(['uraian' => 'Anggaran '.$unit->nama, 'unit_nama' => (string) $unit->id, 'is_parent' => true]);
                $model::create(['uraian' => 'Rincian '.$unit->nama, 'unit_nama' => (string) $unit->id, 'parent_id' => $root->id,
                    'is_parent' => false, 'is_detail' => true, 'volume' => 2, 'harga_satuan' => 1250.5, 'januari' => 2501,
                    'nomor_akun' => (string) $account->id]);
            }
        }
        foreach ([$a, $b] as $unit) RkaGaji::create(['uraian' => 'Gaji '.$unit->nama, 'unit_nama' => (string) $unit->id,
            'is_detail' => true, 'total' => 2501, 'januari' => 2501, 'nomor_akun' => (string) $account->id]);
        $root = Rka::where('unit_nama', $a->id)->where('is_parent', true)->firstOrFail();
        $rows = [];
        for ($i = 0; $i < 500; $i++) $rows[] = ['uraian' => 'Pagination '.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'unit_nama' => (string) $a->id, 'parent_id' => $root->id, 'is_parent' => false, 'is_detail' => true,
            'volume' => 0, 'harga_satuan' => 0, 'total' => 0, 'nomor_akun' => (string) $account->id,
            'created_at' => now(), 'updated_at' => now()];
        // Zero-value fixture rows preserve the root's financial aggregate.
        DB::table('rkas')->insert($rows);
        app(\App\Services\RkaConsolidation::class)->import();
        foreach (['A', 'B'] as $suffix) {
            $org = Organization::create(['name' => 'Organisasi Uji '.$suffix, 'brand_name' => 'Organisasi Uji '.$suffix,
                'slug' => 'organization-'.$suffix, 'currency' => 'IDR', 'locale' => 'id', 'timezone' => 'Asia/Jakarta']);
            Membership::create(['organization_id' => $org->id, 'user_id' => $admin->id, 'roles' => ['owner'], 'active' => true]);
            if ($suffix === 'A') {
                Membership::create(['organization_id' => $org->id, 'user_id' => $member->id, 'roles' => ['owner'], 'active' => true]);
                Membership::create(['organization_id' => $org->id, 'user_id' => $reviewer->id, 'roles' => ['finance', 'reviewer'], 'active' => true]);
            }
            $unit = OrganizationUnit::create(['organization_id' => $org->id, 'code' => 'UNIT-'.$suffix, 'name' => 'Unit Organisasi '.$suffix]);
            $cycle = Cycle::create(['organization_id' => $org->id, 'name' => 'Siklus Uji '.$suffix, 'year' => 2026,
                'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'settings' => ['sharing_unit_approval_policy' => 'impact_based']]);
            BudgetLine::create(['organization_id' => $org->id, 'cycle_id' => $cycle->id, 'unit_id' => $unit->id,
                'name' => 'Anggaran Platform '.$suffix, 'account_code' => 'EXPENSE', 'category' => 'expense', 'amount' => 2501]);
        }
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $routes = [];
        foreach (\Filament\Facades\Filament::getPanel('admin')->getResources() as $resource) {
            $routes[class_basename($resource)] = parse_url($resource::getUrl('index', panel: 'admin'), PHP_URL_PATH);
        }
        file_put_contents(base_path('.tools/browser-fixtures.json'), json_encode(['resources' => $routes], JSON_PRETTY_PRINT));
        });
    }
}
