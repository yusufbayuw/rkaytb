<?php

namespace App\Support;

use App\Models\{RkaCategory, RkaEntry, Unit};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class RkaEntryAccess
{
    private ?array $context = null;
    private mixed $request = null;
    private ?string $actorKey = null;

    private function context(): array
    {
        $user = auth()->user();
        $key = ($user?->id ?? '').':'.($user?->unit_id ?? '');
        if ($this->context !== null && $this->request === request() && $this->actorKey === $key) return $this->context;
        $this->request = request(); $this->actorKey = $key;
        $roles = $user?->roles()->where('guard_name', config('filament.auth.guard', 'web'))->pluck('name')->all() ?? [];
        $unit = $user?->unit_id && Unit::whereKey($user->unit_id)->exists() ? $user->unit_id : null;
        $ready = Schema::hasTable('rka_categories') && Schema::hasTable('rka_entries');
        return $this->context = ['superadmin' => in_array('superadmin', $roles, true),
            'finance' => in_array('admin-keuangan', $roles, true), 'unit' => $unit, 'ready' => $ready,
            'salary' => $ready ? RkaCategory::where('legacy_source', 'rka_gajis')->value('id') : null];
    }

    public static function canViewAny(): bool
    {
        $c = app(self::class)->context();
        return $c['ready'] && ($c['superadmin'] || $c['finance'] || $c['unit'] !== null);
    }

    public static function canWrite(): bool
    {
        $c = app(self::class)->context();
        return self::canViewAny() && !$c['finance'] && !\App\Models\Setting::flag(1);
    }

    public static function superadmin(): bool { return app(self::class)->context()['superadmin']; }
    public static function globalSummary(): bool
    {
        $c = app(self::class)->context();
        return $c['superadmin'] || $c['finance'];
    }

    public static function canView(RkaEntry $entry): bool
    {
        $c = app(self::class)->context();
        return $c['superadmin'] || ($c['unit'] !== null && (string) $entry->unit_nama === (string) $c['unit'])
            || ($c['finance'] && (int) $entry->category_id === (int) $c['salary']);
    }

    public static function scope(Builder $query): Builder
    {
        $c = app(self::class)->context();
        if ($c['superadmin']) return $query;
        return $query->where(function (Builder $q) use ($c): void {
            $c['unit'] !== null ? $q->where('unit_nama', (string) $c['unit']) : $q->whereRaw('1 = 0');
            if ($c['finance'] && $c['salary'] !== null) $q->orWhere('category_id', $c['salary']);
        });
    }
}
