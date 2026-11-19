<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BranchDemoSeeder extends Seeder
{
    public const SLUG = 'schoolhouse-demo-campus';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Branch demo data can only be seeded in local or testing environments.');
        }

        DB::transaction(function (): void {
            $school = School::withTrashed()->firstOrCreate(
                ['slug' => self::SLUG],
                ['name' => 'Schoolhouse Demo Campus', 'status' => 'active'],
            );

            if (! $school->wasRecentlyCreated) {
                $this->command?->info('Schoolhouse Demo Campus already exists. Its records were preserved.');

                return;
            }

            $this->seedBranch($school);
        });
    }

    private function seedBranch(School $school): void
    {
        $year = now()->month >= 8 ? now()->year : now()->year - 1;
        $academicYear = $year.'-'.($year + 1);
        $startsOn = $year.'-08-01';
        $today = now()->toDateString();
        $session = $school->schoolSessions()->create([
            'title' => $academicYear, 'starts_on' => $startsOn, 'ends_on' => ($year + 1).'-07-31',
            'is_current' => true, 'current_marker' => 1, 'status' => 'active',
        ]);

        $sections = collect(['A', 'B'])->map(fn (string $title) => $school->sections()->create(['title' => $title, 'status' => 'active']));
        $subjects = collect(['English', 'Urdu', 'Mathematics', 'Science'])->map(
            fn (string $title) => $school->subjects()->create(['title' => $title, 'status' => 'active'])
        );
        $grades = collect(range(1, 10))->map(function (int $level) use ($school, $sections, $subjects) {
            $grade = $school->grades()->create(['title' => "Grade {$level}", 'status' => 'active']);
            $grade->sections()->syncWithPivotValues($sections->pluck('id')->all(), ['school_id' => $school->id]);
            $grade->subjects()->syncWithPivotValues($subjects->pluck('id')->all(), ['school_id' => $school->id]);

            return $grade;
        });

        $studentNumber = 0;
        for ($family = 1; $family <= 180; $family++) {
            $surname = fake()->lastName();
            $guardian = $school->guardians()->create([
                'father_name' => fake()->firstNameMale().' '.$surname,
                'mother_name' => fake()->firstNameFemale().' '.$surname,
            ]);
            $children = match (true) {
                $family <= 20 => 2,
                $family <= 40 => 3,
                default => 1,
            };
            for ($child = 0; $child < $children; $child++) {
                $school->students()->save($this->student(++$studentNumber, $startsOn, $surname, $guardian->id));
            }
        }
        while ($studentNumber < 300) {
            $school->students()->save($this->student(++$studentNumber, $startsOn, fake()->lastName(), null));
        }

        $students = $school->students()->orderBy('admission_number')->get();
        foreach ($students as $index => $student) {
            $enrollment = $student->enrollments()->create([
                'school_id' => $school->id, 'grade_id' => $grades[$index % 10]->id,
                'section_id' => $sections[intdiv($index, 10) % 2]->id, 'academic_year' => $academicYear,
                'status' => 'active', 'enrolled_on' => $startsOn,
            ]);
            $enrollment->attendanceRecords()->create([
                'school_id' => $school->id, 'attendance_date' => $today,
                'status' => match ($index % 20) {
                    0 => 'absent',
                    1 => 'late',
                    2 => 'excused',
                    default => 'present',
                },
            ]);
        }

        $this->seedStaff($school, $session->id);
        $this->seedFees($school, $session->id, $academicYear, $grades->pluck('id')->all(), $students->modelKeys(), $today);

        foreach (['new', 'contacted', 'visit_scheduled', 'registered', 'rejected'] as $index => $status) {
            $registered = $status === 'registered' ? $students[$index] : null;
            $school->studentInquiries()->create([
                'first_name' => $registered?->first_name ?? fake()->firstName(),
                'last_name' => $registered?->last_name ?? fake()->lastName(),
                'requested_grade_id' => $grades[$index]->id, 'inquiry_date' => $today,
                'status' => $status, 'source' => 'Demo', 'student_id' => $registered?->id,
            ]);
        }

        $withdrawn = $students->last();
        $withdrawn->enrollments()->update(['status' => 'withdrawn', 'ended_on' => $today]);
        $withdrawn->update(['status' => 'leave', 'leave_date' => $today]);
        $withdrawn->lifecycleEvents()->create([
            'school_id' => $school->id, 'event_type' => 'withdrawal',
            'event_date' => $today, 'reason' => 'Demo family relocation',
        ]);

        foreach (['Utilities', 'Classroom supplies', 'Maintenance'] as $index => $title) {
            $head = $school->expenseHeads()->create(['title' => $title]);
            $head->expenses()->create([
                'school_id' => $school->id, 'name' => $title.' demo expense',
                'expense_date' => $today, 'amount' => 1200 * ($index + 1),
            ]);
        }
    }

    private function student(int $number, string $startsOn, string $surname, ?int $guardianId): Student
    {
        $gender = $number % 2 ? 'female' : 'male';
        $firstName = fake()->firstName($gender);

        return new Student([
            'admission_number' => sprintf('DEMO-%03d', $number),
            'first_name' => $firstName, 'last_name' => $surname, 'full_name' => $firstName.' '.$surname,
            'guardian_id' => $guardianId, 'status' => 'active', 'gender' => $gender, 'admission_date' => $startsOn,
        ]);
    }

    private function seedStaff(School $school, int $sessionId): void
    {
        $departments = collect(['Academics', 'Administration', 'Operations'])->map(
            fn (string $title) => $school->departments()->create(['title' => $title])
        );
        $designations = collect(['Teacher', 'Office Assistant', 'Campus Assistant'])->map(
            fn (string $title) => $school->designations()->create(['title' => $title])
        );
        $leave = $school->leaveTypes()->create(['title' => 'Annual leave']);

        for ($index = 0; $index < 30; $index++) {
            $team = $index < 24 ? 0 : ($index < 27 ? 1 : 2);
            $staff = Staff::factory()->for($school)->create([
                'staff_no' => sprintf('DEMO-STAFF-%02d', $index + 1),
                'department_id' => $departments[$team]->id, 'designation_id' => $designations[$team]->id,
            ]);
            $staff->leaveBalances()->create([
                'leave_type_id' => $leave->id, 'school_session_id' => $sessionId,
                'allocated' => 12, 'used' => $index % 4,
            ]);
        }
    }

    /**
     * @param  list<int>  $gradeIds
     * @param  list<int>  $studentIds
     */
    private function seedFees(School $school, int $sessionId, string $academicYear, array $gradeIds, array $studentIds, string $today): void
    {
        $type = $school->feeTypes()->create(['title' => 'Monthly tuition']);
        $school->feeDiscounts()->create(['title' => 'Sibling discount', 'discount_type' => 'percentage', 'value' => 10]);
        $group = $school->feeGroups()->create(['title' => 'All grades', 'allows_discount' => true]);
        $group->grades()->syncWithPivotValues($gradeIds, ['school_id' => $school->id]);
        $billingMonth = substr($today, 0, 7);
        $master = $school->feeMasters()->create([
            'school_session_id' => $sessionId, 'fee_group_id' => $group->id, 'fee_type_id' => $type->id,
            'billing_month' => $billingMonth, 'amount' => 3000, 'due_date' => $today,
        ]);
        $legacyType = $school->legacyFeeTypes()->create([
            'title' => 'Demo registration', 'academic_year' => $academicYear, 'amount_cents' => 150000, 'status' => 'active',
        ]);

        foreach (array_slice($studentIds, 0, 30) as $index => $studentId) {
            StudentFeeCharge::create([
                'school_id' => $school->id, 'student_id' => $studentId, 'fee_master_id' => $master->id,
                'school_session_id' => $sessionId, 'billing_month' => $billingMonth, 'amount' => 3000, 'due_date' => $today,
            ]);
            $invoice = $school->feeInvoices()->create([
                'student_id' => $studentId, 'fee_type_id' => $legacyType->id, 'academic_year' => $academicYear,
                'description' => 'Demo registration', 'amount_cents' => 150000, 'due_date' => $today,
                'status' => $index < 10 ? 'paid' : 'open',
            ]);
            if ($index < 10) {
                $invoice->payments()->create([
                    'school_id' => $school->id, 'amount_cents' => 150000, 'paid_on' => $today,
                    'reference' => 'DEMO-PAY-'.($index + 1),
                ]);
            }
        }
    }
}
