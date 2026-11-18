<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyStudentFeeSnapshot extends Model
{
    protected $fillable = [
        'school_id', 'student_id', 'billing_month', 'balance_amount', 'received_amount',
        'balance_source_label', 'received_source_label', 'source_sheet', 'source_row', 'source_data',
    ];

    protected function casts(): array
    {
        return ['source_data' => 'array'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
