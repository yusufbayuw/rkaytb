<?php

namespace Tests\Feature;

use App\Models\Rka;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Console\Commands\AuditLegacyRka;
use Symfony\Component\Console\Input\ArrayInput;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class RkaPlatformLegacyAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private function runAudit(array $options): array
    {
        $command = app(AuditLegacyRka::class);
        $input = new ArrayInput($options, $command->getDefinition());
        $output = new BufferedOutput();
        $exit = $command->run($input, $output);
        $raw = trim($output->fetch());
        $this->assertNotSame('', $raw, 'Audit command returned no output, exit code '.$exit);
        return [$exit, json_decode($raw, true, 512, JSON_THROW_ON_ERROR)];
    }

    public function test_historical_audit_detects_inconsistent_leaf_and_parent_without_writing(): void
    {
        $root = Rka::create([
            'uraian'=>'Generic parent','unit_nama'=>'A','is_parent'=>true,
        ]);
        $child = Rka::create([
            'uraian'=>'Generic item','unit_nama'=>'A','is_parent'=>false,
            'is_detail'=>true,'parent_id'=>$root->id,
            'volume'=>'2','harga_satuan'=>'10.00','januari'=>'2.00',
        ]);
        // Deliberately simulate historical corruption using raw SQL.
        DB::table('rkas')->where('id',$child->id)->update(['total'=>'777.00']);
        $before = DB::table('rkas')->orderBy('id')->get()->toArray();
        $writeQueries = [];
        DB::listen(function ($query) use (&$writeQueries) {
            if (preg_match('/^\s*(UPDATE|INSERT|DELETE|ALTER|DROP|CREATE|TRUNCATE)\b/i',
                $query->sql)) $writeQueries[] = $query->sql;
        });

        [$exit, $report] = $this->runAudit(['--json'=>true,'--strict'=>true]);
        $this->assertSame(1,$exit);
        $this->assertSame('READ_ONLY',$report['mode']);
        $this->assertGreaterThan(0,$report['issue_count']);
        $this->assertArrayHasKey('rkas:stale_leaf_total',$report['issues_by_type']);
        $this->assertSame([], $writeQueries);
        $this->assertEquals($before, DB::table('rkas')->orderBy('id')->get()->toArray());
    }

    public function test_anomaly_scan_reports_cross_unit_parent_and_never_fixes_it(): void
    {
        $root = Rka::create([
            'uraian'=>'Generic parent','unit_nama'=>'A','is_parent'=>true,
        ]);
        $child = Rka::create([
            'uraian'=>'Generic child','unit_nama'=>'A','is_parent'=>false,
            'parent_id'=>$root->id,'volume'=>'1','harga_satuan'=>'3.50',
        ]);
        DB::table('rkas')->where('id',$child->id)->update(['unit_nama'=>'B']);
        [$result, $report] = $this->runAudit(['--json'=>true]);
        $this->assertSame(0,$result);
        $this->assertArrayHasKey('rkas:cross_unit_parent',$report['issues_by_type']);
        $this->assertSame('B',DB::table('rkas')->where('id',$child->id)->value('unit_nama'));
    }

    public function test_row_limit_is_reported_as_incomplete_not_a_false_clean_bill(): void
    {
        Rka::create(['uraian'=>'First','unit_nama'=>'A','is_parent'=>true]);
        Rka::create(['uraian'=>'Second','unit_nama'=>'A','is_parent'=>true]);
        [$exit, $report] = $this->runAudit([
            '--json'=>true,'--strict'=>true,'--limit'=>'1',
        ]);
        $this->assertSame(1,$exit);
        $this->assertArrayHasKey('rkas:incomplete_scan',$report['issues_by_type']);
        $this->assertTrue($report['table_summary']['rkas']['truncated']);
    }
}
