<?php

namespace Tests\Feature;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_verified_published_listings_are_discoverable(): void
    {
        $visible = School::factory()->create(['name' => 'Visible Campus', 'listing_status' => 'published', 'verified_at' => now()]);
        $hidden = [
            School::factory()->create(['listing_status' => 'published']),
            School::factory()->create(['listing_status' => 'suspended', 'verified_at' => now()]),
            School::factory()->create(['listing_status' => 'published', 'verified_at' => now(), 'status' => 'suspended']),
        ];

        $this->get('/')->assertOk()->assertSee($visible->name);
        $this->get('/schools')->assertOk()->assertSee($visible->name);
        $this->get('/institutions/'.$visible->slug)->assertOk()->assertSee($visible->name);
        $this->get('/sitemap.xml')->assertOk()->assertSee($visible->slug);

        foreach ($hidden as $school) {
            $this->get('/schools')->assertDontSee($school->name);
            $this->get('/institutions/'.$school->slug)->assertNotFound();
            $this->get('/sitemap.xml')->assertDontSee($school->slug);
        }

        $this->post('/saved/'.$visible->slug)->assertRedirect();
        $this->post('/compare/'.$visible->slug)->assertRedirect();
        $this->get('/saved')->assertSee($visible->name);
        $this->get('/compare')->assertSee($visible->name);

        $visible->forceFill(['listing_status' => 'suspended'])->save();
        $this->get('/saved')->assertDontSee($visible->name);
        $this->get('/compare')->assertDontSee($visible->name);
    }

    public function test_directory_filters_by_city_area_keyword_and_type(): void
    {
        $city = DB::table('cities')->insertGetId(['name' => 'Islamabad', 'slug' => 'islamabad']);
        $area = DB::table('areas')->insertGetId(['name' => 'F-8', 'slug' => 'f-8', 'city_id' => $city]);
        $details = ['listing_status' => 'published', 'verified_at' => now(), 'city_id' => $city, 'area_id' => $area];
        School::factory()->create([...$details, 'name' => 'North School']);
        School::factory()->create([...$details, 'name' => 'North College', 'institute_type' => 'college']);
        School::factory()->create([...$details, 'name' => 'South School', 'area_id' => null]);

        $this->get('/schools?city=islamabad&area=f-8&q=North')->assertOk()
            ->assertSee('North School')->assertDontSee('North College')->assertDontSee('South School');
        $this->get('/directory?q=F-8')->assertSee('North College')->assertDontSee('South School');
        $this->get('/schools/islamabad/f-8?view=grid')->assertOk()->assertSee('Schools in F-8, Islamabad')
            ->assertSee('North School')->assertDontSee('South School');
        $this->get('/schools/islamabad/nonexistent')->assertNotFound();
    }
}
