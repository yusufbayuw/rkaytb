<?php

namespace Tests\Feature;

use App\Models\{
    RkaMaintenance, RkaConsumable, RkaOperasional, RkaPendapatan
};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RkaPlatformLegacyAllResourcesTest extends TestCase
{
    use RefreshDatabase;

    private const MODELS = [
        RkaMaintenance::class, RkaConsumable::class,
        RkaOperasional::class, RkaPendapatan::class,
    ];

    public function test_all_legacy_financial_categories_recalculate_monthly_and_annual_parents_on_edit(): void
    {
        foreach (self::MODELS as $class) {
            $root = $class::create([
                'uraian'=>'Main parent','is_parent'=>true,'is_detail'=>false,'unit_nama'=>'1',
            ]);
            $middle = $class::create([
                'uraian'=>'Sub parent','is_parent'=>true,'is_detail'=>false,
                'unit_nama'=>'1','parent_id'=>$root->id,
            ]);
            $first = $class::create([
                'uraian'=>'Asset A','is_parent'=>false,'is_detail'=>true,
                'unit_nama'=>'1','parent_id'=>$middle->id,
                'volume'=>'2','harga_satuan'=>'20.25','januari'=>'9.00',
            ]);
            $second = $class::create([
                'uraian'=>'Asset B','is_parent'=>false,'is_detail'=>true,
                'unit_nama'=>'1','parent_id'=>$middle->id,
                'volume'=>'3','harga_satuan'=>'10.00','januari'=>'1.50',
            ]);
            $this->assertSame('70.50', $root->fresh()->total, $class);
            $this->assertSame('10.50', $root->fresh()->januari, $class);
            $first->update(['volume'=>'4','januari'=>'15.00']);
            $this->assertSame('111.00', $root->fresh()->total, $class);
            $this->assertSame('16.50', $root->fresh()->januari, $class);
            $this->assertSame('111.00', $middle->fresh()->total, $class);
            $second->delete();
            $this->assertSame('81.00', $root->fresh()->total, $class);
            $this->assertSame('15.00', $root->fresh()->januari, $class);
        }
    }

    public function test_cross_unit_parent_assignment_is_rejected_for_all_legacy_categories(): void
    {
        foreach (self::MODELS as $class) {
            $a = $class::create([
                'uraian'=>'Root A','is_parent'=>true,'unit_nama'=>'1',
            ]);
            $b = $class::create([
                'uraian'=>'Root B','is_parent'=>true,'unit_nama'=>'2',
            ]);
            $leaf = $class::create([
                'uraian'=>'Item','is_parent'=>false,'unit_nama'=>'1',
                'volume'=>'1','harga_satuan'=>'50.00','parent_id'=>$a->id,
            ]);
            try {
                $leaf->update(['parent_id'=>$b->id]);
                $this->fail($class.' allowed cross-unit reparenting');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('parent_id',$e->errors());
            }
            $this->assertSame($a->id,$leaf->fresh()->parent_id);
            $this->assertSame('50.00',$a->fresh()->total);
            $this->assertSame('0.00',$b->fresh()->total);
        }
    }

    public function test_all_legacy_financial_table_formatters_are_read_only(): void
    {
        foreach (['RkaMaintenanceResource','RkaConsumableResource',
            'RkaOperasionalResource','RkaPendapatanResource','RkaResource'] as $resource) {
            $path = app_path('Filament/Resources/'.$resource.'.php');
            $code = file_get_contents($path);
            $start = strpos($code, 'public static function table(');
            $end = strpos($code, '->actions([', $start ?: 0);
            $this->assertNotFalse($start, $resource);
            $this->assertNotFalse($end, $resource);
            $rendering = substr($code, $start, $end - $start);
            $this->assertStringNotContainsString('->save(', $rendering,
                $resource.' may not persist data in table cell rendering');
        }
    }
}
