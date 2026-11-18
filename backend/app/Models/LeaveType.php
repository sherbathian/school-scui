<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = ['title'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
