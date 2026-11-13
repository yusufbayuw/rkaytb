<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class RkaDecimal
{
    public static function input(mixed $value, string $field, int $scale = 2): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{1,15}(?:\.\d{1,'.$scale.'})?$/D', $value)) {
            throw ValidationException::withMessages([$field => 'Gunakan angka tidak negatif dengan maksimal '.$scale.' angka desimal.']);
        }
        return bcadd($value, '0', $scale);
    }

    public static function stored(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') return '0.00';
        if (preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d+)?$/D', $value)) $value = str_replace(',', '', $value);
        if (!preg_match('/^\d+(?:\.\d+)?$/D', $value)) {
            throw ValidationException::withMessages(['total' => 'Nilai historis tidak valid. Periksa dan koreksi angka sebelum menyimpan.']);
        }
        return self::round($value);
    }

    public static function round(string $value): string
    {
        $rounded = bcadd($value, '0.005', 2);
        if (bccomp($rounded, '999999999999999.99', 2) > 0) {
            throw ValidationException::withMessages(['total' => 'Total anggaran di luar rentang yang didukung.']);
        }
        return $rounded;
    }

    public static function display(mixed $value, int $scale = 2): string
    {
        if ($value === null || $value === '') return '';
        $raw = (string) $value;
        $negative = str_starts_with($raw, '-');
        if ($negative) $raw = substr($raw, 1);
        if (str_contains($raw, ',')) {
            if (!preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d+)?$/D', $raw)) return (string) $value;
            $raw = str_replace(',', '', $raw);
        }
        if (!preg_match('/^\d+(?:\.\d+)?$/D', $raw)) return (string) $value;
        $parts = explode('.', bcadd($raw, '0', $scale));
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $parts[0]);
        return ($negative ? '-' : '').$whole.($scale ? ','.($parts[1] ?? str_repeat('0', $scale)) : '');
    }
}
