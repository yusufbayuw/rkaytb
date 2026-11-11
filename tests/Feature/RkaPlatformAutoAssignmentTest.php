<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{Cycle, Membership, Organization, Question, QuestionInstance, Unit};
use App\Services\RkaPlatform\QuestionAssignmentAutomation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RkaPlatformAutoAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_assignment_is_idempotent_and_requires_unambiguous_owners_and_reviewers(): void
    {
        $org = Organization::create(['name' => 'Generic School', 'slug' => 'school-workflow']);
        $cycle = Cycle::create(['organization_id' => $org->id, 'name' => 'RKA',
            'year' => 2038, 'starts_on' => '2038-01-01', 'ends_on' => '2038-12-31']);
        $unit = Unit::create(['organization_id' => $org->id,
            'code' => 'U1', 'name' => 'Unit One', 'attributes' => ['unit_type' => 'school']]);
        $manager = User::factory()->create();
        $reviewer = User::factory()->create();
        $unitOwner = User::factory()->create();
        foreach ([
            [$manager, ['owner'], null],
            [$reviewer, ['reviewer'], null],
            [$unitOwner, ['unit_manager'], $unit->id],
        ] as [$user, $roles, $unitId]) {
            Membership::create(['organization_id' => $org->id, 'user_id' => $user->id,
                'roles' => $roles, 'unit_id' => $unitId, 'active' => true]);
        }
        Question::create(['organization_id' => $org->id, 'code' => 'GEN',
            'prompt' => 'Narrative for the organization', 'owner_role' => 'owner']);
        Question::create(['organization_id' => $org->id, 'code' => 'UNIT',
            'prompt' => 'Narrative for each school unit', 'owner_role' => 'unit_manager',
            'rules' => ['all' => [['field' => 'unit_type', 'operator' => 'eq', 'value' => 'school']]]]);
        $service = app(QuestionAssignmentAutomation::class);
        $initial = $service->assign($cycle, $manager->id);
        $this->assertSame(2, $initial['created']);
        $this->assertSame(0, $initial['skipped']);
        $this->assertSame(2, QuestionInstance::where('organization_id', $org->id)
            ->where('cycle_id', $cycle->id)->count());
        $this->assertSame(0, $service->assign($cycle, $manager->id)['created']);
        $this->assertSame(2, QuestionInstance::where('organization_id', $org->id)
            ->where('cycle_id', $cycle->id)->count());
    }
}
