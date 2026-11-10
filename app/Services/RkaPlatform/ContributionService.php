<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{Answer, Invitation, QuestionInstance, Synthesis, NarrativeConflict};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContributionService
{
    public function __construct(private TenantAccess $access, private AuditTrail $audit) {}

    public function invite(QuestionInstance $instance, int $inviterId, int $inviteeId, int $days = 7): Invitation
    {
        $this->access->requireReadInstance($instance, $inviterId);
        $this->access->membership($instance->organization_id, $inviteeId);
        if ($instance->owner_user_id !== $inviterId) {
            $this->access->requireRole($instance->organization_id, $inviterId, ['owner', 'admin', 'editor']);
        }
        if ($instance->status !== 'open' || $days < 1 ||
            $days > config('rka_platform.invitation_max_days', 30)) {
            throw ValidationException::withMessages(['invitation' => 'Tugas tertutup atau masa undangan tidak valid.']);
        }
        return DB::transaction(function () use ($instance, $inviterId, $inviteeId, $days) {
            $invitation = Invitation::query()->firstOrNew([
                'instance_id' => $instance->id, 'invited_user_id' => $inviteeId,
            ]);
            if ($invitation->exists && in_array($invitation->status, ['invited','accepted','completed'], true) &&
                $invitation->expires_at->isFuture()) {
                throw ValidationException::withMessages(['invitation' => 'Undangan aktif sudah ada.']);
            }
            $invitation->fill([
                'organization_id' => $instance->organization_id, 'invited_by' => $inviterId,
                'scope' => ['read_context' => true, 'submit_answer' => true],
                'expires_at' => now()->addDays($days), 'accepted_at' => null, 'status' => 'invited',
            ])->save();
            $this->audit->record($instance->organization_id, $inviterId, 'invitation.created', 'invitation', $invitation->id);
            return $invitation;
        });
    }

    public function accept(Invitation $invite, int $userId): void
    {
        $this->access->membership($invite->organization_id, $userId);
        if ($invite->invited_user_id !== $userId || $invite->status !== 'invited' ||
            $invite->expires_at->isPast()) {
            throw new AuthorizationException('Undangan tidak berlaku.');
        }
        $invite->update(['status' => 'accepted', 'accepted_at' => now()]);
        $this->audit->record($invite->organization_id, $userId, 'invitation.accepted', 'invitation', $invite->id);
    }

    public function submitAnswer(QuestionInstance $instance, int $userId, string $content, ?int $expectedRevision, array $evidence = []): Answer
    {
        if (!trim($content) || mb_strlen($content) > 100000) {
            throw ValidationException::withMessages(['answer' => 'Jawaban harus berisi 1 hingga 100.000 karakter.']);
        }
        if (count($evidence) > 20) {
            throw ValidationException::withMessages(['evidence' => 'Terlalu banyak lampiran.']);
        }
        return DB::transaction(function () use ($instance, $userId, $content, $expectedRevision, $evidence) {
            $lockedInstance = QuestionInstance::query()->lockForUpdate()->findOrFail($instance->id);
            $this->access->requireAnswerInstance($lockedInstance, $userId);
            $current = Answer::query()->where('instance_id', $instance->id)->where('author_id', $userId)
                ->orderByDesc('revision')->first();
            $revision = $current?->revision ?? 0;
            if ($expectedRevision !== $revision) {
                throw ValidationException::withMessages(['answer' => 'Jawaban telah berubah. Muat ulang sebelum menyimpan.']);
            }
            $answer = Answer::create([
                'organization_id' => $instance->organization_id, 'instance_id' => $instance->id,
                'author_id' => $userId, 'revision' => $revision + 1,
                'content' => $content, 'evidence' => $evidence, 'status' => 'submitted',
            ]);
            $this->audit->record($instance->organization_id, $userId, 'answer.submitted', 'answer', $answer->id, ['revision' => $answer->revision]);
            return $answer;
        });
    }

    public function synthesize(QuestionInstance $instance, int $userId, string $content, array $answerIds): Synthesis
    {
        $this->access->requireReadInstance($instance, $userId);
        if ($instance->owner_user_id !== $userId) {
            $this->access->requireRole($instance->organization_id, $userId, ['owner', 'admin', 'editor']);
        }
        if ($instance->status !== 'open') {
            throw ValidationException::withMessages(['synthesis' => 'Pertanyaan sudah ditutup.']);
        }
        $answerIds = array_values(array_unique(array_map('intval', $answerIds)));
        if (!trim($content) || empty($answerIds)) {
            throw ValidationException::withMessages(['synthesis' => 'Narasi dan sumber jawaban wajib diisi.']);
        }
        return DB::transaction(function () use ($instance, $userId, $content, $answerIds) {
            $lockedInstance = QuestionInstance::query()->lockForUpdate()->findOrFail($instance->id);
            $this->access->requireReadInstance($lockedInstance, $userId);
            $count = Answer::query()->where('instance_id', $instance->id)->whereIn('id', $answerIds)->count();
            if ($count !== count($answerIds)) {
                throw ValidationException::withMessages(['synthesis' => 'Ada jawaban sumber yang tidak valid.']);
            }
            $latest = Synthesis::query()->where('instance_id', $instance->id)->max('version') ?? 0;
            $synthesis = Synthesis::create([
                'organization_id' => $instance->organization_id, 'instance_id' => $instance->id,
                'version' => $latest + 1, 'content' => $content, 'source_answer_ids' => $answerIds,
                'author_id' => $userId, 'status' => 'pending_review',
            ]);
            $this->audit->record($instance->organization_id, $userId, 'synthesis.drafted', 'synthesis', $synthesis->id);
            return $synthesis;
        });
    }

    public function verify(Synthesis $synthesis, int $reviewerId): void
    {
        $this->access->requireRole($synthesis->organization_id, $reviewerId, ['owner', 'admin', 'reviewer']);
        $instance = QuestionInstance::query()->findOrFail($synthesis->instance_id);
        $this->access->requireReadInstance($instance, $reviewerId);
        if ($synthesis->author_id === $reviewerId) {
            throw new AuthorizationException('Pemeriksa tidak boleh menyetujui sintesisnya sendiri.');
        }
        DB::transaction(function () use ($synthesis, $reviewerId) {
            $synthesis = Synthesis::query()->lockForUpdate()->findOrFail($synthesis->id);
            if ($synthesis->status !== 'pending_review') {
                throw ValidationException::withMessages(['synthesis' => 'Status sintesis sudah berubah.']);
            }
            $instance = QuestionInstance::query()->lockForUpdate()->findOrFail($synthesis->instance_id);
            if ($instance->status !== 'open' ||
                NarrativeConflict::query()->where('instance_id', $instance->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['synthesis' => 'Ada konflik atau pertanyaan sudah ditutup.']);
            }
            $question = $instance->question;
            $policy = $question->evidence_policy ?? [];
            if (($policy['required'] ?? false) === true) {
                $count = Answer::query()->whereIn('id', $synthesis->source_answer_ids)
                    ->get()->filter(fn ($a) => count($a->evidence ?? []) > 0)->count();
                if ($count < 1) throw ValidationException::withMessages(['synthesis' => 'Bukti wajib belum tersedia.']);
            }
            $synthesis->update(['status' => 'verified', 'verified_by' => $reviewerId, 'verified_at' => now()]);
            $instance->update(['status' => 'verified']);
            $this->audit->record($synthesis->organization_id, $reviewerId, 'synthesis.verified', 'synthesis', $synthesis->id);
        });
    }
}
