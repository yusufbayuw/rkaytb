<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{Cycle, BudgetLine, SharedPool, Unit};
use Illuminate\Validation\ValidationException;

class KpiBudgetPreviewService
{
    public function validateProfile(array $profile): void
    {
        if (array_diff(array_keys($profile), ['income_categories','expense_categories'])) {
            throw ValidationException::withMessages(['kpiProfileJson' => 'Profil KPI memiliki field yang tidak dikenal.']);
        }
        foreach (['income_categories','expense_categories'] as $key) {
            $values = $profile[$key] ?? null;
            if (!is_array($values) || !array_is_list($values) || count($values) < 1 || count($values) > 100) {
                throw ValidationException::withMessages(['kpiProfileJson' => 'Kategori pendapatan dan biaya masing-masing harus diisi.']);
            }
            if (count(array_unique($values)) !== count($values)) {
                throw ValidationException::withMessages(['kpiProfileJson' => 'Kategori tidak boleh duplikat.']);
            }
            foreach ($values as $value) {
                if (!is_string($value) || trim($value) === '' || mb_strlen($value) > 80) {
                    throw ValidationException::withMessages(['kpiProfileJson' => 'Kategori tidak valid.']);
                }
            }
        }
        if (array_intersect($profile['income_categories'], $profile['expense_categories'])) {
            throw ValidationException::withMessages(['kpiProfileJson' => 'Kategori pendapatan dan biaya tidak boleh tumpang tindih.']);
        }
    }

    public function preview(Cycle $cycle, array $profile): array
    {
        $this->validateProfile($profile);
        $rows = [];
        foreach (Unit::where('organization_id', $cycle->organization_id)->orderBy('name')->get() as $unit) {
            $rows[$unit->id] = ['unit' => $unit->name, 'income' => 0, 'cost' => 0];
        }
        $rows['none'] = ['unit' => 'Tanpa unit', 'income' => 0, 'cost' => 0];
        $pending = 0; $unmapped = 0;
        foreach (BudgetLine::where('organization_id', $cycle->organization_id)
            ->where('cycle_id', $cycle->id)->cursor() as $budget) {
            if ($budget->status !== 'approved') { $pending++; continue; }
            $key = $budget->unit_id ?? 'none';
            if (!array_key_exists($key, $rows)) { $unmapped++; continue; }
            $minor = $this->minor((string)$budget->amount);
            if (in_array($budget->category, $profile['income_categories'], true)) {
                $rows[$key]['income'] += $minor;
            } elseif (in_array($budget->category, $profile['expense_categories'], true)) {
                $rows[$key]['cost'] += $minor;
            } else {
                $unmapped++;
            }
        }
        $income = 0; $cost = 0;
        $viewRows = [];
        foreach ($rows as $row) {
            $income += $row['income']; $cost += $row['cost'];
            $viewRows[] = [
                'unit' => $row['unit'],
                'income' => $this->format($row['income']),
                'cost' => $this->format($row['cost']),
                'surplus_before_sharing' => $this->format($row['income'] - $row['cost']),
            ];
        }
        return [
            'rows' => $viewRows, 'income' => $this->format($income), 'cost' => $this->format($cost),
            'surplus_before_sharing' => $this->format($income - $cost),
            'pending_lines' => $pending, 'unmapped_lines' => $unmapped,
            'shared_pools' => SharedPool::where('organization_id', $cycle->organization_id)
                ->where('cycle_id', $cycle->id)->count(),
            'status' => 'PREVIEW_ONLY',
            'warning' => 'KPI dasar ini hanya menggunakan anggaran disetujui sebelum alokasi biaya bersama; tidak mencakup jurnal realisasi, eliminasi internal, atau data legacy.',
        ];
    }

    private function minor(string $amount): int
    {
        if (!preg_match('/^\d{1,15}(?:\.\d{1,2})?$/', $amount)) {
            throw ValidationException::withMessages(['amount' => 'Anggaran bukan desimal positif yang valid.']);
        }
        [$units, $cents] = array_pad(explode('.', $amount, 2), 2, '');
        return (int)$units * 100 + (int)str_pad($cents, 2, '0');
    }

    private function format(int $minor): string
    {
        $minus = $minor < 0 ? '-' : '';
        $absolute = abs($minor);
        return $minus.intdiv($absolute, 100).'.'.str_pad((string)($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
