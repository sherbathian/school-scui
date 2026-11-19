<?php

namespace Tests\Feature;

use App\Models\School;
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
