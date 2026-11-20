<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Database\Seeders\IfsDemoPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchoolPagePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_preview_renders_demo_pages_only_for_platform_admins(): void
    {
        $school = School::factory()->create(['name' => 'IFS', 'slug' => 'ifs']);
        $this->seed(IfsDemoPagesSeeder::class);
        $path = '/local/school-preview/ifs';

        $this->get($path)->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_active' => true]))->get($path)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_active' => true, 'is_super_admin' => true]));
        $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Welcome to IFS')->assertSee('Local preview')->assertSee('Draft')->assertSee('Admissions');
        $this->get("{$path}/admissions")->assertOk()->assertSee('How to enquire');
        $this->get("{$path}/unknown")->assertNotFound();

        $home = DB::table('school_pages')->where('school_id', $school->id)->where('slug', 'home');
        $home->update(['content' => json_encode([['type' => 'paragraph', 'text' => '<script>alert(1)</script>']], JSON_THROW_ON_ERROR)]);
        $this->seed(IfsDemoPagesSeeder::class);
        $this->assertDatabaseCount('school_pages', 4);
        $this->get($path)->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_preview_is_unavailable_outside_local_and_testing(): void
    {
        $this->app->instance('env', 'production');
        $this->get('/local/school-preview/ifs')->assertNotFound();
    }
}
