<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $table = 'rka_memberships';
    protected $guarded = [];
    protected $casts = [
        'roles' => 'array',
        'active' => 'boolean',
    ];

}
