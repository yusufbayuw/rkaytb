<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class BudgetLine extends Model
{
    protected $table = 'rka_budget_lines';
    protected $guarded = [];
    // Match database defaults before a newly created record is reloaded.
    protected $attributes = ['status' => 'draft'];
    protected $casts = [
        'amount' => 'decimal:2',
        'period_amounts' => 'array',
        'metadata' => 'array',
    ];

}
