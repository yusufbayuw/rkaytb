<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    protected $table = 'rka_audit_events';
    protected $guarded = [];
    protected $casts = [
        'payload' => 'array',
    ];
    public $timestamps = false;
}
