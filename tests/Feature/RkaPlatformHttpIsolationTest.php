<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{
    Organization, Membership, Cycle, Question, QuestionInstance,
    EvidenceRecord, BookTemplate, Book
};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RkaPlatformHttpIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_routes_deny_cross_organization_roles_and_unassigned_members(): void
    {
        Storage::fake('local');
        $first = Organization::create(['name'=>'Tenant Alpha','slug'=>'tenant-alpha']);
        $second = Organization::create(['name'=>'Tenant Beta','slug'=>'tenant-beta']);
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $contributor = User::factory()->create();
        $unassignedReviewer = User::factory()->create();
        foreach ([
            [$owner,$first->id,['owner']],
            [$outsider,$second->id,['owner']],
            [$contributor,$first->id,['contributor']],
            [$unassignedReviewer,$first->id,['reviewer']],
        ] as [$user,$org,$roles]) {
            Membership::create(['organization_id'=>$org,'user_id'=>$user->id,
                'roles'=>$roles,'active'=>true]);
        }
        $cycle=Cycle::create(['organization_id'=>$first->id,'name'=>'Fiscal Cycle',
            'year'=>2036,'starts_on'=>'2036-01-01','ends_on'=>'2036-12-31']);
        $q=Question::create(['organization_id'=>$first->id,'code'=>'Q',
            'prompt'=>'Explain the operating results.']);
        $instance=QuestionInstance::create([
            'organization_id'=>$first->id,'cycle_id'=>$cycle->id,'question_id'=>$q->id,
            'owner_user_id'=>$owner->id,'prompt_snapshot'=>$q->prompt,
        ]);
        $path='private/rka-evidence/'.$first->id.'/'.$instance->id.'/source.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test data');
        $answer=\App\Models\RkaPlatform\Answer::create([
            'organization_id'=>$first->id,'instance_id'=>$instance->id,
            'author_id'=>$owner->id,'revision'=>1,
            'content'=>'Scoped source explanation.','status'=>'submitted',
        ]);
        $evidence=EvidenceRecord::create([
            'organization_id'=>$first->id,'instance_id'=>$instance->id,
            'answer_id'=>$answer->id,
            'uploaded_by'=>$owner->id,'original_name'=>'source.pdf',
            'storage_path'=>$path,'mime_type'=>'application/pdf',
            'sha256'=>str_repeat('a',64),'status'=>'verified',
        ]);
        $template=BookTemplate::create([
            'organization_id'=>$first->id,'name'=>'Tenant template',
            'sections'=>[['code'=>'intro','title'=>'Introduction']],
        ]);
        $book=Book::create([
            'organization_id'=>$first->id,'cycle_id'=>$cycle->id,
            'template_id'=>$template->id,'title'=>'Approved Document',
            'status'=>'published','snapshot'=>[
                'title'=>'Approved Document','sha256'=>str_repeat('b',64),
                'currency'=>'IDR','sections'=>[
                    ['title'=>'Introduction','block_type'=>'narrative','content'=>'Public summary.'],
                ],
            ],
        ]);

        $evidenceUrl=route('rka-platform.evidence.download',$evidence);
        $bookUrl=route('rka-platform.books.download',[$book,'pdf']);

        $this->get($evidenceUrl)->assertRedirect();
        $this->get($bookUrl)->assertRedirect();
        $this->actingAs($outsider);
        $this->get($evidenceUrl)->assertForbidden();
        $this->get($bookUrl)->assertForbidden();

        $this->actingAs($unassignedReviewer);
        $this->get($evidenceUrl)->assertForbidden();
        $this->get($bookUrl)->assertForbidden();

        $this->actingAs($contributor);
        $this->get($evidenceUrl)->assertForbidden();
        $this->get($bookUrl)->assertForbidden();

        $this->actingAs($owner);
        $this->get($evidenceUrl)->assertOk();
        $this->get($bookUrl)->assertOk()
            ->assertHeader('Content-Type','application/pdf');
    }

    public function test_revoked_membership_loses_access_to_export_route_immediately(): void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'revoked-org']);
        $user=User::factory()->create();
        $membership=Membership::create([
            'organization_id'=>$org->id,'user_id'=>$user->id,
            'roles'=>['owner'],'active'=>true,
        ]);
        $cycle=Cycle::create([
            'organization_id'=>$org->id,'name'=>'Cycle','year'=>2037,
            'starts_on'=>'2037-01-01','ends_on'=>'2037-12-31',
        ]);
        $template=BookTemplate::create([
            'organization_id'=>$org->id,'name'=>'Neutral template','sections'=>[],
        ]);
        $book=Book::create([
            'organization_id'=>$org->id,'cycle_id'=>$cycle->id,
            'template_id'=>$template->id,'title'=>'Released',
            'status'=>'published','snapshot'=>[
                'title'=>'Released','sha256'=>str_repeat('c',64),
                'sections'=>[['title'=>'Overview','block_type'=>'narrative','content'=>'Text']],
            ],
        ]);
        $this->actingAs($user);
        $this->get(route('rka-platform.books.download',[$book,'pdf']))->assertOk();
        $membership->update(['active'=>false]);
        $this->get(route('rka-platform.books.download',[$book,'pdf']))->assertForbidden();
    }
}
