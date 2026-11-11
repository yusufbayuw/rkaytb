<?php

namespace App\Support;

use App\Models\Unit;
use App\Models\User;

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
}
