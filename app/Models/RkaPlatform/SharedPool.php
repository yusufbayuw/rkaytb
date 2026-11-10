<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class SharedPool extends Model
{
    protected $table = 'rka_shared_pools';
    protected $guarded = [];
    // Match database defaults before a newly created record is reloaded.
    protected $attributes = ['status' => 'draft'];
    protected $casts = [
        'amount' => 'decimal:2',
        'drivers' => 'array',
        'allocations' => 'array',
    ];

}
