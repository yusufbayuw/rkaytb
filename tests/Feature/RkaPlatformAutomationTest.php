<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{Answer, Cycle, Invitation, Membership, Organization, Question, QuestionInstance};
use App\Services\RkaPlatform\{FinancialReconciliationService, SynthesisDraftService, TaskInboxService};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RkaPlatformAutomationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $suffix): array
    {
        $org = Organization::create(['name' => 'Generic '.$suffix, 'slug' => 'automation-'.$suffix]);
        $cycle = Cycle::create(['organization_id' => $org->id, 'name' => 'Planning',
            'year' => 2040, 'starts_on' => '2040-01-01', 'ends_on' => '2040-12-31']);
        return [$org, $cycle];
    }

    private function enroll(Organization $org, string $role): User
    {
        $user = User::factory()->create();
        Membership::create(['organization_id' => $org->id, 'user_id' => $user->id,
            'roles' => [$role], 'active' => true]);
        return $user;
    }

    public function test_task_inbox_is_tenant_scoped_and_read_only(): void
    {
        [$org, $cycle] = $this->fixture('first');
        [$foreign, $otherCycle] = $this->fixture('second');
        $owner = $this->enroll($org, 'owner');
        $invited = $this->enroll($org, 'contributor');
        $outsider = $this->enroll($foreign, 'contributor');
        $question = Question::create(['organization_id' => $org->id,
            'code' => 'Q', 'prompt' => 'Provide the facts']);
        $instance = QuestionInstance::create([
            'organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'question_id' => $question->id, 'owner_user_id' => $owner->id,
            'prompt_snapshot' => $question->prompt,
        ]);
        $invite = Invitation::create([
            'organization_id' => $org->id, 'instance_id' => $instance->id,
            'invited_user_id' => $invited->id, 'invited_by' => $owner->id,
            'status' => 'invited', 'expires_at' => now()->addDays(2),
        ]);
        $inbox = app(TaskInboxService::class);
        $tasks = $inbox->forCycle($org->id, $cycle->id, $invited->id);
        $this->assertCount(1, $tasks);
        $this->assertSame('accept_invite', $tasks[0]['action']);
        $this->assertSame($invite->id, $tasks[0]['id']);
        $this->assertSame([], $inbox->forCycle($org->id, $cycle->id, $outsider->id));
        $this->assertSame([], $inbox->forCycle($foreign->id, $otherCycle->id, $invited->id));
        $this->assertSame('invited', $invite->fresh()->status);
        $invite->update(['status' => 'accepted']);
        $this->assertSame('open_question', $inbox->forCycle($org->id, $cycle->id, $invited->id)[0]['action']);
    }

    public function test_synthesis_prefill_uses_latest_revision_without_writing_or_approving(): void
    {
        [$org, $cycle] = $this->fixture('draft');
        $owner = $this->enroll($org, 'owner');
        $other = $this->enroll($org, 'contributor');
        $question = Question::create(['organization_id' => $org->id,
            'code' => 'Q2', 'prompt' => 'Describe project']);
        $instance = QuestionInstance::create(['organization_id' => $org->id,
            'cycle_id' => $cycle->id, 'question_id' => $question->id,
            'owner_user_id' => $owner->id, 'prompt_snapshot' => $question->prompt]);
        Answer::create(['organization_id' => $org->id, 'instance_id' => $instance->id,
            'author_id' => $owner->id, 'revision' => 1, 'content' => 'Old version']);
        $latest = Answer::create(['organization_id' => $org->id, 'instance_id' => $instance->id,
            'author_id' => $owner->id, 'revision' => 2, 'content' => 'New version']);
        $draft = app(SynthesisDraftService::class)->prepare($instance, $owner->id);
        $this->assertSame([$latest->id], $draft['answer_ids']);
        $this->assertStringContainsString('New version', $draft['content']);
        $this->assertStringNotContainsString('Old version', $draft['content']);
        $this->assertSame(0, DB::table('rka_syntheses')->count());
        $this->expectException(AuthorizationException::class);
        app(SynthesisDraftService::class)->prepare($instance, $other->id);
    }

    public function test_reconciliation_preparation_is_idempotent_and_remains_draft(): void
    {
        [$org, $cycle] = $this->fixture('finance');
        $finance = $this->enroll($org, 'finance');
        $svc = app(FinancialReconciliationService::class);
        $first = $svc->prepareIfChanged($cycle, $finance->id);
        $this->assertSame($first, $svc->prepareIfChanged($cycle, $finance->id));
        $this->assertSame(1, DB::table('rka_reconciliation_runs')->where('cycle_id', $cycle->id)->count());
        $this->assertSame('draft', DB::table('rka_reconciliation_runs')->where('id', $first)->value('status'));
        $cycle->update(['settings' => ['close_risk' => 'high']]);
        $second = $svc->prepareIfChanged($cycle->fresh(), $finance->id);
        $this->assertNotSame($first, $second);
        $this->assertSame(2, DB::table('rka_reconciliation_runs')->where('cycle_id', $cycle->id)->count());
        $this->assertSame('draft', DB::table('rka_reconciliation_runs')->where('id', $second)->value('status'));
    }

    public function test_non_finance_user_cannot_prepare_reconciliation(): void
    {
        [$org, $cycle] = $this->fixture('denied');
        $contributor = $this->enroll($org, 'contributor');
        $this->expectException(AuthorizationException::class);
        app(FinancialReconciliationService::class)->prepareIfChanged($cycle, $contributor->id);
    }
}
