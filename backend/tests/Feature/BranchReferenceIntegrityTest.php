<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\School;
use App\Models\SchoolSession;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BranchReferenceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_events_require_a_same_branch_student(): void
    {
        $branch = School::factory()->create();
        $student = Student::factory()->for($branch)->create();
        $otherStudent = Student::factory()->create();
        $row = [
            'school_id' => $branch->id, 'student_id' => $student->id,
            'event_type' => 'withdrawn', 'event_date' => '2026-11-19',
        ];
        $event = DB::table('student_lifecycle_events')->insertGetId($row);

        $this->assertForeignKeyFailure(fn () => DB::table('student_lifecycle_events')
            ->where('id', $event)->update(['student_id' => $otherStudent->id]));
        $this->assertDatabaseHas('student_lifecycle_events', ['id' => $event, 'student_id' => $student->id]);
        DB::table('students')->where('id', $student->id)->delete();
        $this->assertDatabaseMissing('student_lifecycle_events', ['id' => $event]);
    }

    public function test_expenses_require_a_same_branch_head_and_prevent_its_deletion(): void
    {
        $branch = School::factory()->create();
        $other = School::factory()->create();
        $head = DB::table('expense_heads')->insertGetId(['school_id' => $branch->id, 'title' => 'Synthetic supplies']);
        $otherHead = DB::table('expense_heads')->insertGetId(['school_id' => $other->id, 'title' => 'Synthetic supplies']);
        $expense = DB::table('expenses')->insertGetId([
            'school_id' => $branch->id, 'expense_head_id' => $head,
            'name' => 'Synthetic expense', 'amount' => '20.00', 'expense_date' => '2026-11-19',
        ]);

        $this->assertForeignKeyFailure(fn () => DB::table('expenses')
            ->where('id', $expense)->update(['expense_head_id' => $otherHead]));
        $this->assertForeignKeyFailure(fn () => DB::table('expense_heads')->where('id', $head)->delete());
        $this->assertDatabaseHas('expenses', ['id' => $expense, 'expense_head_id' => $head]);
    }

    public function test_fee_group_grade_matches_both_parents_to_its_branch(): void
    {
        $branch = School::factory()->create();
        $otherBranch = School::factory()->create();
        $group = $branch->feeGroups()->create(['title' => 'Primary']);
        $otherGroup = $otherBranch->feeGroups()->create(['title' => 'Primary']);
        $grade = Grade::factory()->for($branch)->create();
        $otherGrade = Grade::factory()->for($otherBranch)->create();
        $assignment = ['school_id' => $branch->id, 'fee_group_id' => $group->id, 'grade_id' => $grade->id];

        DB::table('fee_group_grade')->insert($assignment);
        $this->assertForeignKeyFailure(fn () => DB::table('fee_group_grade')->insert([...$assignment, 'fee_group_id' => $otherGroup->id]));
        $this->assertForeignKeyFailure(fn () => DB::table('fee_group_grade')->insert([...$assignment, 'grade_id' => $otherGrade->id]));
        $this->assertDatabaseHas('fee_group_grade', $assignment);
    }

    public function test_fee_charges_require_a_same_branch_master_and_session(): void
    {
        $branch = School::factory()->create();
        $otherBranch = School::factory()->create();
        $student = Student::factory()->for($branch)->create();
        $session = SchoolSession::factory()->for($branch)->create();
        $otherSession = SchoolSession::factory()->for($otherBranch)->create();
        $master = $this->createFeeMaster($branch, $session);
        $otherMaster = $this->createFeeMaster($otherBranch, $otherSession);
        $charge = DB::table('student_fee_charges')->insertGetId([
            'school_id' => $branch->id, 'student_id' => $student->id,
            'fee_master_id' => $master, 'school_session_id' => $session->id,
            'billing_month' => '2026-11', 'amount' => '20.00', 'due_date' => '2026-11-19',
        ]);

        $this->assertForeignKeyFailure(fn () => DB::table('student_fee_charges')
            ->where('id', $charge)->update(['fee_master_id' => $otherMaster]));
        $this->assertForeignKeyFailure(fn () => DB::table('student_fee_charges')
            ->where('id', $charge)->update(['school_session_id' => $otherSession->id]));
        $this->assertDatabaseHas('student_fee_charges', [
            'id' => $charge, 'fee_master_id' => $master, 'school_session_id' => $session->id,
        ]);
    }

    private function createFeeMaster(School $branch, SchoolSession $session): int
    {
        return $branch->feeMasters()->create([
            'school_session_id' => $session->id,
            'fee_group_id' => $branch->feeGroups()->create(['title' => 'Primary'])->id,
            'fee_type_id' => $branch->feeTypes()->create(['title' => 'Tuition'])->id,
            'billing_month' => '2026-11', 'amount' => '20.00', 'due_date' => '2026-11-19',
        ])->id;
    }

    private function assertForeignKeyFailure(callable $write): void
    {
        try {
            $write();
            $this->fail('Expected a foreign-key violation.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0]);
            $this->assertStringContainsString('foreign key', strtolower($exception->getMessage()));
        }
    }
}
