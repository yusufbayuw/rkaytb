<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $table = 'rka_units';
    protected $guarded = [];
    protected $casts = [
        'attributes' => 'array',
        'active' => 'boolean',
    ];

}
