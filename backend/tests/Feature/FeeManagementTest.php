<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\LegacyFeeType;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_admin_can_create_a_fee_invoice_and_record_partial_payments(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $feeTypeResponse = $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/legacy-fee-types', [
                'title' => 'Tuition fee',
                'academic_year' => '2026-2027',
                'amount' => '125.50',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '125.50');
        $feeTypeId = $feeTypeResponse->json('data.id');
        $student = $this->enrolledStudentFor($school);

        $invoiceResponse = $this->postJson('/api/schools/'.$school->id.'/fee-invoices', [
            'student_id' => $student->id,
            'fee_type_id' => $feeTypeId,
            'academic_year' => '2026-2027',
        ])->assertCreated()->assertJsonPath('data.amount', '125.50');
        $invoiceId = $invoiceResponse->json('data.id');

        $this->postJson('/api/schools/'.$school->id.'/fee-invoices/'.$invoiceId.'/payments', [
            'amount' => '25.50',
            'reference' => 'RCPT-1001',
        ])->assertOk()
            ->assertJsonPath('data.amount_paid', '25.50')
            ->assertJsonPath('data.balance', '100.00')
            ->assertJsonPath('data.status', 'partial');

        $this->postJson('/api/schools/'.$school->id.'/fee-invoices/'.$invoiceId.'/payments', [
            'amount' => '100.00',
        ])->assertOk()
            ->assertJsonPath('data.amount_paid', '125.50')
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseCount('fee_payments', 2);
        $this->getJson('/api/schools/'.$school->id.'/fee-invoices?academic_year=2026-2027')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.admission_number', $student->admission_number);

        $this->patchJson('/api/schools/'.$school->id.'/legacy-fee-types/'.$feeTypeId, ['amount' => '150.00'])
            ->assertOk()
            ->assertJsonPath('data.amount', '150.00');
        $this->getJson('/api/schools/'.$school->id.'/fee-invoices?academic_year=2026-2027')
            ->assertJsonPath('data.0.amount', '125.50');
        $this->deleteJson('/api/schools/'.$school->id.'/fee-invoices/'.$invoiceId)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invoice');
        $this->patchJson('/api/schools/'.$school->id.'/legacy-fee-types/'.$feeTypeId, ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
        $this->patchJson('/api/schools/'.$school->id.'/legacy-fee-types/'.$feeTypeId, ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_overpayment_is_rejected_and_an_unpaid_invoice_can_be_voided(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $student = $this->enrolledStudentFor($school);
        $feeType = LegacyFeeType::factory()->for($school)->create([
            'title' => 'Books',
            'academic_year' => '2026-2027',
            'amount_cents' => 5000,
        ]);
        $invoice = $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/fee-invoices', [
                'student_id' => $student->id,
                'fee_type_id' => $feeType->id,
                'academic_year' => '2026-2027',
            ])->assertCreated();
        $invoiceId = $invoice->json('data.id');

        $this->postJson('/api/schools/'.$school->id.'/fee-invoices/'.$invoiceId.'/payments', ['amount' => '50.01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->deleteJson('/api/schools/'.$school->id.'/fee-invoices/'.$invoiceId)->assertNoContent();
        $this->postJson('/api/schools/'.$school->id.'/fee-invoices/'.$invoiceId.'/payments', ['amount' => '10.00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invoice');
        $this->getJson('/api/schools/'.$school->id.'/fee-invoices?academic_year=2026-2027')
            ->assertJsonPath('data.0.status', 'void');
    }

    public function test_fee_records_are_school_admin_only_and_invoice_requires_matching_enrollment_year(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $teacher = User::factory()->create();
        $teacher->schools()->attach($school, ['role' => 'teacher']);
        $student = Student::factory()->for($school)->create();
        $feeType = LegacyFeeType::factory()->for($school)->create(['academic_year' => '2026-2027']);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/schools/'.$school->id.'/fee-invoices?academic_year=2026-2027')
            ->assertForbidden();

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/fee-invoices', [
                'student_id' => $student->id,
                'fee_type_id' => $feeType->id,
                'academic_year' => '2026-2027',
            ])->assertNotFound();

        $this->postJson('/api/schools/'.$school->id.'/legacy-fee-types', [
            'title' => $feeType->title,
            'academic_year' => '2026-2027',
            'amount' => '25.00',
        ])->assertUnprocessable()->assertJsonValidationErrors('title');
    }

    private function enrolledStudentFor(School $school): Student
    {
        $student = Student::factory()->for($school)->create();
        $grade = Grade::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create();
        $grade->sections()->syncWithPivotValues([$section->id], ['school_id' => $school->id]);
        $student->enrollments()->create([
            'school_id' => $school->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'status' => 'active',
            'enrolled_on' => now()->toDateString(),
        ]);

        return $student;
    }

    private function schoolAdminFor(School $school): User
    {
        $user = User::factory()->create();
        $user->schools()->attach($school, ['role' => 'school_admin']);

        return $user;
    }
}
