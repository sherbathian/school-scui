<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeDiscount extends Model
{
    protected $fillable = ['title', 'discount_type', 'value', 'description'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
