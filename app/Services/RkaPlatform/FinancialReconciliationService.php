<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{
    BudgetLine, Cycle, JournalBatch, JournalLine, SharedPool, Unit
};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialReconciliationService
{
    private const CLASSES = ['income','expense','internal_income','internal_expense','clearing','non_pnl'];

    public function __construct(
        private TenantAccess $access,
        private AuditTrail $audit,
        private SharedCostAllocator $money,
        private KpiBudgetPreviewService $budgetPreview
    ) {}

    private function assertMutable(Cycle $cycle): void
    {
        if ($cycle->status !== 'draft') {
            throw ValidationException::withMessages(['cycle' => 'Siklus sudah ditutup; buka melalui persetujuan revisi terlebih dahulu.']);
        }
    }

    public function createMapping(Cycle $cycle, int $actor, string $source, string $account,
        string $classification, ?int $unitId = null, ?string $from = null, ?string $until = null): int
    {
        $this->access->requireRole($cycle->organization_id, $actor, ['owner','admin','finance']);
        $this->assertMutable($cycle);
        if (!preg_match('/^[a-zA-Z0-9_.-]{2,60}$/', $source) || !$account ||
            strlen($account) > 255 || !in_array($classification, self::CLASSES, true)) {
            throw ValidationException::withMessages(['mapping' => 'Sumber, akun, atau klasifikasi mapping tidak valid.']);
        }
        $from ??= $cycle->starts_on->format('Y-m-d');
        $until ??= $cycle->ends_on->format('Y-m-d');
        if (!strtotime($from) || !strtotime($until) ||
            $from < $cycle->starts_on->format('Y-m-d') ||
            $until > $cycle->ends_on->format('Y-m-d') || $from > $until) {
            throw ValidationException::withMessages(['mapping' => 'Tanggal mapping harus berada pada periode siklus.']);
        }
        if ($unitId !== null && !Unit::where('organization_id', $cycle->organization_id)->whereKey($unitId)->exists()) {
            throw ValidationException::withMessages(['mapping' => 'Unit mapping tidak termasuk organisasi.']);
        }
        return DB::transaction(function () use ($cycle, $actor, $source, $account, $classification, $unitId, $from, $until) {
            $locked = Cycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $this->assertMutable($locked);
            $conflict = DB::table('rka_journal_account_mappings')
                ->where('organization_id', $cycle->organization_id)->where('cycle_id', $cycle->id)
                ->where('source', $source)->where('account_code', $account)
                ->where('active', true)->where('valid_from', '<=', $until)
                ->where('valid_to', '>=', $from)->exists();
            if ($conflict) throw ValidationException::withMessages(['mapping' => 'Mapping aktif bertabrakan pada akun/periode yang sama.']);
            $id = DB::table('rka_journal_account_mappings')->insertGetId([
                'organization_id' => $cycle->organization_id, 'cycle_id' => $cycle->id,
                'source' => $source, 'account_code' => $account, 'classification' => $classification,
                'unit_id' => $unitId, 'valid_from' => $from, 'valid_to' => $until,
                'created_by' => $actor, 'active' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->record($cycle->organization_id, $actor, 'journal_mapping.drafted', 'journal_mapping', $id);
            return $id;
        });
    }

    public function approveMapping(int $organizationId, int $mappingId, int $reviewer): void
    {
        $this->access->requireRole($organizationId, $reviewer, ['owner','admin','finance']);
        DB::transaction(function () use ($organizationId, $mappingId, $reviewer) {
            $mapping = DB::table('rka_journal_account_mappings')
                ->where('organization_id', $organizationId)->where('id', $mappingId)
                ->lockForUpdate()->first();
            if (!$mapping) abort(404);
            $cycle = Cycle::where('organization_id', $organizationId)->whereKey($mapping->cycle_id)
                ->lockForUpdate()->firstOrFail();
            $this->assertMutable($cycle);
            if ($mapping->created_by === $reviewer) {
                throw new AuthorizationException('Pembuat mapping tidak boleh menyetujui mapping sendiri.');
            }
            if ($mapping->active) throw ValidationException::withMessages(['mapping' => 'Mapping sudah aktif.']);
            $overlap = DB::table('rka_journal_account_mappings')
                ->where('organization_id', $organizationId)->where('cycle_id', $mapping->cycle_id)
                ->where('source', $mapping->source)->where('account_code', $mapping->account_code)
                ->where('active', true)->where('id', '!=', $mappingId)
                ->where('valid_from', '<=', $mapping->valid_to)
                ->where('valid_to', '>=', $mapping->valid_from)->exists();
            if ($overlap) throw ValidationException::withMessages(['mapping' => 'Mapping aktif bertabrakan dengan tanggal yang dipilih.']);
            DB::table('rka_journal_account_mappings')->where('id', $mappingId)->update([
                'active' => true, 'approved_by' => $reviewer, 'approved_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->record($organizationId, $reviewer, 'journal_mapping.approved', 'journal_mapping', $mappingId);
        });
    }

    public function verifyBatch(JournalBatch $batch, int $reviewer): void
    {
        $this->access->requireRole($batch->organization_id, $reviewer, ['owner','admin','finance']);
        DB::transaction(function () use ($batch, $reviewer) {
            $locked = JournalBatch::where('organization_id', $batch->organization_id)
                ->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'staged') throw ValidationException::withMessages(['journal' => 'Batch tidak berada dalam staging.']);
            if ((int)$locked->imported_by === $reviewer) {
                throw new AuthorizationException('Pengimpor tidak boleh memverifikasi datanya sendiri.');
            }
            $lines = JournalLine::where('batch_id', $locked->id)->get();
            foreach ($lines as $line) {
                if (Cycle::where('organization_id', $locked->organization_id)->where('status','closed')
                    ->whereDate('starts_on','<=',$line->posted_on)->whereDate('ends_on','>=',$line->posted_on)->exists()) {
                    throw ValidationException::withMessages(['journal' => 'Ada periode yang sudah ditutup.']);
                }
            }
            if (!$lines->count()) throw ValidationException::withMessages(['journal' => 'Batch tidak memiliki jurnal.']);
            $meta = $locked->metadata ?? [];
            $meta['verified_by'] = $reviewer;
            $meta['verified_at'] = now()->toIso8601String();
            // Verifying a batch only attests its source; reconciled journal balance is checked at cycle level.
            $locked->update(['status' => 'verified', 'metadata' => $meta]);
            $this->audit->record($locked->organization_id, $reviewer, 'journal_batch.verified', 'journal_batch', $locked->id);
        });
    }

    public function calculate(Cycle $cycle): array
    {
        $org = (int)$cycle->organization_id;
        $from = $cycle->starts_on->format('Y-m-d'); $until = $cycle->ends_on->format('Y-m-d');
        $journal = JournalLine::where('organization_id', $org)->whereBetween('posted_on', [$from,$until])
            ->orderBy('id')->get();
        $batchIds = $journal->pluck('batch_id')->unique()->all();
        $batches = JournalBatch::where('organization_id', $org)
            ->whereIn('id', $batchIds)->orderBy('id')->get();
        $maps = DB::table('rka_journal_account_mappings')->where('organization_id', $org)
            ->where('cycle_id', $cycle->id)->orderBy('id')->get();
        $budgets = BudgetLine::where('organization_id', $org)->where('cycle_id', $cycle->id)->orderBy('id')->get();
        $pools = SharedPool::where('organization_id', $org)->where('cycle_id', $cycle->id)->orderBy('id')->get();
        $disputes = DB::table('rka_sharing_disputes')->where('organization_id', $org)
            ->where('cycle_id', $cycle->id)->orderBy('id')->get();
        $units = Unit::where('organization_id', $org)->orderBy('id')->get();
        $profile = ($cycle->settings ?? [])['kpi_profile'] ?? null;
        $sourceHash = hash('sha256', json_encode([
            'cycle' => $cycle->only(['id','organization_id','starts_on','ends_on','settings']),
            'journal' => $journal->toArray(), 'batches' => $batches->toArray(),
            'mappings' => $maps->toArray(), 'budgets' => $budgets->toArray(),
            'pools' => $pools->toArray(), 'disputes' => $disputes->toArray(),
            'units' => $units->toArray(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $errors = []; $warnings = [];
        $block = static function (string $code, string $ref = '') use (&$errors): void {
            $errors[] = ['code' => $code, 'reference' => $ref];
        };
        $money = fn ($number) => $this->money->toMinor((string)$number);
        $fmt = fn ($minor) => $this->money->fromMinor((int)$minor);
        $unitNames = $units->pluck('name','id')->all();
        $perUnit = [];
        foreach ($unitNames as $unitId => $name) {
            $perUnit[$unitId] = $this->emptyUnit($name);
        }
        $perUnit['unassigned'] = $this->emptyUnit('Tanpa unit');
        $groupBalances = []; $internal = []; $clearing = [];
        $grossIncome = 0; $grossExpense = 0; $internalIncome = 0; $internalExpense = 0; $postedCount = 0;

        foreach ($journal as $line) {
            if ($line->posting_status !== 'posted') continue;
            $postedCount++;
            $groupKey = $line->source.'|'.$line->journal_no.'|'.$line->posted_on->format('Y-m-d');
            $debit = $money($line->debit); $credit = $money($line->credit);
            $groupBalances[$groupKey] = ($groupBalances[$groupKey] ?? 0) + $debit - $credit;
            if (($batches->firstWhere('id', $line->batch_id)?->status) !== 'verified') {
                $block('UNVERIFIED_SOURCE_BATCH', (string)$line->batch_id);
            }
            $date = $line->posted_on->format('Y-m-d');
            $applicable = $maps->filter(fn ($m) => $m->active && $m->source === $line->source &&
                $m->account_code === $line->account_code && $m->valid_from <= $date && $m->valid_to >= $date);
            if ($applicable->count() !== 1) {
                $block($applicable->isEmpty() ? 'UNMAPPED_ACCOUNT' : 'AMBIGUOUS_MAPPING',
                    $line->source.'|'.$line->account_code.'|'.$line->external_line_id);
                continue;
            }
            $map = $applicable->first();
            if ($map->unit_id !== null && $line->unit_id !== null && (int)$map->unit_id !== (int)$line->unit_id) {
                $block('UNIT_MAPPING_CONFLICT', (string)$line->id);
                continue;
            }
            $unitId = $line->unit_id ?? $map->unit_id;
            $key = $unitId ?? 'unassigned';
            if (!isset($perUnit[$key])) { $block('UNKNOWN_UNIT', (string)$line->id); continue; }
            $type = $map->classification;
            if ($line->entry_kind === 'clearing' && $type !== 'clearing') $block('CLEARING_KIND_MISMATCH', (string)$line->id);
            if ($line->entry_kind === 'internal_charge' && !in_array($type, ['internal_income','internal_expense'], true)) {
                $block('INTERNAL_KIND_MISMATCH', (string)$line->id);
            }
            if ($line->entry_kind === 'reversal' && !$line->related_reference) {
                $block('MISSING_REVERSAL_REFERENCE', (string)$line->id);
            }
            if ($type === 'clearing') {
                $ref = $line->source.'|'.$line->account_code;
                $clearing[$ref] = ($clearing[$ref] ?? 0) + $debit - $credit;
                continue;
            }
            if ($type === 'non_pnl') continue;
            $value = in_array($type, ['income','internal_income'], true) ? $credit - $debit : $debit - $credit;
            $perUnit[$key][$type] += $value;
            if (str_contains($type, 'income')) $grossIncome += $value;
            else $grossExpense += $value;
            if (str_starts_with($type, 'internal_')) {
                if (!$line->related_reference) {
                    $block('UNREFERENCED_INTERNAL_TRANSFER', (string)$line->id);
                } else {
                    $ref = $line->related_reference;
                    $internal[$ref] ??= ['internal_income' => 0, 'internal_expense' => 0];
                    $internal[$ref][$type] += $value;
                }
                if ($type === 'internal_income') $internalIncome += $value;
                else $internalExpense += $value;
            }
        }
        foreach ($groupBalances as $key => $difference) {
            if ($difference !== 0) $block('UNBALANCED_JOURNAL', $key.'='.$fmt(abs($difference)));
        }
        foreach ($clearing as $key => $difference) {
            if ($difference !== 0) $block('OPEN_CLEARING_BALANCE', $key);
        }
        $eliminatedIncome = 0; $eliminatedExpense = 0;
        foreach ($internal as $reference => $pair) {
            if ($pair['internal_income'] !== $pair['internal_expense']) {
                $block('INTERNAL_TRANSFER_MISMATCH', (string)$reference);
            } else {
                $eliminatedIncome += $pair['internal_income'];
                $eliminatedExpense += $pair['internal_expense'];
            }
        }
        if ($postedCount === 0) $block('NO_POSTED_JOURNALS');
        if (!is_array($profile)) $block('MISSING_KPI_PROFILE');
        $profileValid = false;
        if (is_array($profile)) {
            try {
                $this->budgetPreview->validateProfile($profile);
                if (!array_key_exists('sharing_already_in_expense', $profile) ||
                    !is_bool($profile['sharing_already_in_expense'])) {
                    $block('MISSING_SHARING_INCLUSION_POLICY');
                } else $profileValid = true;
            } catch (ValidationException $e) {
                $block('INVALID_KPI_PROFILE');
            }
        }
        $budgetCount = 0;
        foreach ($budgets as $budget) {
            if ($budget->status !== 'approved') { $block('UNAPPROVED_BUDGET', (string)$budget->id); continue; }
            $budgetCount++;
            $key = $budget->unit_id ?? 'unassigned';
            if (!isset($perUnit[$key])) { $block('UNKNOWN_BUDGET_UNIT', (string)$budget->id); continue; }
            if (!$profileValid) continue;
            $amount = $money($budget->amount);
            if (in_array($budget->category, $profile['income_categories'], true)) {
                $perUnit[$key]['plan_income'] += $amount;
            } elseif (in_array($budget->category, $profile['expense_categories'], true)) {
                $perUnit[$key]['plan_expense'] += $amount;
            } else {
                $block('UNMAPPED_BUDGET_CATEGORY', $budget->category.'|'.$budget->id);
            }
        }
        if (!$budgetCount) $block('NO_APPROVED_BUDGET');
        foreach ($pools as $pool) {
            if ($pool->status !== 'approved' || !is_array($pool->allocations) || !$pool->allocations) {
                $block('UNAPPROVED_OR_EMPTY_POOL', (string)$pool->id); continue;
            }
            $sum = 0;
            foreach ($pool->allocations as $unitId => $amount) {
                if (!ctype_digit((string)$unitId) || !isset($perUnit[(int)$unitId])) {
                    $block('INVALID_POOL_RECIPIENT', (string)$pool->id); continue;
                }
                $allocated = $money($amount);
                $sum += $allocated;
                $perUnit[(int)$unitId]['plan_sharing'] += $allocated;
            }
            if ($sum !== $money($pool->amount)) $block('POOL_SUM_MISMATCH', (string)$pool->id);
        }
        foreach ($disputes as $dispute) {
            if ($dispute->status === 'open') $block('OPEN_SHARING_DISPUTE', (string)$dispute->id);
        }
        foreach ($perUnit as $key => &$row) {
            $row['plan_surplus'] = $row['plan_income'] - $row['plan_expense']
                - ($profileValid && $profile['sharing_already_in_expense'] ? 0 : $row['plan_sharing']);
            $row['actual_surplus'] = $row['income'] + $row['internal_income']
                - $row['expense'] - $row['internal_expense'];
            $row['variance'] = $row['actual_surplus'] - $row['plan_surplus'];
            foreach (['plan_income','plan_expense','plan_sharing','plan_surplus','actual_surplus',
                'variance','income','expense','internal_income','internal_expense'] as $field) {
                $row[$field] = $fmt($row[$field]);
            }
        }
        unset($row);
        $consolidatedIncome = $grossIncome - $eliminatedIncome;
        $consolidatedExpense = $grossExpense - $eliminatedExpense;
        return [
            'source_hash' => $sourceHash,
            'report' => [
                'cycle_id' => $cycle->id, 'currency' => $cycle->organization_id,
                'period' => [$from,$until], 'posted_line_count' => $postedCount,
                'total_imported_lines' => $journal->count(),
                'source_batch_count' => $batches->count(),
                'mappings_count' => $maps->where('active', true)->count(),
                'blocker_count' => count($errors), 'blockers' => array_slice($errors, 0, 300),
                'warnings' => $warnings,
                'unit_managerial' => array_values($perUnit),
                'consolidated' => [
                    'gross_income' => $fmt($grossIncome),
                    'gross_expense' => $fmt($grossExpense),
                    'eliminated_internal_income' => $fmt($eliminatedIncome),
                    'eliminated_internal_expense' => $fmt($eliminatedExpense),
                    'income' => $fmt($consolidatedIncome),
                    'expense' => $fmt($consolidatedExpense),
                    'surplus' => $fmt($consolidatedIncome - $consolidatedExpense),
                    'internal_income_before_elimination' => $fmt($internalIncome),
                    'internal_expense_before_elimination' => $fmt($internalExpense),
                ],
                'profile' => $profile,
                'reconciliation_view_note' => 'Managerial unit includes internal charging. Consolidation eliminates only matching internal transfers; never add plan sharing again to actual expense.',
            ],
        ];
    }

    private function emptyUnit(string $name): array
    {
        return ['unit' => $name,
            'plan_income' => 0, 'plan_expense' => 0, 'plan_sharing' => 0,
            'income' => 0, 'expense' => 0, 'internal_income' => 0, 'internal_expense' => 0,
        ];
    }

    public function prepare(Cycle $cycle, int $actor): int
    {
        $this->access->requireRole($cycle->organization_id, $actor, ['owner','admin','finance']);
        return DB::transaction(function () use ($cycle, $actor) {
            $locked = Cycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $this->assertMutable($locked);
            $result = $this->calculate($locked);
            $version = (int)DB::table('rka_reconciliation_runs')->where('cycle_id', $cycle->id)->max('version') + 1;
            $id = DB::table('rka_reconciliation_runs')->insertGetId([
                'organization_id' => $locked->organization_id, 'cycle_id' => $locked->id,
                'version' => $version, 'status' => 'draft',
                'source_hash' => $result['source_hash'],
                'report' => json_encode($result['report'], JSON_THROW_ON_ERROR),
                'prepared_by' => $actor, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->record($locked->organization_id, $actor, 'reconciliation.prepared', 'reconciliation', $id,
                ['version' => $version, 'blockers' => $result['report']['blocker_count']]);
            return $id;
        });
    }

    public function verify(Cycle $cycle, int $runId, int $actor): void
    {
        $this->access->requireRole($cycle->organization_id, $actor, ['owner','admin','finance']);
        DB::transaction(function () use ($cycle, $runId, $actor) {
            $locked = Cycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $this->assertMutable($locked);
            $run = DB::table('rka_reconciliation_runs')->where('organization_id', $cycle->organization_id)
                ->where('cycle_id', $cycle->id)->where('id', $runId)->lockForUpdate()->first();
            if (!$run) abort(404);
            if ($run->status !== 'draft' || $run->prepared_by === $actor) {
                throw new AuthorizationException('Review rekonsiliasi harus independen dan berstatus draft.');
            }
            $latest = DB::table('rka_reconciliation_runs')->where('cycle_id', $cycle->id)->max('version');
            if ((int)$latest !== (int)$run->version) {
                throw ValidationException::withMessages(['reconciliation' => 'Hanya versi rekonsiliasi terakhir yang boleh disahkan.']);
            }
            $computed = $this->calculate($locked);
            if ($computed['source_hash'] !== $run->source_hash ||
                $computed['report']['blocker_count'] !== 0 || json_decode($run->report, true)['blocker_count'] !== 0) {
                throw ValidationException::withMessages(['reconciliation' => 'Sumber berubah atau masih ada blocker. Jalankan ulang rekonsiliasi.']);
            }
            DB::table('rka_reconciliation_runs')->where('id', $runId)->update([
                'status' => 'verified', 'verified_by' => $actor, 'verified_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->record($locked->organization_id, $actor, 'reconciliation.verified', 'reconciliation', $runId);
        });
    }
}
