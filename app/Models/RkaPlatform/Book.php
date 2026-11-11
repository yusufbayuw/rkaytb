<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $table = 'rka_books';
    protected $guarded = [];
    // Match database defaults before a newly created record is reloaded.
    protected $attributes = ['status' => 'draft'];
    protected $casts = [
        'snapshot' => 'array',
        'published_at' => 'datetime',
    ];
    public function sections() { return $this->hasMany(BookSection::class, 'book_id')->orderBy('position'); }
    public function organization() { return $this->belongsTo(Organization::class); }
}
