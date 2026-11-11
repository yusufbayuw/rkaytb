<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{
    Organization, Membership, Cycle, Question, QuestionInstance, EvidenceRecord
};
use App\Services\RkaPlatform\{
    TenantAccess, ContributionService, EvidenceService
};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RkaPlatformSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function subject(bool $evidenceRequired = false): array
    {
        $org = Organization::create(['name' => 'Test Organization', 'slug' => 'scope-'.rand(10000,99999)]);
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $contributor = User::factory()->create();
        $unassignedReviewer = User::factory()->create();
        foreach ([[$owner,'owner'],[$reviewer,'reviewer'],[$contributor,'contributor'],[$unassignedReviewer,'reviewer']] as [$user,$role]) {
            Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'roles' => [$role]]);
        }
        $cycle = Cycle::create(['organization_id' => $org->id, 'name' => 'Cycle',
            'year' => 2032, 'starts_on' => '2032-01-01', 'ends_on' => '2032-12-31']);
        $q = Question::create(['organization_id' => $org->id, 'code' => 'Q',
            'prompt' => 'Provide rationale', 'evidence_policy' => ['required' => $evidenceRequired]]);
        $instance = QuestionInstance::create(['organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'question_id' => $q->id, 'owner_user_id' => $owner->id,
            'reviewer_user_id' => $reviewer->id, 'prompt_snapshot' => $q->prompt]);
        return compact('org','owner','reviewer','contributor','unassignedReviewer','cycle','q','instance');
    }

    public function test_reviewer_can_only_see_assigned_questions_and_contributor_requires_invitation(): void
    {
        extract($this->subject());
        $access = app(TenantAccess::class);
        $this->assertTrue($access->canReadInstance($instance, $reviewer->id));
        $this->assertFalse($access->canReadInstance($instance, $unassignedReviewer->id));
        $this->assertFalse($access->canReadInstance($instance, $contributor->id));
        $this->assertTrue($access->canReviewInstance($instance, $reviewer->id));
        $this->assertFalse($access->canReviewInstance($instance, $unassignedReviewer->id));
        $service = app(ContributionService::class);
        $invite = $service->invite($instance, $owner->id, $contributor->id);
        $this->assertTrue($access->canReadInstance($instance, $contributor->id));
        $this->assertFalse($access->canAnswerInstance($instance, $contributor->id));
        $service->accept($invite, $contributor->id);
        $this->assertTrue($access->canAnswerInstance($instance, $contributor->id));
        $service->revoke($invite, $owner->id);
        $this->assertFalse($access->canReadInstance($instance, $contributor->id));
        $this->assertFalse($access->canAnswerInstance($instance, $contributor->id));
    }

    public function test_verified_evidence_is_required_for_evidence_bound_synthesis(): void
    {
        extract($this->subject(true));
        Storage::fake('local');
        $svc = app(ContributionService::class);
        $invite = $svc->invite($instance, $owner->id, $contributor->id);
        $svc->accept($invite, $contributor->id);
        $answer = $svc->submitAnswer($instance, $contributor->id, 'Documented explanation.', 0);
        $synthesis = $svc->synthesize($instance, $owner->id, 'Verified conclusion.', [$answer->id]);
        try {
            $svc->verify($synthesis, $reviewer->id);
            $this->fail('Unverified evidence may not be used for final synthesis.');
        } catch (ValidationException $expected) {
            $this->assertNotEmpty($expected->errors());
        }
        $temp = tempnam(sys_get_temp_dir(), 'rka-pdf-');
        file_put_contents($temp, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        try {
            $file = new UploadedFile($temp, 'support.pdf', 'application/pdf', null, true);
            $evidence = app(EvidenceService::class)->upload($answer, $contributor->id, $file);
        } finally {
            @unlink($temp);
        }
        $this->assertSame('pending', $evidence->fresh()->status);
        app(EvidenceService::class)->verify($evidence, $reviewer->id);
        $this->assertSame('verified', EvidenceRecord::find($evidence->id)->status);
        $svc->verify($synthesis, $reviewer->id);
        $this->assertSame('verified', $synthesis->fresh()->status);
    }

    public function test_contributor_cannot_review_anyone_else_evidence(): void
    {
        extract($this->subject());
        $svc = app(ContributionService::class);
        $inv = $svc->invite($instance, $owner->id, $contributor->id);
        $svc->accept($inv, $contributor->id);
        $this->expectException(AuthorizationException::class);
        app(TenantAccess::class)->requireReviewInstance($instance, $contributor->id);
    }
    public function test_synthesis_approval_rejects_outdated_answer_source(): void
    {
        extract($this->subject());
        $svc = app(ContributionService::class);
        $invite = $svc->invite($instance, $owner->id, $contributor->id);
        $svc->accept($invite, $contributor->id);
        $original = $svc->submitAnswer($instance, $contributor->id, 'Original source text.', 0);
        $synthesis = $svc->synthesize($instance, $owner->id, 'Conclusion using the old source.', [$original->id]);

        $svc->submitAnswer($instance, $contributor->id, 'Corrected source text.', 1);
        $this->expectException(ValidationException::class);
        $svc->verify($synthesis, $reviewer->id);
    }

}
