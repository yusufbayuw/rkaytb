<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    protected $table = 'rka_approval_requests';
    protected $guarded = [];
    protected $casts = [
        'steps_snapshot' => 'array',
    ];
    public function decisions() { return $this->hasMany(ApprovalDecision::class, 'request_id'); }
}
