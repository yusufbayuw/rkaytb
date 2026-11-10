<?php

namespace Tests\Feature;

use App\Models\RkaPlatform\{Organization, Unit, Cycle, BudgetLine, SharedPool};
use App\Services\RkaPlatform\KpiBudgetPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RkaPlatformKpiPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_white_label_kpi_is_preview_only_and_does_not_double_count_shared_cost(): void
    {
        $org = Organization::create(['name' => 'Generic Company', 'slug' => 'generic-company']);
        $unit = Unit::create(['organization_id' => $org->id, 'code' => 'U-1', 'name' => 'Department One']);
        $cycle = Cycle::create([
            'organization_id' => $org->id, 'name' => 'Annual Budget', 'year' => 2033,
            'starts_on' => '2033-01-01', 'ends_on' => '2033-12-31',
        ]);
        BudgetLine::create([
            'organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'unit_id' => $unit->id, 'category' => 'REV_CUSTOM', 'name' => 'Income',
            'amount' => '101.50', 'status' => 'approved',
        ]);
        BudgetLine::create([
            'organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'unit_id' => $unit->id, 'category' => 'COST_CUSTOM', 'name' => 'Direct Cost',
            'amount' => '41.25', 'status' => 'approved',
        ]);
        BudgetLine::create([
            'organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'unit_id' => $unit->id, 'category' => 'COST_CUSTOM', 'name' => 'Unapproved',
            'amount' => '900.00', 'status' => 'draft',
        ]);
        SharedPool::create([
            'organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'code' => 'POOL-1', 'name' => 'Shared Office Cost',
            'amount' => '500.00', 'method' => 'equal',
        ]);

        $result = app(KpiBudgetPreviewService::class)->preview($cycle, [
            'income_categories' => ['REV_CUSTOM'],
            'expense_categories' => ['COST_CUSTOM'],
        ]);

        $this->assertSame('101.50', $result['income']);
        $this->assertSame('41.25', $result['cost']);
        $this->assertSame('60.25', $result['surplus_before_sharing']);
        $this->assertSame('60.25', $result['rows'][0]['surplus_before_sharing']);
        $this->assertSame('PREVIEW_ONLY', $result['status']);
        $this->assertSame(1, $result['pending_lines']);
        $this->assertSame(1, $result['shared_pools']);
    }

    public function test_profile_disallows_overlapping_income_and_expense_categories(): void
    {
        $this->expectException(ValidationException::class);
        app(KpiBudgetPreviewService::class)->validateProfile([
            'income_categories' => ['CATEGORY-A'],
            'expense_categories' => ['CATEGORY-A'],
        ]);
    }
}
