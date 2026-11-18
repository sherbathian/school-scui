<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacyFeeType extends Model
{
    use HasFactory;

    protected $table = 'legacy_fee_types';

    protected $fillable = ['school_id', 'title', 'academic_year', 'amount_cents', 'status'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(FeeInvoice::class, 'fee_type_id');
    }
}
