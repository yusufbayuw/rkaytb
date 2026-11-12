<?php

namespace App\Support;

use App\Models\User;

class SystemAccess
{
    public static function allowed(): bool
    {
        $user = auth()->user();
        // Query the database so revocation applies to already-open Livewire pages.
        return $user instanceof User && $user->roles()->where('name', 'superadmin')
            ->where('guard_name', config('filament.auth.guard', 'web'))->exists();
    }

    public static function authorize(): void
    {
        abort_unless(static::allowed(), 403);
    }
}
