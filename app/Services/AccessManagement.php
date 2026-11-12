<?php

namespace App\Services;

use App\Models\User;
use App\Support\SystemAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

class AccessManagement
{
    public static function validatePermissions(array $names): void
    {
        $available = Permission::where('guard_name', config('filament.auth.guard', 'web'))->pluck('name')->all();
        if (array_diff($names, $available)) throw ValidationException::withMessages(['permissions' => 'Permission tidak tersedia pada guard panel. Jalankan sinkronisasi permission terlebih dahulu.']);
    }

    public static function syncRoles(User $user, array $ids): void
    {
        SystemAccess::authorize();
        DB::transaction(function () use ($user, $ids) {
            self::lockSuperadmin();
            $roles = Role::where('guard_name', config('filament.auth.guard', 'web'))->whereIn('id', $ids)->get();
            if ($roles->count() !== count(array_unique($ids))) throw ValidationException::withMessages(['roles' => 'Role tidak tersedia pada guard panel.']);
            if (!$roles->contains('name', 'superadmin')) self::protectLastAdmin($user);
            $before = $user->roles()->pluck('name')->all();
            $user->syncRoles($roles);
            self::auditAssignment($user, $before, $roles->pluck('name')->all(), 'roles');
        });
    }

    public static function deleteUser(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            self::lockSuperadmin();
            self::protectLastAdmin($user);
            return (bool) $user->delete();
        });
    }

    private static function lockSuperadmin(): void
    {
        Role::where('name', 'superadmin')->where('guard_name', config('filament.auth.guard', 'web'))->lockForUpdate()->get();
    }

    private static function protectLastAdmin(User $user): void
    {
        if ($user->roles()->where('name', 'superadmin')->exists() && User::role('superadmin', config('filament.auth.guard', 'web'))->count() <= 1) {
            throw ValidationException::withMessages(['roles' => 'Administrator terakhir tidak dapat dihapus atau dicabut rolenya.']);
        }
    }

    public static function auditAssignment(Model $subject, array $before, array $after, string $key): void
    {
        sort($before); sort($after);
        if ($before === $after) return;
        app(\MrAdder\FilamentLogger\FilamentLogger::class)->log('Assignment Changed', 'Perubahan '.$key,
            ['subject' => $subject, 'logName' => 'Access Management', 'properties' => ['old' => [$key => $before], 'attributes' => [$key => $after]]]);
    }
}
