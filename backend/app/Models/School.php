<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'status'];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function studentInquiries(): HasMany
    {
        return $this->hasMany(StudentInquiry::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function feeTypes(): HasMany
    {
        return $this->hasMany(FeeType::class);
    }

    public function legacyFeeTypes(): HasMany
    {
        return $this->hasMany(LegacyFeeType::class, 'school_id');
    }

    public function feeInvoices(): HasMany
    {
        return $this->hasMany(FeeInvoice::class);
    }

    public function familyFeeReceipts(): HasMany
    {
        return $this->hasMany(FamilyFeeReceipt::class);
    }

    public function legacyFeeSnapshots(): HasMany
    {
        return $this->hasMany(LegacyStudentFeeSnapshot::class);
    }

    public function feeDiscounts(): HasMany
    {
        return $this->hasMany(FeeDiscount::class);
    }

    public function feeGroups(): HasMany
    {
        return $this->hasMany(FeeGroup::class);
    }

    public function feeMasters(): HasMany
    {
        return $this->hasMany(FeeMaster::class);
    }

    public function expenseHeads(): HasMany
    {
        return $this->hasMany(ExpenseHead::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function schoolSessions(): HasMany
    {
        return $this->hasMany(SchoolSession::class);
    }

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function leaveTypes(): HasMany
    {
        return $this->hasMany(LeaveType::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
