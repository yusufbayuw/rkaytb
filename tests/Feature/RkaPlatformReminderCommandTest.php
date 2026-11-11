<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{Cycle, Invitation, Membership, Organization, Question, QuestionInstance};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RkaPlatformReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_is_off_by_default(): void
    {
        config(['rka_platform.task_reminder_mail_enabled' => false]);
        $this->artisan('rka:remind-tasks')->assertSuccessful();
    }

    public function test_dry_run_finds_due_invitation_without_sending(): void
    {
        config(['rka_platform.task_reminder_mail_enabled' => false]);
        $org = Organization::create(['name' => 'Generic Workspace', 'slug' => 'digest-test']);
        $cycle = Cycle::create(['organization_id' => $org->id, 'name' => 'Planning',
            'year' => 2042, 'starts_on' => '2042-01-01', 'ends_on' => '2042-12-31']);
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        foreach ([[$owner, 'owner'], [$invitee, 'contributor']] as [$user, $role]) {
            Membership::create(['organization_id' => $org->id,
                'user_id' => $user->id, 'roles' => [$role], 'active' => true]);
        }
        $question = Question::create(['organization_id' => $org->id,
            'code' => 'REMINDER', 'prompt' => 'Describe the project']);
        $instance = QuestionInstance::create([
            'organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'question_id' => $question->id, 'owner_user_id' => $owner->id,
            'prompt_snapshot' => $question->prompt,
        ]);
        Invitation::create([
            'organization_id' => $org->id, 'instance_id' => $instance->id,
            'invited_user_id' => $invitee->id, 'invited_by' => $owner->id,
            'status' => 'invited', 'expires_at' => now()->addDays(2),
        ]);
        $this->artisan('rka:remind-tasks --dry-run')
            ->expectsOutput('Eligible reminder digests: 1')->assertSuccessful();
    }
}
