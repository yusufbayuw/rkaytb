<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class EvidenceRecord extends Model
{
    protected $table = 'rka_evidence_records';
    protected $guarded = [];
    protected $casts = ['verified_at' => 'datetime'];
    public function answer() { return $this->belongsTo(Answer::class, 'answer_id'); }
    public function instance() { return $this->belongsTo(QuestionInstance::class, 'instance_id'); }
}
