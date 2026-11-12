<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['valbool' => 'boolean'];

    public static function flag(int $id): bool
    {
        return app(\App\Support\LegacySettings::class)->flag($id);
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(\App\Support\LegacySettings::class)->clear());
        static::deleted(fn () => app(\App\Support\LegacySettings::class)->clear());
    }

    public static function initializeDefaults(): void
    {
        foreach ([1 => 'Kunci Anggaran', 2 => 'Nomor Akun Otomatis'] as $id => $name) {
            static::firstOrCreate(['id' => $id], ['nama' => $name, 'valbool' => false]);
        }
    }
}
