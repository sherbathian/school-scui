<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFeeCharge extends Model
{
    protected $fillable = ['school_id', 'fee_master_id', 'school_session_id', 'student_id', 'billing_month', 'amount', 'due_date', 'status'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_date' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeMaster(): BelongsTo
    {
        return $this->belongsTo(FeeMaster::class);
    }

    public function schoolSession(): BelongsTo
    {
        return $this->belongsTo(SchoolSession::class);
    }
}
