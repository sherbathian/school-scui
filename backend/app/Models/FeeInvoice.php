<?php

namespace App\Models;

use Database\Factories\FeeInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeInvoice extends Model
{
    /** @use HasFactory<FeeInvoiceFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'student_id', 'fee_type_id', 'academic_year', 'description', 'amount_cents', 'due_date', 'status'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'due_date' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(LegacyFeeType::class, 'fee_type_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }
}
