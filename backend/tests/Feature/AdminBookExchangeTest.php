<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBookExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_books_and_inquiries_have_admin_crud_with_location_and_price_validation(): void
    {
        $root = '/api/admin/community';
        $this->getJson("{$root}/books")->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson("{$root}/book-inquiries")->assertForbidden();
        $this->signIn();
        $this->withoutVite()->get('/admin/community/books')->assertOk();
        $owner = User::factory()->create();
        $enquirer = User::factory()->create();
        $city = DB::table('cities')->insertGetId(['name' => 'Islamabad', 'slug' => 'islamabad']);
        $other = DB::table('cities')->insertGetId(['name' => 'Rawalpindi', 'slug' => 'rawalpindi']);
        $area = DB::table('areas')->insertGetId(['city_id' => $city, 'name' => 'F-8', 'slug' => 'f-8']);
        $book = ['title' => 'Demo Physics', 'user_id' => $owner->id, 'city_id' => $city, 'area_id' => $area, 'price' => '250.00'];

        $this->postJson("{$root}/books", [...$book, 'city_id' => $other])->assertJsonValidationErrors('area_id');
        $this->postJson("{$root}/books", [...$book, 'listing_type' => 'donate'])->assertJsonValidationErrors('price');
        Storage::fake('local');
        $created = $this->post("{$root}/books", [...$book, 'file' => UploadedFile::fake()->image('physics.png')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.condition', 'good');
        $bookId = $created->json('data.id');
        $filePath = $created->json('data.photo_path');
        Storage::disk('local')->assertExists($filePath);
        $this->get("{$root}/books/{$bookId}/download")->assertDownload(basename($filePath));
        $this->patchJson("{$root}/books/{$bookId}", ['status' => 'reserved'])->assertOk();
        $this->getJson("{$root}/books")->assertJsonCount(1, 'data');

        $path = "{$root}/book-inquiries";
        $data = ['book_id' => $bookId, 'user_id' => $enquirer->id, 'message' => 'Is it still available?'];
        $this->postJson($path, [...$data, 'book_id' => 9999])->assertJsonValidationErrors('book_id');
        $inquiry = $this->postJson($path, $data)->assertCreated()->assertJsonPath('data.status', 'open')->json('data.id');
        $this->patchJson("{$path}/{$inquiry}", ['status' => 'accepted'])->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->getJson($path)->assertJsonCount(1, 'data');
        $this->deleteJson("{$path}/{$inquiry}")->assertNoContent();
        $inquiry = $this->postJson($path, $data)->assertCreated()->json('data.id');
        $this->deleteJson("{$root}/books/{$bookId}")->assertNoContent();
        $this->assertDatabaseMissing('book_inquiries', ['id' => $inquiry]);
        Storage::disk('local')->assertMissing($filePath);
    }

    public function test_study_resource_uploads_are_validated_private_and_removed_with_the_record(): void
    {
        Storage::fake('local');
        $admin = $this->signIn();
        $account = User::factory()->create(['is_active' => true]);
        $school = School::factory()->create();
        $path = '/api/admin/community/study-resources';
        $data = ['title' => 'Example notes', 'user_id' => $account->id, 'school_id' => $school->id];
        $this->postJson($path, $data)->assertJsonValidationErrors('file_path');
        $this->post($path, [...$data, 'file' => UploadedFile::fake()->image('wrong.png')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('file');

        $payload = $this->post($path, [...$data, 'file' => UploadedFile::fake()->create('notes.pdf', 15, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.status', 'pending');
        $id = $payload->json('data.id');
        $file = $payload->json('data.file_path');
        Storage::disk('local')->assertExists($file);
        $this->patchJson("{$path}/{$id}", ['status' => 'approved'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->getJson("{$path}?school_id={$school->id}")->assertJsonCount(1, 'data');
        $this->actingAs($account)->getJson("{$path}/{$id}/download")->assertForbidden();
        $this->actingAs($admin)->get("{$path}/{$id}/download")->assertDownload(basename($file));
        DB::table('schools')->where('id', $school->id)->delete();
        $this->assertDatabaseHas('study_resources', ['id' => $id, 'school_id' => null]);
        $this->deleteJson("{$path}/{$id}")->assertNoContent();
        Storage::disk('local')->assertMissing($file);
    }

    private function signIn(): User
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $this->actingAs($admin);

        return $admin;
    }
}
