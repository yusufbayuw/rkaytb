<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $table = 'rka_invitations';
    protected $guarded = [];
    protected $casts = [
        'scope' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];
    public function instance() { return $this->belongsTo(QuestionInstance::class, 'instance_id'); }
}
