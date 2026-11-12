<?php

namespace Tests\Feature;

use App\Filament\Pages\RkaPlatformWorkspace;
use App\Models\RkaPlatform\{Cycle, Membership, Organization};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceUpgradeStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_organization_or_cycle_clears_unsaved_forms_and_uploads(): void
    {
        $user = User::factory()->create();
        $orgs = [];
        $cycles = [];
        foreach (['A', 'B'] as $name) {
            $org = Organization::create(['name' => $name, 'slug' => strtolower($name)]);
            Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'roles' => ['owner'], 'active' => true]);
            $orgs[] = $org;
            $cycles[] = Cycle::create(['organization_id' => $org->id, 'name' => 'Cycle '.$name, 'year' => 2026,
                'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        }
        $component = Livewire::actingAs($user)->test(RkaPlatformWorkspace::class)
            ->call('selectOrganization', $orgs[0]->id)
            ->set('answerText', 'Private draft A')->set('lineName', 'Unsaved budget A')->set('sectionContent', 'Unsaved section A')
            ->set('poolDrivers', [1 => 10])->set('mappingAccount', 'ACCOUNT-A')
            ->call('selectOrganization', $orgs[1]->id)
            ->assertSet('answerText', '')->assertSet('lineName', '')->assertSet('sectionContent', '')
            ->assertSet('poolDrivers', [])->assertSet('mappingAccount', '')->assertSet('journalFile', null)
            ->assertSet('answerEvidenceFile', null)->assertSet('cycleId', $cycles[1]->id);
        $component->set('answerText', 'Private draft B')->set('lineAmount', '100')
            ->call('selectCycle', $cycles[1]->id)->assertSet('answerText', '')->assertSet('lineAmount', '');
    }
}
