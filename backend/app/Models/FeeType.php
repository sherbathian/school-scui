<?php

namespace App\Models;

use Database\Factories\FeeTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeType extends Model
{
    /** @use HasFactory<FeeTypeFactory> */
    use HasFactory;

    protected $fillable = ['title', 'description'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function feeMasters(): HasMany
    {
        return $this->hasMany(FeeMaster::class);
    }
}
