<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class BookSection extends Model
{
    protected $table = 'rka_book_sections';
    protected $guarded = [];
    protected $casts = [
        'source_references' => 'array',
        'required' => 'boolean',
    ];

}
