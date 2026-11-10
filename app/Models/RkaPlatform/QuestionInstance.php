<?php

namespace App\Models\RkaPlatform;

use Illuminate\Database\Eloquent\Model;

class QuestionInstance extends Model
{
    protected $table = 'rka_question_instances';
    protected $guarded = [];
    // Match database defaults before a newly created record is reloaded.
    protected $attributes = ['status' => 'open'];
    protected $casts = [
        'rules_snapshot' => 'array',
        'due_at' => 'datetime',
    ];
    public function question() { return $this->belongsTo(Question::class, 'question_id'); }
    public function answers() { return $this->hasMany(Answer::class, 'instance_id'); }
    public function invitations() { return $this->hasMany(Invitation::class, 'instance_id'); }
    public function syntheses() { return $this->hasMany(Synthesis::class, 'instance_id'); }
}
