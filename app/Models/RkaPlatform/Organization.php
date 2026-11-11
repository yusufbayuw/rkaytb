<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $table = 'rka_organizations';
    protected $guarded = [];
    protected $casts = [
        'settings' => 'array',
    ];
    public function memberships() { return $this->hasMany(Membership::class, 'organization_id'); }
    public function units() { return $this->hasMany(Unit::class, 'organization_id'); }
}
