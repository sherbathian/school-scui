<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'guardian_id', 'admission_number', 'admission_type', 'previous_admission_number', 'admitted_class', 'current_class_label', 'first_name', 'last_name', 'full_name', 'gender', 'religion', 'email', 'phone', 'address', 'photo_path', 'birth_date', 'b_form_number', 'b_form_document_path', 'admission_date', 'leave_date', 'status', 'previous_school_details', 'note', 'caste_or_tribe', 'fee_rate', 'fee_amount', 'fee_waiver', 'arrears_due', 'withdrawn_from_class', 'source_marks_obtained', 'source_marks_percentage', 'source_data', 'source_guardian_data'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'admission_date' => 'date', 'leave_date' => 'date', 'source_data' => 'array', 'source_guardian_data' => 'array'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(StudentLifecycleEvent::class);
    }

    public function feeInvoices(): HasMany
    {
        return $this->hasMany(FeeInvoice::class);
    }

    public function feeCharges(): HasMany
    {
        return $this->hasMany(StudentFeeCharge::class);
    }

    public function legacyFeeSnapshots(): HasMany
    {
        return $this->hasMany(LegacyStudentFeeSnapshot::class);
    }
}
