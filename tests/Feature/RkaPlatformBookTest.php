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

    public function test_book_editorial_approval_does_not_bypass_financial_closing(): void
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

        config()->set('rka_platform.enable_official_publication', true);
        $service = app(BookPublishingService::class);
        try {
            $service->publish($book, $publisher->id);
            $this->fail('Incomplete book should be blocked.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $service->saveSection($section, $editor->id, 'A factual introduction.');
        $service->approveSection($section->fresh(), $reviewer->id);
        try {
            $service->publish($book->fresh(), $publisher->id);
            $this->fail('Publishing requires fully approved financial closing, not only editorial review.');
        } catch (ValidationException $expected) {
            $this->assertNotEmpty($expected->errors());
        }
        $this->assertSame('draft', $book->fresh()->status);
    }
}
