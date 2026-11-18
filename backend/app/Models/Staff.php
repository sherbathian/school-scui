<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'designation_id', 'department_id', 'staff_no', 'first_name', 'last_name',
        'father_name', 'mother_name', 'gender', 'marital_status', 'birth_date', 'joining_date',
        'email', 'phone', 'emergency_contact_number', 'cnic', 'photo_path', 'address',
        'qualification', 'work_experience', 'basic_salary', 'bank_account_title', 'bank_name',
        'bank_branch_name', 'bank_account_number', 'status',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'joining_date' => 'date',
            'basic_salary' => 'decimal:2',
            'bank_account_number' => 'encrypted',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(StaffLeaveBalance::class);
    }
}
