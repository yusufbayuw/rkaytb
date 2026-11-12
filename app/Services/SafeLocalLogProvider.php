<?php

namespace App\Services;

use Symfony\Component\Finder\Finder;

class SafeLocalLogProvider extends \AchyutN\FilamentLogViewer\Providers\LocalLogProvider
{
    protected function getAllLogFiles(): array
    {
        return $this->inventory()[0];
    }
    public function skippedFiles(): array { return $this->inventory()[1]; }
    private function inventory(): array
    {
        $root = realpath(storage_path('logs'));
        if (!$root) return [[], []];
        $prefix = str_replace('\\', '/', $root).'/';
        $allowed = []; $skipped = [];
        foreach (Finder::create()->files()->name('*.log')->in($root) as $file) {
            $name = str_replace('\\', '/', $file->getRelativePathname());
            $real = $file->getRealPath();
            if (!$real || !str_starts_with(str_replace('\\', '/', $real), $prefix) || $file->getSize() > 2 * 1024 * 1024) {
                $skipped[] = $name;
            } else $allowed[] = $name;
        }
        return [$allowed, $skipped];
    }
}
