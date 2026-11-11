<?php

namespace App\Services;

use App\Models\Rka;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegacyRkaTotals
{
    private const MONTHS = [
        'januari', 'februari', 'maret', 'april', 'mei', 'juni',
        'juli', 'agustus', 'september', 'oktober', 'november', 'desember',
    ];

    /**
     * Apply the leaf formula only when the user saves financial input.
     * Rendering a Filament table or reading an old record never rewrites data.
     */
    public function beforeSave(Rka $rka): void
    {
        // Validate only new or modified ancestry. Existing legacy records with an
        // untouched parent are not silently rewritten or migrated.
        if (!$rka->exists || $rka->isDirty(['parent_id', 'unit_nama', 'is_parent'])) {
            $this->validateAncestry($rka);
        }

        if ($rka->exists && $rka->isDirty('unit_nama') && $rka->children()->exists()) {
            throw ValidationException::withMessages([
                'unit_nama' => 'Unit induk yang mempunyai anak tidak dapat dipindahkan secara sepihak.',
            ]);
        }

        if ($rka->exists && $rka->isDirty('is_parent') && !(bool)$rka->is_parent
            && $rka->children()->exists()) {
            throw ValidationException::withMessages([
                'is_parent' => 'Anggaran induk yang masih mempunyai anak tidak dapat diubah menjadi rincian.',
            ]);
        }

        if ((bool)$rka->is_parent) {
            return;
        }

        if (!$rka->exists || $rka->isDirty([
            'volume', 'harga_satuan', 'is_parent', 'total', 'total_view', 'total_terbilang',
        ])) {
            // Preserve historic incomplete rows on unrelated edits. Explicit
            // attempts to clear a financial input or overwrite a derived total
            // are rejected rather than leaving stale financial figures.
            if ($rka->volume === null || $rka->harga_satuan === null) {
                if ($rka->exists && !$rka->isDirty([
                    'volume', 'harga_satuan', 'total', 'total_view', 'total_terbilang',
                ])) {
                    return;
                }
                if (!$rka->exists && !$rka->isDirty(['total', 'total_view', 'total_terbilang'])) {
                    return;
                }
                throw ValidationException::withMessages([
                    'total' => 'Volume dan harga satuan harus terisi sebelum total dapat diubah.',
                ]);
            }

            $volume = $this->number($rka->volume, 'volume');
            $price = $this->number($rka->harga_satuan, 'harga_satuan');
            $amount = round($volume * $price, 2);
            if (!is_finite($amount) || $amount > 999999999999999) {
                throw ValidationException::withMessages(['total' => 'Total anggaran di luar rentang yang didukung.']);
            }

            $rka->total = number_format($amount, 2, '.', '');
            $rka->total_view = number_format($amount, 2, '.', ',');
            $rka->total_terbilang = $this->spell((int)floor($amount));
        }
    }

    private function validateAncestry(Rka $rka): void
    {
        if ($rka->parent_id === null) return;

        $id = (int)$rka->parent_id;
        $visited = [];
        while ($id > 0) {
            if (isset($visited[$id]) || count($visited) >= 100 ||
                ($rka->exists && (int)$rka->id === $id)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Hierarki RKA tidak boleh membentuk siklus atau terlalu dalam.',
                ]);
            }
            $visited[$id] = true;
            $parent = Rka::query()->find($id);
            if (!$parent || !(bool)$parent->is_parent) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Induk RKA tidak tersedia atau bukan anggaran induk.',
                ]);
            }
            if ((string)$parent->unit_nama !== (string)$rka->unit_nama) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Induk dan anak RKA harus berada pada unit legacy yang sama.',
                ]);
            }
            $id = $parent->parent_id === null ? 0 : (int)$parent->parent_id;
        }
    }

    public function afterSave(Rka $rka): void
    {
        if (!$rka->wasRecentlyCreated && !$rka->isDirty([
            'total', 'volume', 'harga_satuan', 'is_parent', 'parent_id',
            ...self::MONTHS,
        ])) {
            return;
        }

        DB::transaction(function () use ($rka) {
            if ((bool)$rka->is_parent) {
                $this->updateChain($rka->id);
            }
            $this->updateChain($rka->parent_id);
            if ($rka->isDirty('parent_id')) {
                $previous = $rka->getRawOriginal('parent_id');
                if ($previous !== null && (int)$previous !== (int)$rka->parent_id) {
                    $this->updateChain((int)$previous);
                }
            }
        });
    }

    public function afterDelete(Rka $rka): void
    {
        DB::transaction(fn () => $this->updateChain($rka->parent_id));
    }

    private function updateChain(?int $parentId): void
    {
        $visited = [];
        while ($parentId !== null) {
            if (isset($visited[$parentId]) || count($visited) >= 100) {
                throw ValidationException::withMessages(['parent_id' => 'Hierarki RKA membentuk siklus atau terlalu dalam.']);
            }
            $visited[$parentId] = true;
            $parent = DB::table('rkas')->where('id', $parentId)->lockForUpdate()->first();
            if (!$parent) {
                return;
            }
            if ($parent->is_parent) {
                $children = DB::table('rkas')->where('parent_id', $parentId);
                $total = round((float)(clone $children)->sum('total'), 2);
                $values = [
                    'total' => number_format($total, 2, '.', ''),
                    'total_view' => number_format($total, 2, '.', ','),
                    'total_terbilang' => $this->spell((int)floor($total)),
                ];
                foreach (self::MONTHS as $month) {
                    $values[$month] = number_format(
                        round((float)(clone $children)->sum($month), 2), 2, '.', ''
                    );
                }
                $changed = false;
                foreach ($values as $key => $value) {
                    if ((string)$parent->$key !== $value) {
                        $changed = true;
                        break;
                    }
                }
                if ($changed) {
                    $values['updated_at'] = now();
                    // DB update bypasses model hooks and prevents recursive save loops.
                    DB::table('rkas')->where('id', $parentId)->update($values);
                }
            }
            $parentId = $parent->parent_id === null ? null : (int)$parent->parent_id;
        }
    }

    private function number(mixed $raw, string $field): float
    {
        $value = trim(str_replace(',', '', (string)$raw));
        if (!preg_match('/^\d{1,15}(?:\.\d{1,4})?$/', $value)) {
            throw ValidationException::withMessages([$field => 'Gunakan angka tidak negatif tanpa simbol mata uang.']);
        }
        return (float)$value;
    }

    private function spell(int $amount): string
    {
        if ($amount === 0) return 'Nol';
        $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam',
            'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        if ($amount < 12) return $words[$amount];
        if ($amount < 20) return $this->spell($amount - 10).' belas';
        if ($amount < 100) return trim($this->spell(intdiv($amount, 10)).' puluh '.$this->spellSmallRemainder($amount % 10));
        if ($amount < 200) return trim('seratus '.$this->spellSmallRemainder($amount - 100));
        if ($amount < 1000) return trim($this->spell(intdiv($amount, 100)).' ratus '.$this->spellSmallRemainder($amount % 100));
        foreach ([1000000000000 => 'triliun', 1000000000 => 'miliar',
            1000000 => 'juta', 1000 => 'ribu'] as $divisor => $label) {
            if ($amount >= $divisor) {
                if ($divisor === 1000 && $amount < 2000) {
                    return trim('seribu '.$this->spellSmallRemainder($amount % 1000));
                }
                return trim($this->spell(intdiv($amount, $divisor)).' '.$label.' '.
                    $this->spellSmallRemainder($amount % $divisor));
            }
        }
        return (string)$amount;
    }

    private function spellSmallRemainder(int $number): string
    {
        return $number > 0 ? $this->spell($number) : '';
    }
}
