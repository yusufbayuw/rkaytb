<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class ApprovalPolicy extends Model
{
    protected $table = 'rka_approval_policies';
    protected $guarded = [];
    protected $casts = [
        'conditions' => 'array',
        'steps' => 'array',
        'active' => 'boolean',
    ];

}
