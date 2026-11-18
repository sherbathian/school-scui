<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyFeeReceipt extends Model
{
    protected $fillable = [
        'school_id', 'guardian_id', 'source_parent_id', 'receipt_number', 'source_key', 'receipt_date',
        'total_fee', 'discount', 'arrears', 'fund', 'manual_discount', 'total_payable', 'received', 'balance',
        'source_sheet', 'source_row', 'source_data',
    ];

    protected function casts(): array
    {
        return ['receipt_date' => 'date', 'source_data' => 'array'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
