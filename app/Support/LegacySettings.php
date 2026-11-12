<?php

namespace App\Support;

use App\Models\Setting;

class LegacySettings
{
    private array $flags = [];

    public function flag(int $id): bool
    {
        return $this->flags[$id] ??= (bool) Setting::query()->whereKey($id)->value('valbool');
    }

    public function clear(): void
    {
        $this->flags = [];
    }
}
