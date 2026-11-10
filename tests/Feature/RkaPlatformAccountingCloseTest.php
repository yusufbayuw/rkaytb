<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{
    Organization, Membership, Unit, Cycle, BudgetLine, SharedPool,
    JournalBatch, JournalLine, ApprovalPolicy, ApprovalRequest, Book, BookTemplate, BookSection
};
use App\Services\RkaPlatform\{FinancialReconciliationService, FinancialClosingService, BookPublishingService};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RkaPlatformAccountingCloseTest extends TestCase
{
    use RefreshDatabase;

    private function actor(int $org, array $roles, ?int $unitId = null): User
    {
        $user = User::factory()->create();
        Membership::create([
            'organization_id' => $org, 'user_id' => $user->id,
            'roles' => $roles, 'unit_id' => $unitId, 'active' => true,
        ]);
        return $user;
    }

    private function fixture(bool $balancedInternal = true): array
    {
        $org = Organization::create(['name' => 'Sample Cooperative', 'slug' => 'sample-cooperative']);
        $a = Unit::create(['organization_id' => $org->id, 'code' => 'A', 'name' => 'Unit A']);
        $b = Unit::create(['organization_id' => $org->id, 'code' => 'B', 'name' => 'Unit B']);
        $cycle = Cycle::create([
            'organization_id' => $org->id, 'name' => 'Planning Year', 'year' => 2035,
            'starts_on' => '2035-01-01', 'ends_on' => '2035-12-31',
            'settings' => ['kpi_profile' => [
                'income_categories' => ['REV'], 'expense_categories' => ['COST'],
                'sharing_already_in_expense' => false,
            ]],
        ]);
        $author = $this->actor($org->id, ['finance']);
        $checker = $this->actor($org->id, ['finance']);
        $owner = $this->actor($org->id, ['owner']);
        $admin = $this->actor($org->id, ['admin']);
        $submitter = $this->actor($org->id, ['finance']);
        $approverOne = $this->actor($org->id, ['finance']);
        $approverTwo = $this->actor($org->id, ['owner']);
        BudgetLine::create([
            'organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'unit_id' => $a->id, 'category' => 'REV', 'name' => 'Income',
            'amount' => '500.00', 'status' => 'approved',
        ]);
        BudgetLine::create([
            'organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'unit_id' => $a->id, 'category' => 'COST', 'name' => 'Direct expense',
            'amount' => '200.00', 'status' => 'approved',
        ]);
        $pool = SharedPool::create([
            'organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'code' => 'POOL', 'name' => 'Central services',
            'amount' => '100.00', 'method' => 'equal', 'status' => 'approved',
            'allocations' => [(string)$b->id => '100.00'],
        ]);
        $batch = JournalBatch::create([
            'organization_id' => $org->id, 'source' => 'ledger',
            'fingerprint' => str_repeat('a', 64), 'row_count' => 6,
            'status' => 'verified', 'imported_by' => $author->id,
        ]);
        $source = [
            ['J1','CASH',500,0,$a->id,'source_expense',null],
            ['J1','REV_CODE',0,500,$a->id,'source_expense',null],
            ['J2','EXP_CODE',200,0,$a->id,'source_expense',null],
            ['J2','CASH',0,200,$a->id,'source_expense',null],
            ['J3','INT_EXP',$balancedInternal ? 100 : 80,0,$b->id,'internal_charge','CHG-1'],
            ['J3','INT_INC',0,100,$a->id,'internal_charge','CHG-1'],
        ];
        foreach ($source as $index => [$number,$account,$debit,$credit,$unit,$kind,$ref]) {
            JournalLine::create([
                'organization_id' => $org->id, 'batch_id' => $batch->id,
                'source' => 'ledger', 'external_line_id' => 'line-'.($index+1),
                'journal_no' => $number, 'posted_on' => '2035-04-01',
                'account_code' => $account, 'unit_id' => $unit,
                'debit' => $debit, 'credit' => $credit,
                'entry_kind' => $kind, 'posting_status' => 'posted',
                'related_reference' => $ref,
            ]);
        }
        $classifications = [
            'CASH' => 'non_pnl', 'REV_CODE' => 'income', 'EXP_CODE' => 'expense',
            'INT_EXP' => 'internal_expense', 'INT_INC' => 'internal_income',
        ];
        $recon = app(FinancialReconciliationService::class);
        foreach ($classifications as $account => $classification) {
            $mapping = $recon->createMapping($cycle, $author->id, 'ledger', $account, $classification);
            $recon->approveMapping($org->id, $mapping, $checker->id);
        }
        return compact('org','cycle','a','b','pool','batch','author','checker',
            'owner','admin','submitter','approverOne','approverTwo');
    }

    private function policies(array $data): void
    {
        $service = app(FinancialClosingService::class);
        $steps = [
            ['label' => 'Finance', 'roles' => ['finance']],
            ['label' => 'Governing board', 'roles' => ['owner']],
        ];
        $policy = $service->proposePolicy($data['org']->id, $data['owner']->id, 'cycle.close', $steps);
        $service->activatePolicy($policy, $data['admin']->id);
        $reopenPolicy = $service->proposePolicy($data['org']->id, $data['owner']->id, 'cycle.reopen', $steps, 'high');
        $service->activatePolicy($reopenPolicy, $data['admin']->id);
    }

    public function test_reconciliation_eliminates_only_paired_internal_charges_without_double_counting(): void
    {
        $data = $this->fixture();
        $cycle = $data['cycle'];
        $service = app(FinancialReconciliationService::class);
        $result = $service->calculate($cycle);
        $this->assertSame(0, $result['report']['blocker_count'], json_encode($result['report']['blockers']));
        $this->assertSame('600.00', $result['report']['consolidated']['gross_income']);
        $this->assertSame('300.00', $result['report']['consolidated']['gross_expense']);
        $this->assertSame('100.00', $result['report']['consolidated']['eliminated_internal_income']);
        $this->assertSame('100.00', $result['report']['consolidated']['eliminated_internal_expense']);
        $this->assertSame('500.00', $result['report']['consolidated']['income']);
        $this->assertSame('200.00', $result['report']['consolidated']['expense']);
        $this->assertSame('300.00', $result['report']['consolidated']['surplus']);
        $this->assertSame('-100.00', $result['report']['unit_managerial'][1]['plan_surplus']);
    }

    public function test_closing_requires_fresh_independent_reconciliation_and_two_approvers(): void
    {
        $f = $this->fixture();
        $recon = app(FinancialReconciliationService::class);
        $run = $recon->prepare($f['cycle'], $f['author']->id);
        try {
            $recon->verify($f['cycle'], $run, $f['author']->id);
            $this->fail('Self approval must be rejected.');
        } catch (AuthorizationException $expected) {
            $this->assertNotEmpty($expected->getMessage());
        }
        $recon->verify($f['cycle'], $run, $f['checker']->id);
        $this->policies($f);
        $close = app(FinancialClosingService::class);
        $req = $close->request($f['cycle'], $f['submitter']->id, 'cycle.close', 'Monthly and yearly closing approved for review');
        $close->decide($req, $f['approverOne']->id, true);
        $this->assertSame('pending', $req->fresh()->status);
        $this->assertSame('draft', $f['cycle']->fresh()->status);
        try {
            $close->decide($req, $f['approverOne']->id, true);
            $this->fail('The same reviewer cannot approve twice.');
        } catch (AuthorizationException $expected) {
            $this->assertNotEmpty($expected->getMessage());
        }
        $close->decide($req, $f['approverTwo']->id, true);
        $this->assertSame('approved', $req->fresh()->status);
        $this->assertSame('closed', $f['cycle']->fresh()->status);
        $this->assertSame(1, DB::table('rka_cycle_closings')->where('cycle_id', $f['cycle']->id)->count());
        $snapshot = DB::table('rka_cycle_closings')->where('cycle_id', $f['cycle']->id)->first();
        $this->assertSame('300.00', json_decode($snapshot->report, true)['consolidated']['surplus']);
    }

    public function test_unbalanced_or_unpaired_journals_block_signoff(): void
    {
        $f = $this->fixture(false);
        $service = app(FinancialReconciliationService::class);
        $result = $service->calculate($f['cycle']);
        $codes = array_column($result['report']['blockers'], 'code');
        $this->assertContains('UNBALANCED_JOURNAL', $codes);
        $this->assertContains('INTERNAL_TRANSFER_MISMATCH', $codes);
        $run = $service->prepare($f['cycle'], $f['author']->id);
        $this->expectException(ValidationException::class);
        $service->verify($f['cycle'], $run, $f['checker']->id);
    }

    public function test_reopening_keeps_previous_snapshot_immutable(): void
    {
        $f = $this->fixture();
        $recon = app(FinancialReconciliationService::class);
        $run = $recon->prepare($f['cycle'], $f['author']->id);
        $recon->verify($f['cycle'], $run, $f['checker']->id);
        $this->policies($f);
        $close = app(FinancialClosingService::class);
        $req = $close->request($f['cycle'], $f['submitter']->id, 'cycle.close', 'Close for the fiscal year');
        $close->decide($req, $f['approverOne']->id, true);
        $close->decide($req, $f['approverTwo']->id, true);
        $original = DB::table('rka_cycle_closings')->first();
        $reopenReq = $close->request($f['cycle']->fresh(), $f['owner']->id,
            'cycle.reopen', 'Documented correction of old budget after closing');
        $close->decide($reopenReq, $f['approverOne']->id, true);
        $close->decide($reopenReq, $f['approverTwo']->id, true);
        $this->assertSame('draft', $f['cycle']->fresh()->status);
        $this->assertSame($original->report_hash, DB::table('rka_cycle_closings')->first()->report_hash);
        $this->assertSame(1, DB::table('rka_cycle_closings')->count());
    }
    public function test_book_publication_is_bound_to_verified_close_and_automatic_blocks(): void
    {
        $f = $this->fixture();
        $recon = app(FinancialReconciliationService::class);
        $run = $recon->prepare($f['cycle'], $f['author']->id);
        $recon->verify($f['cycle'], $run, $f['checker']->id);
        $this->policies($f);
        $closer = app(FinancialClosingService::class);
        $req = $closer->request($f['cycle'], $f['submitter']->id, 'cycle.close',
            'Final finance and budget verification before book publication');
        $closer->decide($req, $f['approverOne']->id, true);
        $closer->decide($req, $f['approverTwo']->id, true);

        $template = BookTemplate::create([
            'organization_id' => $f['org']->id, 'name' => 'Flexible plan',
            'sections' => [
                ['code' => 'intro', 'title' => 'Overview', 'required' => true, 'block_type' => 'narrative'],
                ['code' => 'statement', 'title' => 'Consolidated Budget', 'required' => true, 'block_type' => 'consolidated'],
                ['code' => 'kpi', 'title' => 'Unit KPI', 'required' => true, 'block_type' => 'unit_kpi'],
            ],
        ]);
        $book = Book::create([
            'organization_id' => $f['org']->id, 'cycle_id' => $f['cycle']->id,
            'template_id' => $template->id, 'title' => 'Example annual plan',
        ]);
        $intro = BookSection::create([
            'book_id' => $book->id, 'code' => 'intro', 'title' => 'Overview',
            'block_type' => 'narrative', 'required' => true, 'status' => 'draft', 'position' => 0,
        ]);
        $statement = BookSection::create([
            'book_id' => $book->id, 'code' => 'statement', 'title' => 'Consolidated Budget',
            'block_type' => 'consolidated', 'required' => true, 'status' => 'review', 'position' => 1,
        ]);
        $kpi = BookSection::create([
            'book_id' => $book->id, 'code' => 'kpi', 'title' => 'Unit KPI',
            'block_type' => 'unit_kpi', 'required' => true, 'status' => 'review', 'position' => 2,
        ]);
        config()->set('rka_platform.enable_official_publication', true);
        $publisher = app(BookPublishingService::class);
        $publisher->saveSection($intro, $f['admin']->id, 'The 2035 financial plan.');
        $publisher->approveSection($intro->fresh(), $f['owner']->id);
        $publisher->approveSection($statement, $f['owner']->id);
        $publisher->approveSection($kpi, $f['owner']->id);
        $published = $publisher->publish($book, $f['owner']->id);
        $snapshot = $published->snapshot;
        $this->assertSame('published', $published->status);
        $this->assertSame('300.00', $snapshot['financial_report']['consolidated']['surplus']);
        $this->assertSame('consolidated', $snapshot['sections'][1]['block_type']);
        $this->assertSame('unit_kpi', $snapshot['sections'][2]['block_type']);
        $this->assertNotEmpty($snapshot['closing']['source_hash']);
        $this->assertSame($f['org']->fresh()->currency, $snapshot['currency']);
        $this->assertNotEmpty($snapshot['sha256']);
    }

}
