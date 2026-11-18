<?php

namespace App\Console\Commands;

use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ImportIfsSchoolData extends Command
{
    protected $signature = 'school:import-ifs-data {file} {--school=1} {--replace : Replace existing student and guardian records for this school}';

    protected $description = 'Import the approved Islamic Foundation School student and legacy fee data package';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        if (! is_file($filePath)) {
            $this->components->error('Import file does not exist.');

            return self::FAILURE;
        }

        try {
            $payload = json_decode((string) file_get_contents($filePath), true, flags: JSON_THROW_ON_ERROR);
            $this->validatePayload($payload);
            $school = School::query()->findOrFail((int) $this->option('school'));
            if (! $this->option('replace')) {
                $this->components->error('Pass --replace to explicitly replace existing student and guardian records.');

                return self::FAILURE;
            }

            $counts = DB::transaction(function () use ($school, $payload): array {
                DB::table('family_fee_receipts')->where('school_id', $school->id)->delete();
                DB::table('student_fee_charges')->where('school_id', $school->id)->delete();
                DB::table('students')->where('school_id', $school->id)->delete();
                DB::table('guardians')->where('school_id', $school->id)->delete();

                $school->update(['name' => 'Islamic Foundation School']);
                $guardians = $this->createGuardians($school, $payload['guardians']);
                $students = $this->createStudents($school, $payload['students'], $guardians);
                $this->createFamilyReceipts($school, $payload['family_fee_receipts'], $guardians);
                $snapshotCount = $this->createFeeSnapshots($school, $payload['fee_snapshots'], $students);

                $session = $school->schoolSessions()->where('title', '2026-2027')->first();
                if ($session) {
                    $session->makeCurrent();
                }

                return ['guardians' => count($payload['guardians']), 'students' => count($students), 'family_fee_receipts' => count($payload['family_fee_receipts']), 'fee_snapshots' => $snapshotCount];
            }, 3);
        } catch (\Throwable $exception) {
            report($exception);
            $this->components->error('Import failed and the database transaction was rolled back. Check the application log for details.');

            return self::FAILURE;
        }

        $this->components->info('Islamic Foundation School import completed.');
        foreach ($counts as $name => $count) {
            $this->line(sprintf('%s: %s', Str::headline($name), number_format($count)));
        }

        return self::SUCCESS;
    }

    private function validatePayload(mixed $payload): void
    {
        if (! is_array($payload)) {
            throw new RuntimeException('Import file must contain a JSON object.');
        }

        foreach (['guardians', 'students', 'family_fee_receipts', 'fee_snapshots'] as $key) {
            if (! isset($payload[$key]) || ! is_array($payload[$key])) {
                throw new RuntimeException("Import file is missing the {$key} array.");
            }
        }

        if (count($payload['students']) < 1000 || count($payload['family_fee_receipts']) < 1000) {
            throw new RuntimeException('Import package record counts are unexpectedly low.');
        }
    }

    /** @param array<int, array<string, mixed>> $records
     * @return array<string, int>
     */
    private function createGuardians(School $school, array $records): array
    {
        $ids = [];
        foreach ($records as $record) {
            $parentId = $record['source_parent_id'] ?? null;
            $guardian = $school->guardians()->create([
                'source_parent_id' => $parentId,
                'source_data' => $record['source_data'] ?? null,
                'father_name' => $record['father_name'] ?? null,
                'father_phone' => $record['father_phone'] ?? null,
                'father_occupation' => $record['father_occupation'] ?? null,
                'father_cnic' => $record['father_cnic'] ?? null,
                'mother_name' => $record['mother_name'] ?? null,
                'mother_phone' => $record['mother_phone'] ?? null,
                'mother_occupation' => $record['mother_occupation'] ?? null,
                'mother_cnic' => $record['mother_cnic'] ?? null,
            ]);
            if ($parentId !== null) {
                $ids[(string) $parentId] = $guardian->id;
            }
            if (isset($record['guardian_key'])) {
                $ids[(string) $record['guardian_key']] = $guardian->id;
            }
        }

        return $ids;
    }

    /** @param array<int, array<string, mixed>> $records
     * @param  array<string, int>  $guardians
     * @return array<string, int>
     */
    private function createStudents(School $school, array $records, array $guardians): array
    {
        $ids = [];
        foreach ($records as $record) {
            $admissionNumber = trim((string) ($record['admission_number'] ?? ''));
            $fullName = trim((string) ($record['full_name'] ?? ''));
            if ($admissionNumber === '' || $fullName === '') {
                throw new RuntimeException('A student is missing a source admission number or name.');
            }

            $nameParts = preg_split('/\s+/', $fullName, 2) ?: [$fullName];
            $currentClass = trim((string) ($record['current_class_label'] ?? ''));
            $status = match (strtoupper($currentClass)) {
                'LEFT OUT' => 'leave',
                'PASS OUT' => 'alumni',
                default => 'active',
            };
            $parentId = isset($record['source_parent_id']) ? (string) $record['source_parent_id'] : null;
            $guardianKey = (string) ($record['guardian_key'] ?? $parentId ?? '');
            $student = $school->students()->create([
                'guardian_id' => $guardianKey !== '' ? ($guardians[$guardianKey] ?? null) : null,
                'admission_number' => $admissionNumber,
                'admission_type' => $record['admission_type'] ?? null,
                'previous_admission_number' => $record['previous_admission_number'] ?? null,
                'admitted_class' => $record['admitted_class'] ?? null,
                'current_class_label' => $currentClass ?: null,
                'full_name' => $fullName,
                'first_name' => $nameParts[0] ?: $fullName,
                'last_name' => $nameParts[1] ?? '',
                'gender' => $this->gender($record['gender'] ?? null),
                'religion' => $this->stringValue($record['religion'] ?? null),
                'phone' => $this->stringValue($record['phone'] ?? null),
                'address' => $this->stringValue($record['address'] ?? null),
                'birth_date' => $record['birth_date'] ?? null,
                'admission_date' => $record['admission_date'] ?? null,
                'leave_date' => $record['leave_date'] ?? null,
                'status' => $status,
                'previous_school_details' => $this->stringValue($record['previous_school_details'] ?? null),
                'note' => $this->stringValue($record['note'] ?? null),
                'caste_or_tribe' => $this->stringValue($record['caste_or_tribe'] ?? null),
                'fee_rate' => $this->decimalValue($record['fee_rate'] ?? null),
                'fee_amount' => $this->decimalValue($record['fee_amount'] ?? null),
                'fee_waiver' => $this->stringValue($record['fee_waiver'] ?? null),
                'arrears_due' => $this->decimalValue($record['arrears_due'] ?? null),
                'withdrawn_from_class' => $this->stringValue($record['withdrawn_from_class'] ?? null),
                'source_marks_obtained' => $this->decimalValue($record['source_marks_obtained'] ?? null),
                'source_marks_percentage' => $this->decimalValue($record['source_marks_percentage'] ?? null),
                'source_data' => $record['source_data'] ?? null,
                'source_guardian_data' => $record['source_guardian_data'] ?? null,
            ]);
            $ids[$admissionNumber] = $student->id;
        }

        return $ids;
    }

    /** @param array<int, array<string, mixed>> $records
     * @param  array<string, int>  $guardians
     */
    private function createFamilyReceipts(School $school, array $records, array $guardians): void
    {
        foreach ($records as $record) {
            $parentId = isset($record['source_parent_id']) ? (string) $record['source_parent_id'] : null;
            $school->familyFeeReceipts()->create([
                'guardian_id' => $parentId !== null ? ($guardians[$parentId] ?? null) : null,
                'source_parent_id' => $parentId,
                'receipt_number' => (string) $record['receipt_number'],
                'source_key' => (string) ($record['source_key'] ?? $record['receipt_number']),
                'receipt_date' => $record['receipt_date'],
                'total_fee' => $this->decimalValue($record['total_fee'] ?? null),
                'discount' => $this->decimalValue($record['discount'] ?? null),
                'arrears' => $this->decimalValue($record['arrears'] ?? null),
                'fund' => $this->decimalValue($record['fund'] ?? null),
                'manual_discount' => $this->decimalValue($record['manual_discount'] ?? null),
                'total_payable' => $this->decimalValue($record['total_payable'] ?? null),
                'received' => $this->decimalValue($record['received'] ?? null),
                'balance' => $this->decimalValue($record['balance'] ?? null),
                'source_sheet' => $record['source_sheet'] ?? null,
                'source_row' => $record['source_row'] ?? null,
                'source_data' => $record['source_data'] ?? null,
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $records
     * @param  array<string, int>  $students
     */
    private function createFeeSnapshots(School $school, array $records, array $students): int
    {
        $count = 0;
        foreach ($records as $record) {
            $studentId = $students[(string) ($record['admission_number'] ?? '')] ?? null;
            if (! $studentId) {
                continue;
            }
            $school->legacyFeeSnapshots()->create([
                'student_id' => $studentId,
                'billing_month' => $record['billing_month'],
                'balance_amount' => $this->decimalValue($record['balance_amount'] ?? null),
                'received_amount' => $this->decimalValue($record['received_amount'] ?? null),
                'balance_source_label' => $record['balance_source_label'] ?? null,
                'received_source_label' => $record['received_source_label'] ?? null,
                'source_sheet' => $record['source_sheet'] ?? null,
                'source_row' => $record['source_row'] ?? null,
                'source_data' => $record['source_data'] ?? null,
            ]);
            $count++;
        }

        return $count;
    }

    private function gender(mixed $value): ?string
    {
        return match (strtolower(trim((string) $value))) {
            'm', 'male', 'boy' => 'male',
            'f', 'female', 'girl' => 'female',
            default => null,
        };
    }

    private function stringValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function decimalValue(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
