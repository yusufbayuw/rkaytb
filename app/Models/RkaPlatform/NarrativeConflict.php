<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class NarrativeConflict extends Model
{
    protected $table = 'rka_narrative_conflicts';
    protected $guarded = [];

    public function instance() { return $this->belongsTo(QuestionInstance::class, 'instance_id'); }
}
