<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Database\Seeders\PublicDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_sections_and_published_demo_details_are_navigable(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(PublicDemoSeeder::class);
        $this->seed(PublicDemoSeeder::class);
        foreach ([
            '/courses' => 'Python Fundamentals (Demo)',
            '/admissions' => 'Admissions',
            '/books' => 'Mathematics Practice Workbook (Demo)',
            '/events' => 'Campus Open Day (Demo)',
            '/parent-guides' => 'A campus visit checklist (Demo)',
            '/study-resources' => 'Number Practice Sheet (Demo)',
            '/for-schools' => 'For schools and institutes',
            '/community-privacy' => 'Community privacy',
            '/terms-of-use' => 'Terms of use',
            '/copyright-takedown' => 'Copyright and takedown',
            '/signup' => 'Create your TaleemFinder account',
            '/login' => 'Sign in to TaleemFinder',
        ] as $path => $heading) {
            $this->get($path)->assertOk()->assertSee($heading);
        }
        $this->get('/short-courses/python-course-in-islamabad')->assertRedirect('/courses/python-course-in-islamabad');
        $this->get('/courses/python-course-in-islamabad')->assertOk()->assertSee('Practise variables');
        $this->get('/parent-guides/demo-campus-visit-checklist')->assertOk()->assertSee('classroom routines');
        $this->get('/books?kind=donate')->assertSee('English Reading Exercises')->assertDontSee('Mathematics Practice Workbook');
        $this->assertDatabaseCount('books', 3);
        $resource = DB::table('study_resources')->where('file_path', 'study-files/DemoNumberPractice.pdf')->first();
        $this->get("/study-resources/{$resource->id}/download")->assertDownload('DemoNumberPractice.pdf');
        $this->assertDatabaseHas('study_resources', ['id' => $resource->id, 'download_count' => 1]);
        DB::table('study_resources')->where('id', $resource->id)->update(['status' => 'pending']);
        $this->get("/study-resources/{$resource->id}/download")->assertNotFound();

        $this->get('/institutions/directory-demo-school-1')->assertOk()->assertSee('Indicative demo tuition')->assertSee('Example review (Demo)');
        $this->get('/schools/directory-demo-school-1')->assertOk()->assertSee('Indicative demo tuition')->assertSee('Campus brochure (Demo)');
        $media = DB::table('school_media')->where('path', 'listing-media/DemoCampusBrochure.pdf')->first();
        $this->get("/institutions/directory-demo-school-1/media/{$media->id}")->assertOk();
        $this->get('/schools?board=federal-board&level=primary&has_fees=1')->assertOk()->assertSee('Cedar School')->assertDontSee('Summit School');
    }

    public function test_profile_enquiries_require_consent_and_only_target_visible_listings(): void
    {
        $school = School::factory()->create(['listing_status' => 'published', 'verified_at' => now()]);
        $path = "/institutions/{$school->slug}/enquire";
        $data = ['parent_name' => 'Synthetic Parent', 'phone' => '0000000000', 'message' => 'Are applications open?'];
        $this->post($path, $data)->assertSessionHasErrors('consent_given');
        $this->post($path, [...$data, 'consent_given' => '1'])->assertRedirect();
        $this->assertDatabaseHas('listing_leads', ['school_id' => $school->id, 'consent_given' => true]);
        $this->assertDatabaseHas('school_stats_daily', ['school_id' => $school->id, 'leads' => 1]);
        $school->forceFill(['listing_status' => 'draft'])->save();
        $this->post($path, [...$data, 'consent_given' => '1'])->assertNotFound();
    }

    public function test_family_signup_and_book_enquiries_use_the_public_account(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(PublicDemoSeeder::class);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/signup', ['name' => 'Demo Parent', 'email' => 'family@example.test', 'password' => 'Demo1234', 'password_confirmation' => 'Demo1234'])
            ->assertRedirect('/dashboard');
        $account = User::query()->where('email', 'family@example.test')->firstOrFail();
        $this->get('/dashboard')->assertOk()->assertSee('Demo Parent');
        $book = DB::table('books')->first();
        $path = "/books/{$book->id}/enquire";
        $this->post($path, ['message' => 'Still available?'])->assertRedirect();
        $this->post($path, ['message' => 'Still available?'])->assertRedirect();
        $this->assertDatabaseHas('book_inquiries', ['book_id' => $book->id, 'user_id' => $account->id, 'status' => 'open']);
        $this->assertDatabaseCount('book_inquiries', 1);
    }
}
