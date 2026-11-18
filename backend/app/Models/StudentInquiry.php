<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentInquiry extends Model
{
    protected $fillable = ['school_id', 'first_name', 'last_name', 'guardian_name', 'guardian_phone', 'guardian_email', 'requested_grade_id', 'inquiry_date', 'source', 'status', 'notes', 'student_id'];

    protected function casts(): array
    {
        return ['inquiry_date' => 'date'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function requestedGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'requested_grade_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
