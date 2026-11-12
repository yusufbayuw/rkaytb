<?php

namespace App\Health;

use Spatie\Health\Checks\{Check, Result};

class StorageWritableCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();
        foreach ([storage_path('app'), storage_path('framework/cache/data'), app()->bootstrapPath('cache')] as $directory) {
            $path = $directory.'/.rka-health-'.bin2hex(random_bytes(8));
            try {
                if (!is_dir($directory) || !is_writable($directory) || file_put_contents($path, 'probe') !== 5 || file_get_contents($path) !== 'probe') {
                    return $result->failed('Penyimpanan privat atau cache aplikasi tidak dapat ditulis.');
                }
            } catch (\Throwable) {
                return $result->failed('Pemeriksaan penyimpanan tidak berhasil.');
            } finally {
                if (is_file($path)) unlink($path);
            }
        }
        return $result->ok();
    }
}
