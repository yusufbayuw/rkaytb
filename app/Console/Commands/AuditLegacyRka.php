<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only historical financial diagnostics.
 *
 * Reads via Query Builder only; no Model::save/delete, UPDATE, migrations,
 * corrections, file writes or data exports. Results contain IDs, not names.
 */
class AuditLegacyRka extends Command
{
    protected $signature = 'rka:audit-legacy
        {--json : Emit machine-readable findings (no identifying names)}
        {--strict : Exit with failure when anomalies are found}
        {--limit=200000 : Maximum number of rows to scan per legacy table}';

    protected $description = 'Audit legacy RKA totals and hierarchy without changing any data.';

    private const TABLES = [
        'rkas', 'rka_maintenances', 'rka_consumables',
        'rka_operasionals', 'rka_pendapatans',
    ];

    private const MONTHS = [
        'januari','februari','maret','april','mei','juni',
        'juli','agustus','september','oktober','november','desember',
    ];

    private array $counts = [];
    private array $samples = [];

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 500000) {
            $this->error('--limit must be between 1 and 500000.');
            return self::FAILURE;
        }
        $result = $this->scan((int)$limit);
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->info('READ-ONLY legacy RKA audit (no records were changed).');
            foreach ($result['table_summary'] as $table => $stats) {
                $this->line($table.': '.$stats['scanned'].' scanned'.($stats['truncated'] ? ' (INCOMPLETE)' : ''));
            }
            foreach ($result['issues_by_type'] as $key => $count) $this->warn($key.': '.$count);
            $this->info('Total findings: '.$result['issue_count']);
        }
        return ($this->option('strict') && $result['issue_count'] > 0)
            ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Pure read-only audit engine. Extracted from console presentation so the
     * correctness and absence of writes can be tested independently of
     * Symfony/Laravel console output buffering.
     */
    public function scan(int $limit = 200000): array
    {
        if ($limit < 1 || $limit > 500000) {
            throw new \InvalidArgumentException('Audit scan limit must be 1..500000.');
        }
        $this->counts = [];
        $this->samples = [];
        $tables = [];
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                $this->issue($table, 0, 'missing_table');
                continue;
            }

            $rows = DB::table($table)->orderBy('id')->limit($limit + 1)
                ->get(array_merge(
                    ['id','parent_id','is_parent','unit_nama','volume','harga_satuan','total'],
                    self::MONTHS
                ));
            $truncated = $rows->count() > $limit;
            if ($truncated) {
                $this->issue($table, 0, 'incomplete_scan');
                $rows = $rows->take($limit);
            }

            $nodes = [];
            $children = [];
            foreach ($rows as $row) {
                $nodes[(int)$row->id] = $row;
                if ($row->parent_id !== null) {
                    $children[(int)$row->parent_id][] = $row;
                }
            }
            foreach ($nodes as $row) {
                $id = (int)$row->id;
                if ($row->parent_id !== null) {
                    $visited = [$id => true];
                    $cursor = (int)$row->parent_id;
                    $depth = 0;
                    while ($cursor > 0 && $depth++ <= 100) {
                        if (isset($visited[$cursor])) {
                            $this->issue($table, $id, 'cyclic_hierarchy');
                            break;
                        }
                        $visited[$cursor] = true;
                        $parent = $nodes[$cursor] ?? null;
                        if (!$parent) {
                            $this->issue($table, $id, $truncated ? 'unresolved_parent_in_partial_scan' : 'missing_parent');
                            break;
                        }
                        if (!(bool)$parent->is_parent) {
                            $this->issue($table, $id, 'parent_is_leaf');
                            break;
                        }
                        if ((string)$parent->unit_nama !== (string)$row->unit_nama) {
                            $this->issue($table, $id, 'cross_unit_parent');
                            break;
                        }
                        $cursor = $parent->parent_id === null ? 0 : (int)$parent->parent_id;
                    }
                    if ($depth > 101) $this->issue($table, $id, 'hierarchy_too_deep');
                }

                if (!(bool)$row->is_parent && $row->volume !== null && $row->harga_satuan !== null) {
                    if (!$this->numericInput($row->volume) || !$this->numericInput($row->harga_satuan)) {
                        $this->issue($table, $id, 'invalid_leaf_input');
                    } else {
                        $expected = (int)round((float)$row->volume * (float)$row->harga_satuan * 100);
                        $actual = $this->cents($row->total);
                        if ($actual === null) $this->issue($table, $id, 'invalid_leaf_total');
                        elseif ($expected !== $actual) $this->issue($table, $id, 'stale_leaf_total');
                    }
                }
            }

            foreach ($nodes as $row) {
                if (!(bool)$row->is_parent) continue;
                $id = (int)$row->id;
                $dependents = $children[$id] ?? [];
                if (!$dependents) continue;
                foreach (array_merge(['total'], self::MONTHS) as $field) {
                    $total = 0;
                    $valid = true;
                    foreach ($dependents as $child) {
                        $value = $this->cents($child->$field);
                        if ($value === null) {
                            $valid = false;
                            break;
                        }
                        if ($value > 0 && $total > PHP_INT_MAX - $value) {
                            $valid = false;
                            break;
                        }
                        $total += $value;
                    }
                    if (!$valid) {
                        $this->issue($table, $id, 'unverifiable_'.$field);
                        continue;
                    }
                    $stored = $this->cents($row->$field);
                    if ($stored === null) $this->issue($table, $id, 'invalid_parent_'.$field);
                    elseif ($stored !== $total) $this->issue($table, $id, 'stale_parent_'.$field);
                }
            }

            $tables[$table] = [
                'scanned' => count($nodes), 'truncated' => $truncated,
                'rows_with_children' => count($children),
            ];
        }

        return [
            'mode' => 'READ_ONLY',
            'table_summary' => $tables,
            'issue_count' => array_sum($this->counts),
            'issues_by_type' => $this->counts,
            'sample_ids_only' => $this->samples,
        ];
    }

    private function issue(string $table, int $id, string $code): void
    {
        $key = $table.':'.$code;
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
        if (count($this->samples) < 30) {
            $this->samples[] = ['table'=>$table,'id'=>$id,'type'=>$code];
        }
    }

    private function cents(mixed $value): ?int
    {
        $raw = trim((string)$value);
        if ($value === null || !preg_match('/^(-?)(\d{1,15})(?:\.(\d{1,2}))?$/', $raw, $parts)) {
            return null;
        }
        $whole = (int)$parts[2];
        $fraction = (int)str_pad($parts[3] ?? '', 2, '0');
        $amount = $whole * 100 + $fraction;
        return $parts[1] === '-' ? -$amount : $amount;
    }

    private function numericInput(mixed $value): bool
    {
        return preg_match('/^\d{1,15}(?:\.\d{1,4})?$/', trim((string)$value)) === 1;
    }
}
