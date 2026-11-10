<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    protected $table = 'rka_answers';
    protected $guarded = [];
    protected $casts = [
        'evidence' => 'array',
    ];

}
