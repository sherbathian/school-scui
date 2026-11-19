<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminCommunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_manages_events_articles_reviews_saves_and_statistics(): void
    {
        $root = '/api/admin/community';
        $this->getJson("{$root}/events")->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson("{$root}/reviews")->assertForbidden();
        $admin = $this->signIn();
        $school = School::factory()->create();
        $reviewer = User::factory()->create();
        $this->withoutVite()->get('/admin/community/events')->assertOk();

        foreach ([
            'events' => [['title' => 'Open Day', 'slug' => 'open-day', 'starts_at' => '2026-11-20 10:00'], ['status' => 'published']],
            'articles' => [['title' => 'Guide', 'slug' => 'guide', 'body' => 'A guide for parents.'], ['status' => 'published']],
            'reviews' => [['school_id' => $school->id, 'user_id' => $reviewer->id, 'rating' => 4, 'body' => 'Example review.'], ['status' => 'approved', 'school_reply' => 'Thank you.']],
            'saved-schools' => [['school_id' => $school->id, 'user_id' => $reviewer->id], ['user_id' => $admin->id]],
            'school-stats' => [['school_id' => $school->id, 'stat_date' => '2026-11-19'], ['profile_views' => 13]],
        ] as $resource => [$create, $patch]) {
            $path = "{$root}/{$resource}";
            $id = $this->postJson($path, $create)->assertCreated()->json('data.id');
            $updated = $this->patchJson("{$path}/{$id}", $patch)->assertOk();
            $id = $updated->json('data.id');
            if ($resource === 'reviews') {
                $updated->assertJsonPath('data.moderated_by_user_id', $admin->id)
                    ->assertJsonPath('data.replied_by_user_id', $admin->id);
            }
            $this->getJson($path)->assertJsonCount(1, 'data');
            $this->deleteJson("{$path}/{$id}")->assertNoContent();
            $this->getJson($path)->assertJsonCount(0, 'data');
        }
    }

    public function test_leads_require_consent_and_same_branch_then_unlink_when_either_record_is_removed(): void
    {
        $this->signIn();
        $branch = School::factory()->create();
        $other = School::factory()->create();
        $newInquiry = fn (School $school) => $school->studentInquiries()->create([
            'first_name' => 'Demo', 'last_name' => 'Student', 'inquiry_date' => now()->toDateString(),
        ]);
        $inquiry = $newInquiry($branch);
        $foreign = $newInquiry($other);
        $path = '/api/admin/community/listing-leads';
        $lead = ['school_id' => $branch->id, 'parent_name' => 'Demo Parent', 'phone' => '03000000000', 'source' => 'profile_page'];
        $this->postJson($path, [...$lead, 'student_inquiry_id' => $inquiry->id])->assertJsonValidationErrors('student_inquiry_id');
        $this->postJson($path, [...$lead, 'consent_given' => true, 'student_inquiry_id' => $foreign->id])
            ->assertJsonValidationErrors('student_inquiry_id');

        $id = $this->postJson($path, [...$lead, 'consent_given' => true, 'student_inquiry_id' => $inquiry->id])
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('student_inquiries', ['id' => $inquiry->id, 'listing_lead_id' => $id]);
        $this->assertForeignKeyFailure(fn () => DB::table('listing_leads')->where('id', $id)->update(['student_inquiry_id' => $foreign->id]));
        $this->assertForeignKeyFailure(fn () => DB::table('student_inquiries')->where('id', $foreign->id)->update(['listing_lead_id' => $id]));
        $this->deleteJson("{$path}/{$id}")->assertNoContent();
        $this->assertDatabaseHas('student_inquiries', ['id' => $inquiry->id, 'listing_lead_id' => null]);

        $id = $this->postJson($path, [...$lead, 'consent_given' => true, 'student_inquiry_id' => $inquiry->id])
            ->assertCreated()->json('data.id');
        $this->deleteJson("/api/schools/{$branch->id}/student-inquiries/{$inquiry->id}")->assertNoContent();
        $this->assertDatabaseHas('listing_leads', ['id' => $id, 'student_inquiry_id' => null]);
    }

    public function test_transfer_and_discount_references_are_enforced_in_the_database_and_api(): void
    {
        $this->signIn();
        $origin = School::factory()->create();
        $destination = School::factory()->create();
        $first = Student::factory()->for($origin)->create();
        $second = Student::factory()->for($destination)->create();
        $transfers = '/api/admin/community/student-transfers';
        $transfer = [
            'from_school_id' => $origin->id, 'to_school_id' => $destination->id,
            'from_student_id' => $first->id, 'to_student_id' => $second->id, 'transfer_date' => '2026-11-19',
        ];
        $this->postJson($transfers, [...$transfer, 'from_student_id' => $second->id])->assertJsonValidationErrors('from_student_id');
        $id = $this->postJson($transfers, $transfer)->assertCreated()->json('data.id');
        $this->assertForeignKeyFailure(fn () => DB::table('student_transfers')->where('id', $id)->update(['to_student_id' => $first->id]));
        $unrelated = School::factory()->create();
        $this->getJson("{$transfers}?branch_id={$origin->id}")->assertJsonCount(1, 'data');
        $this->getJson("{$transfers}?branch_id={$destination->id}")->assertJsonCount(1, 'data');
        $this->getJson("{$transfers}?branch_id={$unrelated->id}")->assertJsonCount(0, 'data');
        $this->patchJson("{$transfers}/{$id}", ['reason' => 'Changed campus'])->assertOk();
        $this->deleteJson("{$transfers}/{$id}")->assertNoContent();

        $discounts = '/api/admin/community/student-fee-discounts';
        $discount = $origin->feeDiscounts()->create(['title' => 'Demo discount', 'discount_type' => 'percentage', 'value' => 10]);
        $foreign = $destination->feeDiscounts()->create(['title' => 'Other discount', 'discount_type' => 'percentage', 'value' => 10]);
        $session = SchoolSession::factory()->for($destination)->create();
        $assignment = ['school_id' => $origin->id, 'student_id' => $first->id, 'fee_discount_id' => $discount->id];
        $this->postJson($discounts, [...$assignment, 'school_session_id' => $session->id])->assertJsonValidationErrors('school_session_id');
        $id = $this->postJson($discounts, $assignment)->assertCreated()->json('data.id');
        $this->assertForeignKeyFailure(fn () => DB::table('student_fee_discounts')->where('id', $id)->update(['fee_discount_id' => $foreign->id]));
        $this->getJson("{$discounts}?school_id={$origin->id}")->assertJsonCount(1, 'data');
        $this->getJson("{$discounts}?school_id={$destination->id}")->assertJsonCount(0, 'data');
        $this->patchJson("{$discounts}/{$id}", ['note' => 'Approved'])->assertOk();
        $this->deleteJson("{$discounts}/{$id}")->assertNoContent();
    }

    public function test_signed_in_saves_persist_and_public_activity_updates_daily_counts(): void
    {
        $school = School::factory()->create(['listing_status' => 'published', 'verified_at' => now()]);
        $account = User::factory()->create(['is_active' => true]);
        $this->actingAs($account)->get("/institutions/{$school->slug}")->assertOk();
        $this->post("/saved/{$school->slug}")->assertRedirect();
        $this->post("/compare/{$school->slug}")->assertRedirect();
        $this->assertDatabaseHas('saved_schools', ['user_id' => $account->id, 'school_id' => $school->id]);
        $this->assertDatabaseHas('school_stats_daily', ['school_id' => $school->id, 'profile_views' => 1, 'saves' => 1, 'compare_adds' => 1]);
        $this->get('/saved')->assertSee($school->name);
        $this->post("/saved/{$school->slug}")->assertRedirect();
        $this->assertDatabaseMissing('saved_schools', ['user_id' => $account->id, 'school_id' => $school->id]);
    }

    private function signIn(): User
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $this->actingAs($admin);

        return $admin;
    }

    private function assertForeignKeyFailure(callable $write): void
    {
        try {
            $write();
            $this->fail('Expected a foreign key violation.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0]);
            $this->assertStringContainsString('foreign key', strtolower($exception->getMessage()));
        }
    }
}
