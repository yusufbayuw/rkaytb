<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class BookTemplate extends Model
{
    protected $table = 'rka_book_templates';
    protected $guarded = [];
    protected $casts = [
        'sections' => 'array',
        'active' => 'boolean',
    ];

}
