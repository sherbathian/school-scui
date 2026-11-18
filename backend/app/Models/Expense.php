<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = ['school_id', 'expense_head_id', 'name', 'description', 'invoice_number', 'expense_date', 'amount', 'attachment_path'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(ExpenseHead::class, 'expense_head_id');
    }
}
