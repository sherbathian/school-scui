<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeGroup extends Model
{
    protected $fillable = ['title', 'description', 'allows_discount'];

    protected function casts(): array
    {
        return ['allows_discount' => 'boolean'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(Grade::class, 'fee_group_grade')->withPivot('fee_group_id', 'grade_id');
    }

    public function feeMasters(): HasMany
    {
        return $this->hasMany(FeeMaster::class);
    }
}
