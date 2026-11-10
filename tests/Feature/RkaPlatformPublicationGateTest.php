<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{Organization, Membership, Cycle, Book, BookTemplate};
use App\Services\RkaPlatform\BookPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RkaPlatformPublicationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_publication_is_disabled_by_default(): void
    {
        config()->set('rka_platform.enable_official_publication', false);
        $org = Organization::create(['name' => 'Generic organization', 'slug' => 'generic']);
        $user = User::factory()->create();
        Membership::create(['organization_id' => $org->id, 'user_id' => $user->id,
            'roles' => ['publisher']]);
        $cycle = Cycle::create(['organization_id' => $org->id, 'name' => 'Cycle', 'year' => 2032,
            'starts_on' => '2032-01-01', 'ends_on' => '2032-12-31']);
        $template = BookTemplate::create(['organization_id' => $org->id,
            'name' => 'Empty template', 'sections' => []]);
        $book = Book::create(['organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'template_id' => $template->id, 'title' => 'Unverified book']);
        $this->expectException(ValidationException::class);
        app(BookPublishingService::class)->publish($book, $user->id);
    }
}
