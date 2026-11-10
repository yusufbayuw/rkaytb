<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class BookSection extends Model
{
    protected $table = 'rka_book_sections';
    protected $guarded = [];
    // Match database defaults before a newly created record is reloaded.
    protected $attributes = ['status' => 'draft'];
    protected $casts = [
        'source_references' => 'array',
        'required' => 'boolean',
    ];
    public function book() { return $this->belongsTo(Book::class, 'book_id'); }
}
