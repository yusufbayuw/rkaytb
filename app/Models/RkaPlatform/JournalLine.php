<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected $table = 'rka_journal_lines';
    protected $guarded = [];
    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'posted_on' => 'date',
        'metadata' => 'array',
    ];

}
