<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{Organization, Membership, Cycle, BookTemplate, Book, BookSection};
use App\Services\RkaPlatform\BookPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RkaPlatformBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_book_is_a_frozen_snapshot_after_independent_review(): void
    {
        $org = Organization::create(['name' => 'Example organization', 'slug' => 'example-organization']);
        $editor = User::factory()->create();
        $reviewer = User::factory()->create();
        $publisher = User::factory()->create();
        foreach ([[$editor,'editor'],[$reviewer,'reviewer'],[$publisher,'publisher']] as [$user,$role]) {
            Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'roles' => [$role]]);
        }
        $cycle = Cycle::create(['organization_id' => $org->id, 'name' => 'Cycle',
            'year' => 2030, 'starts_on' => '2030-01-01', 'ends_on' => '2030-12-31']);
        $template = BookTemplate::create(['organization_id' => $org->id,
            'name' => 'General template', 'sections' => [['code' => 'intro', 'title' => 'Introduction', 'required' => true]]]);
        $book = Book::create(['organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'template_id' => $template->id, 'title' => 'Annual plan']);
        $section = BookSection::create(['book_id' => $book->id, 'code' => 'intro',
            'title' => 'Introduction', 'required' => true]);

        $service = app(BookPublishingService::class);
        try {
            $service->publish($book, $publisher->id);
            $this->fail('Incomplete book should be blocked.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $service->saveSection($section, $editor->id, 'A factual introduction.');
        $service->approveSection($section->fresh(), $reviewer->id);
        $book = $service->publish($book->fresh(), $publisher->id);
        $this->assertSame('published', $book->status);
        $this->assertSame('A factual introduction.', $book->snapshot['sections'][0]['content']);
        $this->assertNotEmpty($book->snapshot['sha256']);
    }
}
