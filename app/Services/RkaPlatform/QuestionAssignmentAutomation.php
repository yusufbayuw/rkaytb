<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{Cycle, Membership, Question, QuestionInstance, Unit};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Idempotent bulk assignment, intentionally conservative:
 * skip ambiguous owners or missing reviewers instead of guessing authority.
 * Never modifies existing assignments or approves any narrative.
 */
class QuestionAssignmentAutomation
{
    public function __construct(
        private TenantAccess $access,
        private QuestionRules $rules,
        private AuditTrail $audit
    ) {}

    public function assign(Cycle $cycle, int $actor): array
    {
        $this->access->requireRole($cycle->organization_id, $actor, ['owner', 'admin']);
        return DB::transaction(function () use ($cycle, $actor) {
            $cycle = Cycle::where('organization_id', $cycle->organization_id)
                ->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            if ($cycle->status !== 'draft') {
                throw ValidationException::withMessages(['cycle' => 'Penugasan otomatis hanya untuk siklus draft.']);
            }
            $org = (int)$cycle->organization_id;
            $members = Membership::where('organization_id', $org)->where('active', true)
                ->orderBy('user_id')->get();
            $units = Unit::where('organization_id', $org)->where('active', true)
                ->orderBy('id')->get();
            $created = 0; $skipped = 0;

            foreach (Question::where('organization_id', $org)->where('active', true)
                ->orderBy('id')->get() as $question) {
                $conditions = $question->rules['all'] ?? [];
                $byUnit = $question->owner_role === 'unit_manager'
                    || collect($conditions)->contains(fn ($r) =>
                        in_array($r['field'] ?? '', ['unit_type', 'program_type'], true));
                $targets = $byUnit ? $units->all() : [null];
                foreach ($targets as $unit) {
                    $unitId = $unit?->id;
                    $context = array_intersect_key($unit?->attributes ?? [], array_flip([
                        'unit_type', 'program_type', 'activity_type', 'category',
                        'budget_amount', 'variance_percent', 'risk', 'is_carry_over',
                    ]));
                    if (!$this->rules->applies($question->rules ?? [], $context)) {
                        $skipped++; continue;
                    }
                    $existing = QuestionInstance::where('organization_id', $org)
                        ->where('cycle_id', $cycle->id)->where('question_id', $question->id)
                        ->where('unit_id', $unitId)->exists();
                    if ($existing) { $skipped++; continue; }

                    $owners = $members->filter(fn ($m) =>
                        in_array($question->owner_role, $m->roles ?? [], true) &&
                        ($unitId === null ? $m->unit_id === null : (int)$m->unit_id === (int)$unitId))
                        ->values();
                    if ($owners->count() !== 1) { $skipped++; continue; }
                    $ownerId = (int)$owners->first()->user_id;

                    $reviewers = $members->filter(fn ($m) =>
                        (int)$m->user_id !== $ownerId &&
                        (bool)array_intersect(['reviewer','owner','admin'], $m->roles ?? []) &&
                        $m->unit_id === null)->values();
                    if ($reviewers->isEmpty()) { $skipped++; continue; }
                    $reviewerId = (int)$reviewers->first()->user_id;
                    $instance = QuestionInstance::create([
                        'organization_id' => $org, 'cycle_id' => $cycle->id,
                        'question_id' => $question->id, 'unit_id' => $unitId,
                        'owner_user_id' => $ownerId, 'reviewer_user_id' => $reviewerId,
                        'prompt_snapshot' => $question->prompt, 'rules_snapshot' => $question->rules,
                    ]);
                    $this->audit->record($org, $actor, 'question.auto_assigned',
                        'question_instance', $instance->id,
                        ['question_id' => $question->id, 'unit_id' => $unitId,
                         'owner_user_id' => $ownerId, 'reviewer_user_id' => $reviewerId]);
                    $created++;
                }
            }
            return ['created' => $created, 'skipped' => $skipped];
        });
    }
}
