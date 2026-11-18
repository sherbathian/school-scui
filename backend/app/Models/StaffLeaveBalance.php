<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLeaveBalance extends Model
{
    protected $fillable = ['leave_type_id', 'school_session_id', 'allocated', 'used'];

    protected function casts(): array
    {
        return ['allocated' => 'decimal:1', 'used' => 'decimal:1'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function schoolSession(): BelongsTo
    {
        return $this->belongsTo(SchoolSession::class);
    }
}
