<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicSchoolSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_branch_serves_only_its_published_pages_and_safe_content(): void
    {
        $branch = School::factory()->create(['status' => 'active', 'verified_at' => now(), 'listing_status' => 'published']);
        $other = School::factory()->create(['status' => 'active', 'verified_at' => now(), 'listing_status' => 'published']);
        $module = DB::table('modules')->insertGetId(['slug' => 'website', 'name' => 'Website', 'module_group' => 'services']);
        $theme = DB::table('site_themes')->insertGetId(['name' => 'Synthetic theme', 'slug' => 'synthetic-theme']);
        $blocks = [
            ['type' => 'heading', 'text' => 'Welcome to our school'],
            ['type' => 'paragraph', 'text' => '<script>unsafe</script>'],
            ['type' => 'link', 'text' => 'Approved link', 'url' => 'https://example.test/admissions'],
            ['type' => 'link', 'text' => 'Unsafe link', 'url' => 'javascript:unsafe()'],
        ];
        DB::table('school_pages')->insert([
            ['school_id' => $branch->id, 'title' => 'School home', 'slug' => 'home',
                'is_published' => true, 'show_in_menu' => false, 'sort_order' => 0, 'content' => json_encode(['blocks' => $blocks])],
            ['school_id' => $branch->id, 'title' => 'Second menu page', 'slug' => 'second',
                'is_published' => true, 'show_in_menu' => true, 'sort_order' => 20, 'content' => null],
            ['school_id' => $branch->id, 'title' => 'First menu page', 'slug' => 'first',
                'is_published' => true, 'show_in_menu' => true, 'sort_order' => 10, 'content' => null],
            ['school_id' => $branch->id, 'title' => 'Private draft', 'slug' => 'draft',
                'is_published' => false, 'show_in_menu' => true, 'sort_order' => 0, 'content' => null],
            ['school_id' => $other->id, 'title' => 'Foreign home', 'slug' => 'home',
                'is_published' => true, 'show_in_menu' => true, 'sort_order' => 0, 'content' => null],
            ['school_id' => $other->id, 'title' => 'Foreign page', 'slug' => 'foreign',
                'is_published' => true, 'show_in_menu' => true, 'sort_order' => 0, 'content' => null],
        ]);
        $url = "/school-sites/{$branch->slug}";
        $this->get($url)->assertNotFound();
        $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]), 'sanctum');
        $settings = '/api/admin/services/school-site-settings';
        $data = ['school_id' => $branch->id, 'site_theme_id' => $theme, 'site_enabled' => true];
        DB::table('school_modules')->insert(['school_id' => $branch->id, 'module_id' => $module, 'is_enabled' => true]);
        $site = $this->postJson($settings, $data)->assertCreated()->json('data.id');
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()
            ->assertSee('Welcome to our school')
            ->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)
            ->assertDontSee('<script>unsafe</script>', false)
            ->assertSee('https://example.test/admissions', false)
            ->assertDontSee('javascript:unsafe()', false)
            ->assertSeeInOrder(['First menu page', 'Second menu page'])
            ->assertDontSee('Private draft')->assertDontSee('Foreign home')->assertDontSee('Foreign page');
        $this->get("$url/first")->assertOk();
        $this->get("$url/draft")->assertNotFound();
        $this->get("$url/foreign")->assertNotFound();
        $this->get('/school-sites/missing-school')->assertNotFound();
        $branch->forceFill(['verified_at' => null])->save();
        $this->get($url)->assertNotFound();
        $branch->forceFill(['verified_at' => now(), 'listing_status' => 'draft'])->save();
        $this->get($url)->assertNotFound();
        $branch->forceFill(['listing_status' => 'published'])->save();
        DB::table('school_pages')->where('school_id', $branch->id)->where('slug', 'home')->update(['is_published' => false]);
        $this->get($url)->assertNotFound();
        $this->get("$url/first")->assertOk();
        DB::table('school_pages')->where('school_id', $branch->id)->where('slug', 'home')->update(['is_published' => true]);

        DB::table('site_themes')->where('id', $theme)->update(['is_active' => false]);
        $this->get($url)->assertNotFound();
        DB::table('site_themes')->where('id', $theme)->update(['is_active' => true]);
        DB::table('school_modules')->where('school_id', $branch->id)->where('module_id', $module)->update(['is_enabled' => false]);
        $this->get($url)->assertNotFound();
        DB::table('school_modules')->where('school_id', $branch->id)->where('module_id', $module)->update(['is_enabled' => true]);
        DB::table('school_site_settings')->where('id', $site)->update(['site_enabled' => false]);
        $this->get($url)->assertNotFound();
        $this->withoutVite()->get("/institutions/{$branch->slug}")->assertOk();
    }
}
