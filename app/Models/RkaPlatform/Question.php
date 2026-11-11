<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $table = 'rka_questions';
    protected $guarded = [];
    protected $casts = [
        'rules' => 'array',
        'evidence_policy' => 'array',
        'required' => 'boolean',
        'active' => 'boolean',
    ];

}
