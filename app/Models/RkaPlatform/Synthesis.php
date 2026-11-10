<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class Synthesis extends Model
{
    protected $table = 'rka_syntheses';
    protected $guarded = [];
    protected $casts = [
        'source_answer_ids' => 'array',
        'verified_at' => 'datetime',
    ];

}
