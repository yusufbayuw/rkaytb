<?php

namespace App\Support;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LegacyRkaAccess
{
    /**
     * Legacy RKA screens are scoped by the old users.unit_id field.
     * White-label RKA membership must not grant access to unrelated old tables.
     */
    public static function canView(?User $user): bool
    {
        if (!$user) return false;
        if ($user->hasAnyRole(['superadmin', 'admin-keuangan'])) return true;

        return $user->unit_id !== null &&
            Unit::query()->whereKey($user->unit_id)->exists();
    }

    public static function canAccessRecord(?User $user, Model $record, bool $financeGlobal = false): bool
    {
        if (!self::canView($user)) return false;
        if ($user->hasRole('superadmin')) return true;

        // RKA Gaji has historically shown its full list to admin-keuangan.
        // The other five legacy Resources already filter lists by users.unit_id.
        if ($financeGlobal && $user->hasRole('admin-keuangan')) return true;

        return $user->unit_id !== null &&
            Unit::query()->whereKey($user->unit_id)->exists() &&
            (string)$record->getAttribute('unit_nama') === (string)$user->unit_id;
    }
}
