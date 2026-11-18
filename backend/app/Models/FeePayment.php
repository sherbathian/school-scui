<?php

namespace App\Models;

use Database\Factories\FeePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePayment extends Model
{
    /** @use HasFactory<FeePaymentFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'fee_invoice_id', 'amount_cents', 'paid_on', 'reference', 'recorded_by_user_id'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'paid_on' => 'date'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
