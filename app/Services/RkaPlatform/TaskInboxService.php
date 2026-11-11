<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{Answer, ApprovalRequest, BookSection, BudgetLine, Cycle, EvidenceRecord, Invitation, Membership, QuestionInstance, SharedPool, Synthesis};
use Illuminate\Support\Facades\DB;

/** Read-only, tenant-scoped tasks. A task never grants a permission or an approval. */
class TaskInboxService
{
    public function forCycle(int $org, int $cycle, int $user): array
    {
        $member = Membership::where('organization_id', $org)->where('user_id', $user)
            ->where('active', true)->first();
        if (!$member || !Cycle::where('organization_id', $org)->whereKey($cycle)->exists()) return [];
        $roles = $member->roles ?? [];
        $manager = (bool)array_intersect($roles, ['owner', 'admin']);
        $finance = (bool)array_intersect($roles, ['owner', 'admin', 'finance']);
        $reviewer = (bool)array_intersect($roles, ['owner', 'admin', 'reviewer']);
        $tasks = [];
        $add = static function (string $key, string $title, string $detail, string $action, int $id, ?string $due = null) use (&$tasks) {
            $tasks[] = compact('key','title','detail','action','id','due');
        };

        $invites = Invitation::where('organization_id', $org)->where('invited_user_id', $user)
            ->whereIn('status', ['invited', 'accepted'])->where('expires_at', '>', now())
            ->whereHas('instance', fn ($q) => $q->where('organization_id', $org)
                ->where('cycle_id', $cycle)->where('status', 'open'))
            ->with('instance')->limit(40)->get();
        foreach ($invites as $invite) {
            if ($invite->status === 'invited') {
                $add('invite:'.$invite->id, 'Tanggapi undangan kontribusi',
                    $invite->instance->prompt_snapshot, 'accept_invite', $invite->id,
                    $invite->expires_at->toDateTimeString());
            } elseif (!Answer::where('instance_id', $invite->instance_id)->where('author_id', $user)->exists()) {
                $add('answer:'.$invite->instance_id, 'Isi jawaban yang ditugaskan',
                    $invite->instance->prompt_snapshot, 'open_question', $invite->instance_id,
                    $invite->instance->due_at?->toDateTimeString() ?? $invite->expires_at->toDateTimeString());
            }
        }

        foreach (QuestionInstance::where('organization_id', $org)->where('cycle_id', $cycle)
            ->where('status', 'open')->where('owner_user_id', $user)->limit(40)->get() as $instance) {
            if (Answer::where('instance_id', $instance->id)->exists() &&
                !Synthesis::where('instance_id', $instance->id)->whereIn('status', ['pending_review', 'verified'])->exists()) {
                $add('synthesis:'.$instance->id, 'Tinjau draf sintesis', $instance->prompt_snapshot,
                    'open_question', $instance->id, $instance->due_at?->toDateTimeString());
            }
        }

        if ($reviewer) {
            foreach (QuestionInstance::where('organization_id', $org)->where('cycle_id', $cycle)
                ->where('status', 'open')
                ->when(!$manager, fn ($q) => $q->where('reviewer_user_id', $user))
                ->limit(50)->get() as $instance) {
                if (Synthesis::where('instance_id', $instance->id)->where('status', 'pending_review')
                    ->where('author_id', '!=', $user)->exists()) {
                    $add('review:'.$instance->id, 'Periksa sintesis narasi', $instance->prompt_snapshot,
                        'open_question', $instance->id, $instance->due_at?->toDateTimeString());
                }
                if (EvidenceRecord::where('organization_id', $org)->where('instance_id', $instance->id)
                    ->where('status', 'pending')->where('uploaded_by', '!=', $user)->exists()) {
                    $add('evidence:'.$instance->id, 'Periksa bukti pendukung', $instance->prompt_snapshot,
                        'open_question', $instance->id, $instance->due_at?->toDateTimeString());
                }
            }
        }

        if ($finance) {
            foreach (BudgetLine::where('organization_id', $org)->where('cycle_id', $cycle)
                ->where('status','draft')->where('created_by','!=',$user)->limit(25)->get() as $line) {
                $add('budget:'.$line->id, 'Periksa anggaran', $line->name, 'finance', $line->id);
            }
            foreach (SharedPool::where('organization_id', $org)->where('cycle_id', $cycle)
                ->where('status','draft')->where('created_by','!=',$user)->limit(25)->get() as $pool) {
                $add('pool:'.$pool->id, 'Periksa Sharing Budget', $pool->name, 'finance', $pool->id);
            }
            foreach (DB::table('rka_journal_account_mappings')->where('organization_id', $org)
                ->where('cycle_id', $cycle)->where('active', false)->where('created_by','!=',$user)
                ->limit(25)->get() as $mapping) {
                $add('mapping:'.$mapping->id, 'Periksa mapping jurnal',
                    $mapping->source.' / '.$mapping->account_code, 'finance', (int)$mapping->id);
            }
            $last = DB::table('rka_reconciliation_runs')->where('organization_id', $org)
                ->where('cycle_id', $cycle)->orderByDesc('version')->first();
            if ($last && $last->status === 'draft' && (int)$last->prepared_by !== $user &&
                (json_decode($last->report, true)['blocker_count'] ?? 1) === 0) {
                $add('recon:'.$last->id, 'Sahkan rekonsiliasi',
                    'Versi '.$last->version.' siap diperiksa.', 'finance', (int)$last->id);
            }
        }

        if ($reviewer) {
            foreach (BookSection::where('status','review')->where('last_edited_by','!=',$user)
                ->whereHas('book', fn ($q) => $q->where('organization_id',$org)
                    ->where('cycle_id',$cycle)->where('status','draft'))
                ->limit(25)->get() as $section) {
                $add('book:'.$section->id, 'Periksa bagian Buku RKA', $section->title,
                    'book', $section->id);
            }
        }
        foreach (ApprovalRequest::where('organization_id',$org)->where('subject_type','cycle')
            ->where('subject_id',$cycle)->where('status','pending')->where('requested_by','!=',$user)
            ->limit(20)->get() as $request) {
            $required = $request->steps_snapshot['steps'][$request->current_step]['roles'] ?? [];
            if (array_intersect($roles, $required) && !$request->decisions()->where('actor_id', $user)->exists()) {
                $add('approval:'.$request->id, 'Keputusan closing / reopen',
                    'Persetujuan tahap '.($request->current_step + 1).' memerlukan keputusan Anda.',
                    'approval', $request->id);
            }
        }
        usort($tasks, static fn ($a,$b) =>
            strcmp($a['due'] ?? '9999-12-31', $b['due'] ?? '9999-12-31')
                ?: strcmp($a['key'], $b['key']));
        return array_slice($tasks, 0, 50);
    }
}
