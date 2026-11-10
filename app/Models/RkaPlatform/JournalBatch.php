<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class JournalBatch extends Model
{
    protected $table = 'rka_journal_batches';
    protected $guarded = [];
    protected $casts = [
        'metadata' => 'array',
    ];

}
