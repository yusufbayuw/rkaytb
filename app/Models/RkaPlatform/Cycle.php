<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class Cycle extends Model
{
    protected $table = 'rka_cycles';
    protected $guarded = [];
    protected $casts = [
        'settings' => 'array',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

}
