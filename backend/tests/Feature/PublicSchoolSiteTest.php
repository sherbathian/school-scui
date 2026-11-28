<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Database\Seeders\IfsSiteThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicSchoolSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_subdomains_serve_only_their_public_website_pages(): void
    {
        $branch = School::factory()->create(['verified_at' => now(), 'listing_status' => 'published']);
        $other = School::factory()->create(['verified_at' => now(), 'listing_status' => 'published']);
        $module = DB::table('modules')->insertGetId(['slug' => 'website', 'name' => 'Website']);
        $theme = DB::table('site_themes')->insertGetId(['name' => 'Example', 'slug' => 'hostname-theme']);
        $settings = DB::table('school_site_settings')->insertGetId([
            'school_id' => $branch->id, 'site_theme_id' => $theme, 'site_enabled' => true, 'subdomain' => 'example-school',
        ]);
        foreach ([
            [$branch->id, 'home', 'Example school home', true],
            [$branch->id, 'about', 'About this school', true],
            [$branch->id, 'draft', 'Private draft', false],
            [$other->id, 'foreign', 'Other school page', true],
        ] as [$id, $slug, $title, $published]) {
            DB::table('school_pages')->insert([
                'school_id' => $id, 'title' => $title, 'slug' => $slug, 'is_published' => $published, 'show_in_menu' => true,
            ]);
        }

        $host = 'https://example-school.taleemfinder.pk';
        $this->get($host.'/')->assertOk()->assertSee('Example school home')
            ->assertSee('href="/about"', false)->assertDontSee('Private draft');
        $this->get($host.'/about')->assertOk()->assertSee('About this school');
        foreach (['/draft', '/foreign', '/institutions/'.$other->slug, '/api/health', '/login'] as $path) {
            $this->get($host.$path)->assertNotFound();
        }
        $this->postJson($host.'/login')->assertNotFound();
        $this->get('https://unknown-school.taleemfinder.pk/')->assertNotFound();
        $this->get('https://taleemfinder.pk/school-sites/'.$branch->slug)->assertOk();
        $this->withoutVite()->get('https://admin.taleemfinder.pk/login')->assertOk();
        DB::table('school_modules')->insert(['school_id' => $branch->id, 'module_id' => $module, 'is_enabled' => false]);
        $this->get($host.'/')->assertNotFound();
        DB::table('school_modules')->where('school_id', $branch->id)->update(['is_enabled' => true]);
        DB::table('school_site_settings')->where('id', $settings)->update(['site_enabled' => false]);
        $this->get($host.'/')->assertNotFound();
    }

    public function test_ifs_theme_uses_published_pages_on_both_public_entry_points(): void
    {
        $branch = School::factory()->create(['verified_at' => now(), 'listing_status' => 'published']);
        DB::table('modules')->insert(['slug' => 'website', 'name' => 'Website']);
        $this->seed(IfsSiteThemeSeeder::class);
        $theme = DB::table('site_themes')->where('slug', 'ifs')->sole();
        DB::table('school_site_settings')->insert([
            'school_id' => $branch->id, 'site_theme_id' => $theme->id, 'site_enabled' => true, 'subdomain' => 'synthetic-ifs',
        ]);

        foreach (['home' => true, 'admissions' => true, 'gallery' => false] as $slug => $published) {
            DB::table('school_pages')->insert([
                'school_id' => $branch->id, 'title' => ucfirst($slug), 'slug' => $slug,
                'show_in_menu' => true, 'is_published' => $published,
            ]);
        }

        foreach (['ApprovedCampus' => true, 'ApprovedClassroom' => true, 'PrivateCampus' => false] as $name => $approved) {
            DB::table('school_media')->insert([
                'school_id' => $branch->id, 'kind' => 'photo', 'path' => 'listing-media/'.$name.'.webp',
                'alt_text' => $name, 'is_approved' => $approved,
            ]);
        }

        $host = 'https://synthetic-ifs.taleemfinder.pk';
        $this->get($host.'/')->assertOk()
            ->assertSee('class="ifs-hero"', false)
            ->assertSee('href="/admissions"', false)
            ->assertDontSee('href="/gallery"', false)
            ->assertSee('ApprovedCampus')->assertSee('ApprovedClassroom')->assertDontSee('PrivateCampus');
        $this->get($host.'/admissions')->assertOk()
            ->assertSee('class="ifs-inner"', false)
            ->assertDontSee('class="ifs-hero"', false);
        $this->get('https://taleemfinder.pk/school-sites/'.$branch->slug)->assertOk()
            ->assertSee('/school-sites/'.$branch->slug.'/admissions');

        $otherTheme = DB::table('site_themes')->insertGetId(['name' => 'Default', 'slug' => 'synthetic-default']);
        DB::table('school_site_settings')->where('school_id', $branch->id)->update(['site_theme_id' => $otherTheme]);
        $this->get($host.'/')->assertOk()->assertDontSee('class="ifs-hero"', false);
    }

    public function test_rich_school_page_content_renders_theme_and_rejects_unsafe_links(): void
    {
        $branch = School::factory()->create(['verified_at' => now(), 'listing_status' => 'published']);
        DB::table('modules')->insert(['slug' => 'website', 'name' => 'Website']);
        $theme = DB::table('site_themes')->insertGetId(['name' => 'Example', 'slug' => 'example']);
        DB::table('school_site_settings')->insert([
            'school_id' => $branch->id, 'site_theme_id' => $theme, 'site_enabled' => true, 'primary_color' => '#135724',
        ]);
        $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]), 'sanctum');
        $url = '/api/admin/services/school-pages';
        $text = fn (string $text, array $marks = []) => ['type' => 'text', 'text' => $text, 'marks' => $marks];
        $document = ['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [$text('Admissions')]],
            ['type' => 'paragraph', 'content' => [
                $text('<strong>literal</strong>', [['type' => 'bold']]),
                $text('Apply now', [['type' => 'link', 'attrs' => ['href' => 'https://example.test/apply']]]),
            ]],
            ['type' => 'orderedList', 'attrs' => ['start' => 3], 'content' => [
                ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text('First step')]]]],
            ]],
        ]];
        $payload = ['school_id' => $branch->id, 'title' => 'Draft home', 'slug' => 'home', 'page_type' => 'home',
            'content' => json_encode(['document' => $document, 'blocks' => [['type' => 'legacy', 'keep' => true]]])];
        $preview = $this->postJson($url.'/preview', $payload)->assertOk()->json('data.html');
        $this->assertStringContainsString('#135724', $preview);
        $this->assertStringContainsString('<strong>&lt;strong&gt;literal&lt;/strong&gt;</strong>', $preview);
        $this->get('/school-sites/'.$branch->slug)->assertNotFound();
        $record = $this->postJson($url, $payload + ['is_published' => true])->assertCreated()->json('data.id');
        $this->get('/school-sites/'.$branch->slug)->assertOk()
            ->assertSee('<h2>Admissions</h2>', false)
            ->assertSee('<ol start="3">', false)
            ->assertSee('href="https://example.test/apply"', false);
        $document['content'][1]['content'][1]['marks'][0]['attrs']['href'] = 'javascript:alert(1)';
        $unsafe = ['content' => json_encode(['document' => $document])];
        $this->patchJson($url.'/'.$record, $unsafe)->assertJsonValidationErrors('content');
        $this->postJson($url.'/preview', $unsafe + $payload)->assertJsonValidationErrors('content');
    }

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
