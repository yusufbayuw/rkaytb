<?php

namespace App\Services\RkaPlatform;

class BookAutoContentService
{
    // Only whitelisted block types can bind to a verified frozen snapshot.
    public function table(string $type, array $snapshot): ?array
    {
        $report = $snapshot['financial_report'] ?? null;
        if (!is_array($report)) return null;
        $con = $report['consolidated'] ?? [];
        if ($type === 'consolidated') {
            return ['headers' => ['Komponen','Nominal'], 'rows' => [
                ['Pendapatan bruto', $con['gross_income'] ?? '0.00'],
                ['Eliminasi pendapatan internal', $con['eliminated_internal_income'] ?? '0.00'],
                ['Pendapatan konsolidasi', $con['income'] ?? '0.00'],
                ['Beban bruto', $con['gross_expense'] ?? '0.00'],
                ['Eliminasi beban internal', $con['eliminated_internal_expense'] ?? '0.00'],
                ['Beban konsolidasi', $con['expense'] ?? '0.00'],
                ['Surplus/(Defisit) konsolidasi', $con['surplus'] ?? '0.00'],
                ['Pendapatan pengecualian (konfigurasi profil)', $con['excluded_external_income'] ?? '0.00'],
                ['Surplus tanpa kategori pendapatan pengecualian', $con['surplus_without_excluded_income'] ?? ($con['surplus'] ?? '0.00')],
            ]];
        }
        if ($type === 'unit_kpi') {
            $rows = [];
            foreach (($report['unit_managerial'] ?? []) as $item) {
                $rows[] = [
                    $item['unit'] ?? '',
                    $item['plan_income'] ?? '0.00', $item['plan_expense'] ?? '0.00',
                    $item['plan_sharing'] ?? '0.00', $item['plan_surplus'] ?? '0.00',
                    $item['actual_surplus'] ?? '0.00', $item['variance'] ?? '0.00',
                    $item['plan_surplus_without_excluded_income'] ?? '0.00',
                    $item['actual_surplus_without_excluded_income'] ?? '0.00',
                ];
            }
            return ['headers' => ['Unit','Pendapatan Anggaran','Beban Anggaran',
                'Sharing Rencana','Surplus Rencana','Surplus Aktual','Deviasi',
                'Rencana Tanpa Kategori Pengecualian','Aktual Tanpa Kategori Pengecualian'], 'rows' => $rows];
        }
        if ($type === 'shared_cost') {
            $rows = [];
            $names = [];
            foreach (($snapshot['units'] ?? []) as $unit) $names[(string)$unit['id']] = $unit['name'];
            foreach (($snapshot['shared_pools'] ?? []) as $pool) {
                foreach (($pool['allocations'] ?? []) as $unitId => $amount) {
                    $rows[] = [$pool['code'] ?? '', $pool['name'] ?? '',
                        $pool['method'] ?? '', $names[(string)$unitId] ?? (string)$unitId,
                        (string)$amount];
                }
            }
            return ['headers' => ['Pool','Nama','Metode','Penerima','Alokasi'], 'rows' => $rows];
        }
        if ($type === 'reconciliation') {
            $closing = $snapshot['closing'] ?? [];
            return ['headers' => ['Pemeriksaan','Hasil'], 'rows' => [
                ['Periode', implode(' s.d. ', $report['period'] ?? [])],
                ['Baris jurnal posted', $report['posted_line_count'] ?? 0],
                ['Jumlah sumber jurnal', $report['source_batch_count'] ?? 0],
                ['Jumlah blocker setelah verifikasi', $report['blocker_count'] ?? 0],
                ['Versi closing', $closing['version'] ?? ''],
                ['Hash sumber', $closing['source_hash'] ?? ''],
                ['Hash laporan', $closing['report_hash'] ?? ''],
            ]];
        }
        return null;
    }

    // A true rendered bitmap chart shared by PDF and DOCX, based solely on frozen report data.
    public function chartPng(array $snapshot): ?string
    {
        if (!extension_loaded('gd')) return null;
        $rows = array_slice($snapshot['financial_report']['unit_managerial'] ?? [], 0, 12);
        if (!$rows) return null;
        $width = 1040;
        $height = 95 + count($rows) * 50;
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $navy = imagecolorallocate($image, 29, 57, 88);
        $teal = imagecolorallocate($image, 19, 128, 130);
        $grey = imagecolorallocate($image, 110, 122, 137);
        $line = imagecolorallocate($image, 219, 225, 231);
        imagefill($image, 0, 0, $white);
        imagestring($image, 5, 18, 18, 'Surplus Rencana vs Aktual (per unit)', $navy);
        imagestring($image, 3, 20, 48, 'Rencana', $navy);
        imagefilledrectangle($image, 77, 50, 102, 58, $navy);
        imagestring($image, 3, 130, 48, 'Aktual', $teal);
        imagefilledrectangle($image, 178, 50, 203, 58, $teal);

        $max = 1.0;
        foreach ($rows as $row) {
            $max = max($max, abs((float)($row['plan_surplus'] ?? 0)),
                abs((float)($row['actual_surplus'] ?? 0)));
        }
        $center = 615;
        imageline($image, $center, 78, $center, $height - 12, $grey);
        foreach ($rows as $i => $row) {
            $y = 92 + $i * 50;
            $label = mb_strimwidth((string)($row['unit'] ?? ''), 0, 32, '...', 'UTF-8');
            imagestring($image, 3, 18, $y + 9, $label, $navy);
            imageline($image, 18, $y + 43, $width - 15, $y + 43, $line);
            foreach (['plan_surplus' => [$navy, $y + 6], 'actual_surplus' => [$teal, $y + 24]] as $key => [$color,$top]) {
                $value = (float)($row[$key] ?? 0);
                $length = (int)round(min(1.0, abs($value) / $max) * 395);
                if ($length === 0) continue;
                if ($value >= 0) imagefilledrectangle($image, $center, $top, $center + $length, $top + 12, $color);
                else imagefilledrectangle($image, $center - $length, $top, $center, $top + 12, $color);
            }
        }
        ob_start();
        imagepng($image);
        $content = ob_get_clean();
        imagedestroy($image);
        return is_string($content) ? $content : null;
    }
}
