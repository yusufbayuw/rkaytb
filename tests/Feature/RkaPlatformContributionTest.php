<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{Organization, Membership, Cycle, Question, QuestionInstance, Answer};
use App\Services\RkaPlatform\{ContributionService, TenantAccess};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RkaPlatformContributionTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(string $slug): Organization
    {
        return Organization::create(['name' => $slug, 'slug' => $slug]);
    }

    private function enroll(Organization $org, User $user, string $role): void
    {
        Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'roles' => [$role]]);
    }

    public function test_invitation_is_tenant_scoped_and_answers_are_revisioned(): void
    {
        $org = $this->createOrganization('org-a');
        $otherOrg = $this->createOrganization('org-b');
        $owner = User::factory()->create();
        $contributor = User::factory()->create();
        $outsider = User::factory()->create();
        $reviewer = User::factory()->create();
        $this->enroll($org, $owner, 'owner');
        $this->enroll($org, $contributor, 'contributor');
        $this->enroll($org, $reviewer, 'reviewer');
        $this->enroll($otherOrg, $outsider, 'contributor');

        $cycle = Cycle::create(['organization_id' => $org->id, 'name' => 'Budget Cycle',
            'year' => 2028, 'starts_on' => '2028-01-01', 'ends_on' => '2028-12-31']);
        $question = Question::create(['organization_id' => $org->id, 'code' => 'Q-A',
            'prompt' => 'Explain the activity.', 'evidence_policy' => []]);
        $instance = QuestionInstance::create([
            'organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'question_id' => $question->id, 'owner_user_id' => $owner->id,
            'prompt_snapshot' => $question->prompt,
        ]);
        $service = app(ContributionService::class);
        try {
            $service->invite($instance, $owner->id, $outsider->id);
            $this->fail('Cross-organization invite must be denied.');
        } catch (AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $invite = $service->invite($instance, $owner->id, $contributor->id, 7);
        $service->accept($invite, $contributor->id);
        $first = $service->submitAnswer($instance, $contributor->id, 'Initial fact.', 0);
        $second = $service->submitAnswer($instance, $contributor->id, 'Revised fact.', 1);
        $this->assertSame(2, $second->revision);
        $this->assertSame(2, Answer::where('instance_id', $instance->id)->count());
        $this->expectException(ValidationException::class);
        $service->submitAnswer($instance, $contributor->id, 'Stale edit.', 1);
    }

    public function test_synthesis_cannot_be_self_approved(): void
    {
        $org = $this->createOrganization('org-c');
        $owner = User::factory()->create();
        $contributor = User::factory()->create();
        $this->enroll($org, $owner, 'owner');
        $this->enroll($org, $contributor, 'contributor');
        $cycle = Cycle::create(['organization_id' => $org->id, 'name' => 'Cycle',
            'year' => 2029, 'starts_on' => '2029-01-01', 'ends_on' => '2029-12-31']);
        $question = Question::create(['organization_id' => $org->id, 'code' => 'Q1',
            'prompt' => 'Why?', 'evidence_policy' => []]);
        $instance = QuestionInstance::create(['organization_id' => $org->id,
            'cycle_id' => $cycle->id, 'question_id' => $question->id,
            'owner_user_id' => $owner->id, 'prompt_snapshot' => 'Why?']);
        $svc = app(ContributionService::class);
        $invite = $svc->invite($instance, $owner->id, $contributor->id);
        $svc->accept($invite, $contributor->id);
        $answer = $svc->submitAnswer($instance, $contributor->id, 'Explanation.', 0);
        $synthesis = $svc->synthesize($instance, $owner->id, 'Final draft.', [$answer->id]);
        $this->expectException(AuthorizationException::class);
        $svc->verify($synthesis, $owner->id);
    }

    public function test_tenant_access_requires_membership_even_for_privileged_role(): void
    {
        $org = $this->createOrganization('org-d');
        $user = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(TenantAccess::class)->requireRole($org->id, $user->id, ['owner']);
    }
}
