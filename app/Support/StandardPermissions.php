<?php

namespace App\Support;

use App\Filament\Resources;
use Spatie\Permission\Models\Permission;

class StandardPermissions
{
    public const RESOURCES = [
        Resources\UserResource::class => 'user', Resources\RoleResource::class => 'role',
        Resources\PermissionResource::class => 'permission', Resources\UnitResource::class => 'unit',
        Resources\AkunResource::class => 'akun', Resources\SettingResource::class => 'setting',
    ];

    public static function keys(): array
    {
        $keys = [];
        foreach (self::RESOURCES as $subject) {
            foreach (['read', 'create', 'update', 'delete'] as $action) $keys[] = "$action:$subject";
        }
        return $keys;
    }

    public static function key(string $entity, ?string $affix, string $subject, string $case, string $separator): ?string
    {
        if ($entity === 'custom') return $subject;
        if (!isset(self::RESOURCES[$entity])) return null;
        $action = in_array($affix, ['view', 'viewAny'], true) ? 'read' : $affix;
        return $action.':'.self::RESOURCES[$entity];
    }

    public static function sync(bool $dryRun = false): array
    {
        $guard = config('filament.auth.guard', 'web');
        $existing = Permission::where('guard_name', $guard)->pluck('name')->all();
        $missing = array_values(array_diff(self::keys(), $existing));
        if (!$dryRun) {
            foreach ($missing as $name) Permission::findOrCreate($name, $guard);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
        return $missing;
    }
}
